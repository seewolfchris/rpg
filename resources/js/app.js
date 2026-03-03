import './bootstrap';
import { initDiceRoller } from './dice-roller';
import { characterSheetForm, registerCharacterSheetComponent } from './character-sheet';

const QUEUE_DB_NAME = 'chroniken-pbp';
const QUEUE_STORE_NAME = 'postQueue';
const SYNC_TAG_POSTS = 'pbp-sync-posts';
const OFFLINE_POST_FORM_SELECTOR = 'form[data-offline-post-form]';
const CACHEABLE_LINK_SELECTOR = 'a[href*="/campaigns/"][href*="/scenes/"], a[href*="/characters/"]';
const PWA_INSTALL_BUTTON_SELECTOR = '[data-pwa-install-button]';

let swRegistration = null;
let deferredInstallPrompt = null;

window.characterSheetForm = characterSheetForm;

window.addEventListener('alpine:init', () => {
    if (!window.Alpine) {
        return;
    }

    registerCharacterSheetComponent(window.Alpine);
});

const startDeferredAlpine = () => {
    if (typeof window.__startAlpine === 'function') {
        window.__startAlpine();
        delete window.__startAlpine;
    }
};

startDeferredAlpine();
window.addEventListener('load', startDeferredAlpine);

const bootDiceRoller = () => {
    initDiceRoller();
};

const bootApplication = async () => {
    setupPwaInstallPrompt();
    setupOfflinePostQueue();
    setupOnlineSyncTrigger();
    setupServiceWorkerMessageHandling();

    swRegistration = await registerServiceWorker();
    await warmOfflineReadingCache();

    if (navigator.onLine) {
        await triggerQueuedPostSync();
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        bootDiceRoller();
        bootApplication();
    });
} else {
    bootDiceRoller();
    bootApplication();
}

async function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        return null;
    }

    try {
        const registration = await navigator.serviceWorker.register('/sw.js');
        const readyRegistration = await navigator.serviceWorker.ready.catch(() => null);

        if (readyRegistration) {
            return readyRegistration;
        }

        return registration;
    } catch (error) {
        console.error('Service worker registration failed:', error);

        return null;
    }
}

function setupPwaInstallPrompt() {
    const installButtons = Array.from(document.querySelectorAll(PWA_INSTALL_BUTTON_SELECTOR));

    if (!installButtons.length) {
        return;
    }

    hideInstallButtons(installButtons);

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredInstallPrompt = event;
        showInstallButtons(installButtons);
    });

    window.addEventListener('appinstalled', () => {
        deferredInstallPrompt = null;
        hideInstallButtons(installButtons);
        showSyncNotice('App wurde installiert und ist jetzt als Startbildschirm-App verfuegbar.', 'success');
    });

    installButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            if (!deferredInstallPrompt) {
                showSyncNotice('Installationsdialog ist auf diesem Geraet aktuell nicht verfuegbar.', 'warning');
                return;
            }

            deferredInstallPrompt.prompt();
            const choice = await deferredInstallPrompt.userChoice;

            deferredInstallPrompt = null;
            hideInstallButtons(installButtons);

            if (choice?.outcome !== 'accepted') {
                showSyncNotice('Installation wurde abgebrochen.', 'warning');
            }
        });
    });
}

function showInstallButtons(buttons) {
    buttons.forEach((button) => {
        button.classList.remove('hidden');
        button.removeAttribute('aria-hidden');
    });
}

function hideInstallButtons(buttons) {
    buttons.forEach((button) => {
        button.classList.add('hidden');
        button.setAttribute('aria-hidden', 'true');
    });
}

function setupOfflinePostQueue() {
    const postForms = document.querySelectorAll(OFFLINE_POST_FORM_SELECTOR);

    if (!postForms.length) {
        return;
    }

    postForms.forEach((form) => {
        form.addEventListener('submit', async (event) => {
            if (navigator.onLine) {
                return;
            }

            event.preventDefault();

            try {
                await queuePostSubmission(form);
                form.reset();

                showSyncNotice('Offline erkannt: Beitrag gespeichert und zur Synchronisierung vorgemerkt.', 'success');

                const syncTriggered = await triggerQueuedPostSync();

                if (!syncTriggered) {
                    showSyncNotice('Background Sync fehlt. Beitraege werden bei naechstem Online-Besuch verarbeitet.', 'warning');
                }
            } catch (error) {
                console.error('Offline post queue failed:', error);
                showSyncNotice('Beitrag konnte offline nicht gespeichert werden.', 'error');
            }
        });
    });
}

function setupOnlineSyncTrigger() {
    window.addEventListener('online', async () => {
        const syncTriggered = await triggerQueuedPostSync();

        if (syncTriggered) {
            showSyncNotice('Verbindung wiederhergestellt: Offline-Beitraege werden synchronisiert.', 'success');
        }
    });
}

function setupServiceWorkerMessageHandling() {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    navigator.serviceWorker.addEventListener('message', (event) => {
        const data = event.data;

        if (!data || typeof data !== 'object' || !data.type) {
            return;
        }

        if (data.type === 'POST_SYNC_DROPPED') {
            showSyncNotice('Ein Offline-Beitrag konnte wegen Validierung nicht gesendet werden und wurde verworfen.', 'warning');
            return;
        }

        if (data.type === 'POST_SYNC_FINISHED') {
            const remaining = Number(data.payload?.remaining || 0);

            if (remaining > 0) {
                showSyncNotice(`Synchronisierung unvollstaendig. Verbleibende Queue: ${remaining}.`, 'warning');
                return;
            }

            showSyncNotice('Offline-Queue erfolgreich synchronisiert.', 'success');
        }
    });
}

