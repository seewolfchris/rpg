import './bootstrap';
import { characterSheetForm, registerCharacterSheetComponent } from './character-sheet';

const QUEUE_DB_NAME = 'chroniken-pbp';
const QUEUE_STORE_NAME = 'postQueue';
const SYNC_TAG_POSTS = 'pbp-sync-posts';
const OFFLINE_POST_FORM_SELECTOR = 'form[data-offline-post-form]';
const CACHEABLE_LINK_SELECTOR = 'a[href*="/campaigns/"][href*="/scenes/"], a[href*="/characters/"]';
const PWA_INSTALL_BUTTON_SELECTOR = '[data-pwa-install-button]';
const BROWSER_NOTIFICATION_ROOT_SELECTOR = '[data-browser-notifications]';
const BROWSER_NOTIFICATION_STATUS_SELECTOR = '[data-browser-notifications-status]';
const BROWSER_NOTIFICATION_ENABLE_SELECTOR = '[data-browser-notifications-enable]';
const BROWSER_NOTIFICATION_POLL_INTERVAL_MS = 45000;
const BROWSER_NOTIFICATION_SEEN_STORAGE_KEY = 'chroniken-browser-notification-seen-ids';
const BROWSER_NOTIFICATION_SEEN_LIMIT = 250;

let swRegistration = null;
let deferredInstallPrompt = null;
let browserNotificationConfig = null;
let browserNotificationSeenIds = new Set();
let browserNotificationPollTimer = null;

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

const bootApplication = async () => {
    setupPwaInstallPrompt();
    setupOfflinePostQueue();
    setupOnlineSyncTrigger();
    setupServiceWorkerMessageHandling();

    swRegistration = await registerServiceWorker();
    await warmOfflineReadingCache();
    setupBrowserNotifications();

    if (navigator.onLine) {
        await triggerQueuedPostSync();
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        bootApplication();
    });
} else {
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
        showSyncNotice('App wurde installiert und ist jetzt als Startbildschirm-App verfügbar.', 'success');
    });

    installButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            if (!deferredInstallPrompt) {
                showSyncNotice('Installationsdialog ist auf diesem Gerät aktuell nicht verfügbar.', 'warning');
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
                    showSyncNotice('Background Sync fehlt. Beiträge werden bei nächstem Online-Besuch verarbeitet.', 'warning');
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
            showSyncNotice('Verbindung wiederhergestellt: Offline-Beiträge werden synchronisiert.', 'success');
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
                showSyncNotice(`Synchronisierung unvollständig. Verbleibende Queue: ${remaining}.`, 'warning');
                return;
            }

            showSyncNotice('Offline-Queue erfolgreich synchronisiert.', 'success');
        }
    });
}

function setupBrowserNotifications() {
    const root = document.querySelector(BROWSER_NOTIFICATION_ROOT_SELECTOR);

    if (!(root instanceof HTMLElement)) {
        return;
    }

    const pollUrl = root.dataset.pollUrl || '';

    if (!pollUrl) {
        return;
    }

    browserNotificationConfig = {
        pollUrl,
        appName: root.dataset.appName || 'C76-RPG',
        enabledKinds: normalizeBrowserNotificationKinds(root.dataset.enabledKinds),
        statusNode: document.querySelector(BROWSER_NOTIFICATION_STATUS_SELECTOR),
        enableButton: document.querySelector(BROWSER_NOTIFICATION_ENABLE_SELECTOR),
    };

    browserNotificationSeenIds = loadSeenBrowserNotificationIds();

    if (browserNotificationConfig.enableButton instanceof HTMLButtonElement) {
        browserNotificationConfig.enableButton.addEventListener('click', async () => {
            await requestBrowserNotificationPermission();
        });
    }

    window.addEventListener('online', () => {
        if (isBrowserNotificationPollingEnabled()) {
            void pollBrowserNotifications();
        }
    });

    updateBrowserNotificationStatus();

    if (isBrowserNotificationPollingEnabled()) {
        startBrowserNotificationPolling();
    }
}

function normalizeBrowserNotificationKinds(rawKinds) {
    if (!rawKinds) {
        return [];
    }

    const source = Array.isArray(rawKinds) ? rawKinds : safeParseBrowserNotificationKinds(rawKinds);

    if (!Array.isArray(source)) {
        return [];
    }

    return source
        .filter((kind) => typeof kind === 'string')
        .map((kind) => kind.trim())
        .filter((kind) => kind !== '');
}

function safeParseBrowserNotificationKinds(rawKinds) {
    try {
        return JSON.parse(rawKinds);
    } catch {
        return null;
    }
}

function updateBrowserNotificationStatus() {
    if (!browserNotificationConfig) {
        return;
    }

    const statusNode = browserNotificationConfig.statusNode;
    const enableButton = browserNotificationConfig.enableButton;
    const hasBrowserKinds = browserNotificationConfig.enabledKinds.length > 0;
    const supportsNotifications = 'Notification' in window;
    let statusMessage = 'Browser-Benachrichtigungen werden geprüft.';
    let disableEnableButton = false;
    let enableButtonLabel = 'Browser-Permission aktivieren';

    if (!hasBrowserKinds) {
        statusMessage = 'Browser-Push ist in den Mitteilungs-Präferenzen aktuell deaktiviert.';
        disableEnableButton = true;
    } else if (!supportsNotifications) {
        statusMessage = 'Dieser Browser unterstützt keine Notification-API.';
        disableEnableButton = true;
    } else if (Notification.permission === 'granted') {
        statusMessage = 'Browser-Push ist aktiv. Neue Mitteilungen werden automatisch geprüft.';
        disableEnableButton = true;
        enableButtonLabel = 'Browser-Permission aktiv';
    } else if (Notification.permission === 'denied') {
        statusMessage = 'Browser-Push wurde blockiert. Erlaube Notifications in deinen Browser-Einstellungen.';
        disableEnableButton = true;
        enableButtonLabel = 'Browser-Permission blockiert';
    } else {
        statusMessage = 'Browser-Push wartet auf Freigabe.';
    }

    if (statusNode instanceof HTMLElement) {
        statusNode.textContent = statusMessage;
    }

    if (enableButton instanceof HTMLButtonElement) {
        enableButton.textContent = enableButtonLabel;
        enableButton.disabled = disableEnableButton;

        if (disableEnableButton) {
            enableButton.classList.add('cursor-not-allowed', 'opacity-60');
            enableButton.setAttribute('aria-disabled', 'true');
        } else {
            enableButton.classList.remove('cursor-not-allowed', 'opacity-60');
            enableButton.setAttribute('aria-disabled', 'false');
        }
    }
}

async function requestBrowserNotificationPermission() {
    if (!browserNotificationConfig) {
        return;
    }

    if (!('Notification' in window)) {
        showSyncNotice('Browser-Benachrichtigungen werden auf diesem Gerät nicht unterstützt.', 'warning');
        updateBrowserNotificationStatus();
        return;
    }

    if (!browserNotificationConfig.enabledKinds.length) {
        showSyncNotice('Aktiviere zuerst Browser-Push in den Mitteilungs-Präferenzen.', 'warning');
        updateBrowserNotificationStatus();
        return;
    }

    if (Notification.permission === 'denied') {
        showSyncNotice('Browser-Permission ist blockiert. Bitte im Browser manuell freigeben.', 'warning');
        updateBrowserNotificationStatus();
        return;
    }

    if (Notification.permission === 'granted') {
        startBrowserNotificationPolling();
        updateBrowserNotificationStatus();
        return;
    }

    try {
        const permission = await Notification.requestPermission();

        if (permission === 'granted') {
            showSyncNotice('Browser-Push wurde aktiviert.', 'success');
            startBrowserNotificationPolling();
        } else {
            showSyncNotice('Browser-Push wurde nicht freigegeben.', 'warning');
        }
    } catch (error) {
        console.error('Notification permission request failed:', error);
        showSyncNotice('Browser-Permission konnte nicht angefragt werden.', 'error');
    }

    updateBrowserNotificationStatus();
}

function isBrowserNotificationPollingEnabled() {
    return (
        Boolean(browserNotificationConfig) &&
        browserNotificationConfig.enabledKinds.length > 0 &&
        'Notification' in window &&
        Notification.permission === 'granted'
    );
}

function startBrowserNotificationPolling() {
    if (!isBrowserNotificationPollingEnabled()) {
        stopBrowserNotificationPolling();
        updateBrowserNotificationStatus();
        return;
    }

    stopBrowserNotificationPolling();

    void pollBrowserNotifications();
    browserNotificationPollTimer = window.setInterval(() => {
        void pollBrowserNotifications();
    }, BROWSER_NOTIFICATION_POLL_INTERVAL_MS);

    updateBrowserNotificationStatus();
}