async function warmOfflineReadingCache() {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    const urls = [window.location.href];

    document.querySelectorAll(CACHEABLE_LINK_SELECTOR).forEach((link) => {
        if (link instanceof HTMLAnchorElement) {
            urls.push(link.href);
        }
    });

    const filteredUrls = Array.from(new Set(urls.filter((url) => isOfflineReadableUrl(url)).slice(0, 40)));

    if (!filteredUrls.length) {
        return;
    }

    const activeWorker =
        navigator.serviceWorker.controller ||
        (await getActiveServiceWorkerRegistration())?.active ||
        null;

    if (!activeWorker) {
        return;
    }

    activeWorker.postMessage({
        type: 'CACHE_URLS',
        urls: filteredUrls,
    });
}

function isOfflineReadableUrl(url) {
    try {
        const parsed = new URL(url, window.location.origin);

        if (parsed.origin !== window.location.origin) {
            return false;
        }

        return (
            /^\/campaigns\/[^/]+\/scenes\/[^/]+\/?$/.test(parsed.pathname) ||
            /^\/characters\/[^/]+\/?$/.test(parsed.pathname)
        );
    } catch {
        return false;
    }
}

async function triggerQueuedPostSync() {
    if (!('serviceWorker' in navigator)) {
        return false;
    }

    const registration = await getActiveServiceWorkerRegistration();

    if (!registration?.active) {
        return false;
    }

    if ('sync' in registration) {
        try {
            await registration.sync.register(SYNC_TAG_POSTS);

            return true;
        } catch (error) {
            if (
                error instanceof DOMException &&
                (error.name === 'InvalidStateError' || error.name === 'NotAllowedError' || error.name === 'SecurityError')
            ) {
                return false;
            }

            console.error('Background sync registration failed:', error);
        }
    }

    registration.active.postMessage({ type: 'SYNC_POSTS_NOW' });
    return true;
}

async function getActiveServiceWorkerRegistration() {
    if (!('serviceWorker' in navigator)) {
        return null;
    }

    if (swRegistration?.active) {
        return swRegistration;
    }

    const readyRegistration = await navigator.serviceWorker.ready.catch(() => null);

    if (readyRegistration?.active) {
        swRegistration = readyRegistration;
        return readyRegistration;
    }

    return null;
}

async function queuePostSubmission(form) {
    const formData = new FormData(form);
    const entries = [];

    for (const [key, value] of formData.entries()) {
        if (typeof value !== 'string') {
            continue;
        }

        entries.push([key, value]);
    }

    const payload = {
        url: form.action,
        method: (form.method || 'POST').toUpperCase(),
        entries,
        queued_at: new Date().toISOString(),
        source_path: window.location.pathname,
    };

    const database = await openQueueDatabase();

    await new Promise((resolve, reject) => {
        const transaction = database.transaction(QUEUE_STORE_NAME, 'readwrite');
        const store = transaction.objectStore(QUEUE_STORE_NAME);
        const request = store.add(payload);

        request.onsuccess = () => resolve(undefined);
        request.onerror = () => reject(request.error || new Error('Could not persist offline post.'));

        transaction.oncomplete = () => {
            database.close();
        };
    });
}

function openQueueDatabase() {
    return new Promise((resolve, reject) => {
        if (!('indexedDB' in window)) {
            reject(new Error('IndexedDB is not supported in this browser.'));
            return;
        }

        const request = indexedDB.open(QUEUE_DB_NAME, 1);

        request.onupgradeneeded = () => {
            const database = request.result;

            if (!database.objectStoreNames.contains(QUEUE_STORE_NAME)) {
                database.createObjectStore(QUEUE_STORE_NAME, {
                    keyPath: 'id',
                    autoIncrement: true,
                });
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error || new Error('Could not open offline queue database.'));
    });
}

function showSyncNotice(message, level = 'info') {
    const container = getOrCreateSyncNoticeContainer();
    const notice = document.createElement('p');

    notice.className = `rounded border px-3 py-2 text-xs uppercase tracking-[0.08em] ${resolveNoticeClass(level)}`;
    notice.textContent = message;

    container.appendChild(notice);

    window.setTimeout(() => {
        notice.remove();

        if (!container.children.length) {
            container.remove();
        }
    }, 9000);
}

function getOrCreateSyncNoticeContainer() {
    const existingContainer = document.getElementById('offline-sync-notices');

    if (existingContainer) {
        return existingContainer;
    }

    const container = document.createElement('div');
    container.id = 'offline-sync-notices';
    container.className = 'fixed bottom-4 right-4 z-50 max-w-sm space-y-2';

    document.body.appendChild(container);

    return container;
}

function resolveNoticeClass(level) {
    if (level === 'success') {
        return 'border-emerald-600/70 bg-emerald-900/30 text-emerald-100';
    }

    if (level === 'warning') {
        return 'border-amber-600/70 bg-amber-900/30 text-amber-100';
    }

    if (level === 'error') {
        return 'border-red-700/70 bg-red-900/30 text-red-100';
    }

    return 'border-stone-600/80 bg-neutral-900/80 text-stone-100';
}