function stopBrowserNotificationPolling() {
    if (browserNotificationPollTimer === null) {
        return;
    }

    window.clearInterval(browserNotificationPollTimer);
    browserNotificationPollTimer = null;
}

async function pollBrowserNotifications() {
    if (!browserNotificationConfig || !navigator.onLine) {
        return;
    }

    if (!isBrowserNotificationPollingEnabled()) {
        stopBrowserNotificationPolling();
        updateBrowserNotificationStatus();
        return;
    }

    try {
        const response = await fetch(browserNotificationConfig.pollUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            if (response.status === 401 || response.status === 403) {
                stopBrowserNotificationPolling();
            }

            return;
        }

        const payload = await response.json();
        const enabledKinds = normalizeBrowserNotificationKinds(payload?.browser_enabled_kinds ?? []);

        browserNotificationConfig.enabledKinds = enabledKinds;

        if (!enabledKinds.length) {
            stopBrowserNotificationPolling();
            updateBrowserNotificationStatus();
            return;
        }

        const notifications = Array.isArray(payload?.notifications) ? payload.notifications : [];

        for (const item of notifications) {
            const notificationId = typeof item?.id === 'string' ? item.id : '';

            if (!notificationId || browserNotificationSeenIds.has(notificationId)) {
                continue;
            }

            await showBrowserNotification(item);
            rememberSeenBrowserNotificationId(notificationId);
        }

        persistSeenBrowserNotificationIds();
    } catch (error) {
        console.error('Browser notification poll failed:', error);
    }
}

async function showBrowserNotification(item) {
    if (!browserNotificationConfig || !('Notification' in window) || Notification.permission !== 'granted') {
        return;
    }

    const title = typeof item?.title === 'string' && item.title.trim() !== '' ? item.title.trim() : browserNotificationConfig.appName;
    const message = typeof item?.message === 'string' ? item.message : '';
    const actionUrl =
        typeof item?.action_url === 'string' && item.action_url.trim() !== ''
            ? item.action_url
            : '/notifications';
    const createdAtTimestamp = typeof item?.created_at === 'string' ? Date.parse(item.created_at) : Number.NaN;
    const options = {
        body: message,
        tag: typeof item?.id === 'string' ? `chroniken-notification-${item.id}` : undefined,
        icon: '/images/icons/icon-192.svg',
        badge: '/images/icons/icon-192.svg',
        data: {
            actionUrl,
        },
        timestamp: Number.isNaN(createdAtTimestamp) ? Date.now() : createdAtTimestamp,
    };

    const registration = await getActiveServiceWorkerRegistration();

    if (registration && typeof registration.showNotification === 'function') {
        await registration.showNotification(title, options);
        return;
    }

    const notification = new Notification(title, options);
    notification.onclick = () => {
        notification.close();
        window.focus();
        window.location.assign(actionUrl);
    };
}

function loadSeenBrowserNotificationIds() {
    try {
        const raw = window.localStorage.getItem(BROWSER_NOTIFICATION_SEEN_STORAGE_KEY);

        if (!raw) {
            return new Set();
        }

        const parsed = JSON.parse(raw);

        if (!Array.isArray(parsed)) {
            return new Set();
        }

        return new Set(
            parsed
                .filter((value) => typeof value === 'string')
                .slice(-BROWSER_NOTIFICATION_SEEN_LIMIT),
        );
    } catch {
        return new Set();
    }
}

function rememberSeenBrowserNotificationId(notificationId) {
    browserNotificationSeenIds.add(notificationId);

    if (browserNotificationSeenIds.size <= BROWSER_NOTIFICATION_SEEN_LIMIT) {
        return;
    }

    browserNotificationSeenIds = new Set(Array.from(browserNotificationSeenIds).slice(-BROWSER_NOTIFICATION_SEEN_LIMIT));
}

function persistSeenBrowserNotificationIds() {
    try {
        window.localStorage.setItem(
            BROWSER_NOTIFICATION_SEEN_STORAGE_KEY,
            JSON.stringify(Array.from(browserNotificationSeenIds).slice(-BROWSER_NOTIFICATION_SEEN_LIMIT)),
        );
    } catch {
        // Ignore storage errors, notifications still work for current session.
    }
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
