import './bootstrap';
import { characterSheetForm, registerCharacterSheetComponent } from './character-sheet';
import {
    createPostDraftStorageKey,
    hasPostDraftContent,
    normalizePostDraftForRestore,
    parsePostDraftPayload,
} from './post-editor-draft';

const QUEUE_DB_NAME = 'chroniken-pbp';
const QUEUE_STORE_NAME = 'postQueue';
const SYNC_TAG_POSTS = 'pbp-sync-posts';
const OFFLINE_POST_FORM_SELECTOR = 'form[data-offline-post-form]';
const CACHEABLE_LINK_SELECTOR = 'a[href*="/campaigns/"][href*="/scenes/"], a[href*="/characters/"]';
const PWA_INSTALL_BUTTON_SELECTOR = '[data-pwa-install-button]';
const BROWSER_NOTIFICATION_ROOT_SELECTOR = '[data-browser-notifications]';
const BROWSER_NOTIFICATION_STATUS_SELECTOR = '[data-browser-notifications-status]';
const BROWSER_NOTIFICATION_ENABLE_SELECTOR = '[data-browser-notifications-enable]';
const SCENE_THREAD_READING_MODE_SELECTOR = '[data-scene-thread-reading-mode]';
const POST_EDITOR_SELECTOR = 'form[data-post-editor]';
const POST_PREVIEW_DEBOUNCE_MS = 450;
const POST_DRAFT_DEBOUNCE_MS = 350;

let swRegistration = null;
let deferredInstallPrompt = null;
let browserNotificationConfig = null;

window.characterSheetForm = characterSheetForm;

if (window.Alpine) {
    registerCharacterSheetComponent(window.Alpine);
    window.Alpine.start();
}

document.addEventListener('htmx:afterSwap', (event) => {
    const target = event.detail?.target;

    if (window.Alpine && target instanceof HTMLElement) {
        window.Alpine.initTree(target);
    }

    setupSceneThreadReadingMode();
    setupPostEditorEnhancements();
});

const bootApplication = async () => {
    setupSceneThreadReadingMode();
    setupPostEditorEnhancements();
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

function setupSceneThreadReadingMode() {
    document.querySelectorAll(SCENE_THREAD_READING_MODE_SELECTOR).forEach((root) => {
        if (!(root instanceof HTMLElement)) {
            return;
        }

        const sceneId = String(root.dataset.sceneId || '').trim();
        const oocDetailsList = Array.from(root.querySelectorAll('details[data-ooc-thread]'))
            .filter((element) => element instanceof HTMLDetailsElement);

        if (!sceneId || oocDetailsList.length === 0) {
            return;
        }

        const storageKey = `c76:scene-ooc-open:${sceneId}`;
        const stored = readLocalStorageValue(storageKey);

        oocDetailsList.forEach((oocDetails) => {
            if (stored === '1') {
                oocDetails.open = true;
            } else if (stored === '0') {
                oocDetails.open = false;
            }

            if (oocDetails.dataset.readingModeBound === '1') {
                return;
            }

            oocDetails.dataset.readingModeBound = '1';
            oocDetails.addEventListener('toggle', () => {
                writeLocalStorageValue(storageKey, oocDetails.open ? '1' : '0');
            });
        });
    });
}

function setupPostEditorEnhancements() {
    document.querySelectorAll(POST_EDITOR_SELECTOR).forEach((formNode) => {
        if (!(formNode instanceof HTMLFormElement)) {
            return;
        }

        if (formNode.dataset.postEditorEnhanced === '1') {
            return;
        }

        formNode.dataset.postEditorEnhanced = '1';

        const contentField = formNode.querySelector('[data-post-content-input]');
        const formatField = formNode.querySelector('[data-post-content-format]');
        const postTypeField = formNode.querySelector('select[name="post_type"]');
        const characterField = formNode.querySelector('select[name="character_id"]');
        const icQuoteField = formNode.querySelector('input[name="ic_quote"]');
        const previewRoot = formNode.querySelector('[data-post-preview]');
        const previewStatusNode = formNode.querySelector('[data-post-preview-status]');
        const previewOutputNode = formNode.querySelector('[data-post-preview-output]');
        const previewUrl = String(formNode.dataset.previewUrl || '').trim();
        const draftKeySeed = String(formNode.dataset.draftKey || '').trim();
        const storageKey = createPostDraftStorageKey(draftKeySeed);

        if (!(contentField instanceof HTMLTextAreaElement) || !(formatField instanceof HTMLSelectElement)) {
            return;
        }

        const restoreDraftIfNeeded = () => {
            if (!storageKey || contentField.value.trim() !== '') {
                return;
            }

            const raw = readLocalStorageValue(storageKey);
            if (!raw) {
                return;
            }

            const parsed = parsePostDraftPayload(raw);

            if (!parsed) {
                removeLocalStorageValue(storageKey);
                return;
            }

            const restored = normalizePostDraftForRestore(parsed, {
                allowedFormats: Array.from(formatField.options).map((option) => option.value),
                allowedPostTypes: postTypeField instanceof HTMLSelectElement
                    ? Array.from(postTypeField.options).map((option) => option.value)
                    : [],
                allowedCharacterIds: characterField instanceof HTMLSelectElement
                    ? Array.from(characterField.options).map((option) => option.value)
                    : [],
            });

            if (!restored) {
                removeLocalStorageValue(storageKey);
                return;
            }

            contentField.value = restored.content;

            if (restored.content_format !== '') {
                formatField.value = restored.content_format;
            }

            if (postTypeField instanceof HTMLSelectElement && restored.post_type !== '') {
                postTypeField.value = restored.post_type;
            }

            if (characterField instanceof HTMLSelectElement && restored.character_id !== '') {
                characterField.value = restored.character_id;
            }

            if (icQuoteField instanceof HTMLInputElement) {
                icQuoteField.value = restored.ic_quote;
            }

            showSyncNotice('Lokaler Entwurf wiederhergestellt.', 'success');
        };

        const persistDraft = debounce(() => {
            if (!storageKey) {
                return;
            }

            const payload = {
                content: contentField.value,
                content_format: formatField.value,
                post_type: postTypeField instanceof HTMLSelectElement ? postTypeField.value : '',
                character_id: characterField instanceof HTMLSelectElement ? characterField.value : '',
                ic_quote: icQuoteField instanceof HTMLInputElement ? icQuoteField.value : '',
                saved_at: new Date().toISOString(),
            };

            if (!hasPostDraftContent(payload)) {
                removeLocalStorageValue(storageKey);
                return;
            }

            writeLocalStorageValue(storageKey, JSON.stringify(payload));
        }, POST_DRAFT_DEBOUNCE_MS);

        let previewAbortController = null;

        const renderPreview = debounce(async () => {
            if (!(previewRoot instanceof HTMLElement) || !(previewStatusNode instanceof HTMLElement) || !(previewOutputNode instanceof HTMLElement)) {
                return;
            }

            const format = String(formatField.value || '');
            const content = String(contentField.value || '');

            if (format !== 'markdown') {
                previewStatusNode.textContent = 'Live-Vorschau ist nur bei Markdown aktiv.';
                previewOutputNode.innerHTML = '<p class="text-stone-500">Für dieses Format ist keine Live-Vorschau aktiv.</p>';
                return;
            }

            if (content.trim() === '') {
                previewStatusNode.textContent = 'Schreibe Text, um eine Vorschau zu sehen.';
                previewOutputNode.innerHTML = '<p class="text-stone-500">Noch keine Vorschau verfügbar.</p>';
                return;
            }

            if (!previewUrl) {
                previewStatusNode.textContent = 'Live-Vorschau ist derzeit nicht verfügbar.';
                return;
            }

            if (previewAbortController instanceof AbortController) {
                previewAbortController.abort();
            }

            previewAbortController = new AbortController();
            previewStatusNode.textContent = 'Vorschau wird aktualisiert ...';

            try {
                const response = await fetch(previewUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        content_format: 'markdown',
                        content,
                    }),
                    signal: previewAbortController.signal,
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const payload = await response.json();
                const html = typeof payload?.html === 'string'
                    ? payload.html
                    : '<p class="text-stone-500">Keine Vorschau verfügbar.</p>';

                previewOutputNode.innerHTML = html;
                previewStatusNode.textContent = 'Live-Vorschau aktiv.';
            } catch (error) {
                if (error instanceof DOMException && error.name === 'AbortError') {
                    return;
                }

                previewStatusNode.textContent = 'Vorschau konnte nicht geladen werden.';
            }
        }, POST_PREVIEW_DEBOUNCE_MS);

        restoreDraftIfNeeded();
        renderPreview();

        contentField.addEventListener('input', () => {
            persistDraft();
            renderPreview();
        });

        formatField.addEventListener('change', () => {
            persistDraft();
            renderPreview();
        });

        if (postTypeField instanceof HTMLSelectElement) {
            postTypeField.addEventListener('change', persistDraft);
        }

        if (characterField instanceof HTMLSelectElement) {
            characterField.addEventListener('change', persistDraft);
        }

        if (icQuoteField instanceof HTMLInputElement) {
            icQuoteField.addEventListener('input', persistDraft);
        }

        formNode.addEventListener('submit', () => {
            if (storageKey) {
                removeLocalStorageValue(storageKey);
            }
        });
    });
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

        if (data.type === 'POST_SYNC_AUTH_RETRY') {
            showSyncNotice('Sicherheits-Token aktualisiert. Offline-Beitrag wird erneut gesendet.', 'warning');
            return;
        }

        if (data.type === 'POST_SYNC_AUTH_REQUIRED') {
            const retryHint = formatRetryHint(data.payload?.nextRetryAt);
            showSyncNotice(`Offline-Queue pausiert (Anmeldung/CSRF). Nächster Versuch ${retryHint}.`, 'warning');
            return;
        }

        if (data.type === 'POST_SYNC_RETRY_SCHEDULED') {
            const status = Number(data.payload?.status || 0);

            if (status === 401 || status === 419) {
                return;
            }

            const retryHint = formatRetryHint(data.payload?.nextRetryAt);

            if (status === 429) {
                showSyncNotice(`Server-Limit erreicht. Nächster Sync-Versuch ${retryHint}.`, 'warning');
                return;
            }

            showSyncNotice(`Synchronisierung pausiert. Nächster Versuch ${retryHint}.`, 'warning');
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

    const subscribeUrl = root.dataset.subscribeUrl || '';
    const unsubscribeUrl = root.dataset.unsubscribeUrl || '';
    const vapidPublicKey = root.dataset.vapidPublicKey || '';

    if (!subscribeUrl || !unsubscribeUrl) {
        return;
    }

    browserNotificationConfig = {
        subscribeUrl,
        unsubscribeUrl,
        vapidPublicKey,
        worldSlug: root.dataset.worldSlug || 'chroniken-der-asche',
        appName: root.dataset.appName || 'C76-RPG',
        enabledKinds: normalizeBrowserNotificationKinds(root.dataset.enabledKinds),
        csrfToken: getCsrfToken(),
        statusNode: document.querySelector(BROWSER_NOTIFICATION_STATUS_SELECTOR),
        enableButton: document.querySelector(BROWSER_NOTIFICATION_ENABLE_SELECTOR),
    };

    if (browserNotificationConfig.enableButton instanceof HTMLButtonElement) {
        browserNotificationConfig.enableButton.addEventListener('click', async () => {
            await requestBrowserNotificationPermission();
        });
    }

    window.addEventListener('online', () => {
        if (isBrowserPushReady()) {
            void syncBrowserNotificationSubscriptionState();
        }
    });

    updateBrowserNotificationStatus();
    void syncBrowserNotificationSubscriptionState();
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
    const supportsNotifications = supportsWebPush();
    let statusMessage = 'Browser-Benachrichtigungen werden geprüft.';
    let disableEnableButton = false;
    let enableButtonLabel = 'Browser-Push aktivieren';

    if (!hasBrowserKinds) {
        statusMessage = 'Browser-Push ist in den Mitteilungs-Präferenzen aktuell deaktiviert.';
        disableEnableButton = true;
    } else if (!supportsNotifications) {
        statusMessage = 'Dieser Browser unterstützt Web Push nicht vollständig.';
        disableEnableButton = true;
    } else if (!browserNotificationConfig.vapidPublicKey.trim()) {
        statusMessage = 'Web-Push ist serverseitig nicht konfiguriert (VAPID-Key fehlt).';
        disableEnableButton = true;
    } else if (Notification.permission === 'granted') {
        statusMessage = 'Browser-Push ist aktiv und mit diesem Geraet verknuepft.';
        disableEnableButton = true;
        enableButtonLabel = 'Browser-Push aktiv';
    } else if (Notification.permission === 'denied') {
        statusMessage = 'Browser-Push wurde blockiert. Erlaube Notifications in deinen Browser-Einstellungen.';
        disableEnableButton = true;
        enableButtonLabel = 'Browser-Push blockiert';
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

    if (!supportsWebPush()) {
        showSyncNotice('Browser-Benachrichtigungen werden auf diesem Geraet nicht unterstuetzt.', 'warning');
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
        await syncBrowserNotificationSubscriptionState();
        updateBrowserNotificationStatus();
        return;
    }

    try {
        const permission = await Notification.requestPermission();

        if (permission === 'granted') {
            showSyncNotice('Browser-Push wurde aktiviert.', 'success');
            await syncBrowserNotificationSubscriptionState();
        } else {
            showSyncNotice('Browser-Push wurde nicht freigegeben.', 'warning');
        }
    } catch (error) {
        console.error('Notification permission request failed:', error);
        showSyncNotice('Browser-Permission konnte nicht angefragt werden.', 'error');
    }

    updateBrowserNotificationStatus();
}

function supportsWebPush() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

function isBrowserPushReady() {
    return (
        Boolean(browserNotificationConfig) &&
        browserNotificationConfig.enabledKinds.length > 0 &&
        supportsWebPush() &&
        browserNotificationConfig.vapidPublicKey.trim() !== '' &&
        Notification.permission === 'granted'
    );
}

async function syncBrowserNotificationSubscriptionState() {
    if (!browserNotificationConfig || !supportsWebPush()) {
        return;
    }

    const registration = await getActiveServiceWorkerRegistration();

    if (!registration || !registration.pushManager) {
        return;
    }

    const currentSubscription = await registration.pushManager.getSubscription().catch(() => null);

    if (!isBrowserPushReady()) {
        if (currentSubscription) {
            await unsubscribeBrowserPush(currentSubscription, true);
        }
        updateBrowserNotificationStatus();
        return;
    }

    if (currentSubscription) {
        await syncBrowserPushSubscriptionWithServer(currentSubscription);
        updateBrowserNotificationStatus();
        return;
    }

    try {
        const newSubscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(browserNotificationConfig.vapidPublicKey),
        });

        await syncBrowserPushSubscriptionWithServer(newSubscription);
        showSyncNotice('Browser-Push wurde aktiviert.', 'success');
    } catch (error) {
        console.error('Browser push subscribe failed:', error);
        showSyncNotice('Browser-Push konnte nicht aktiviert werden.', 'error');
    }

    updateBrowserNotificationStatus();
}

async function unsubscribeBrowserPush(subscription, silent = false) {
    if (!browserNotificationConfig) {
        return;
    }

    const endpoint = typeof subscription?.endpoint === 'string' ? subscription.endpoint : '';

    try {
        await subscription.unsubscribe();
    } catch (error) {
        console.error('Browser push unsubscribe failed:', error);
    }

    if (endpoint !== '') {
        try {
            await postJson(browserNotificationConfig.unsubscribeUrl, {
                world_slug: resolveBrowserNotificationWorldSlug(),
                endpoint,
            });
        } catch (error) {
            console.error('Browser push unsubscribe sync failed:', error);
        }
    }

    if (!silent) {
        showSyncNotice('Browser-Push wurde deaktiviert.', 'warning');
    }
}

async function syncBrowserPushSubscriptionWithServer(subscription) {
    if (!browserNotificationConfig) {
        return;
    }

    const payload = normalizePushSubscriptionPayload(subscription);

    if (!payload) {
        throw new Error('Push subscription payload is invalid.');
    }

    await postJson(browserNotificationConfig.subscribeUrl, {
        world_slug: resolveBrowserNotificationWorldSlug(),
        endpoint: payload.endpoint,
        public_key: payload.publicKey,
        auth_token: payload.authToken,
        content_encoding: payload.contentEncoding,
    });
}

function normalizePushSubscriptionPayload(subscription) {
    if (!subscription || typeof subscription.toJSON !== 'function') {
        return null;
    }

    const json = subscription.toJSON();
    const endpoint = typeof json?.endpoint === 'string' ? json.endpoint : '';
    const publicKey = typeof json?.keys?.p256dh === 'string' ? json.keys.p256dh : '';
    const authToken = typeof json?.keys?.auth === 'string' ? json.keys.auth : '';
    const contentEncoding =
        typeof json?.contentEncoding === 'string' && json.contentEncoding.trim() !== ''
            ? json.contentEncoding.trim()
            : 'aes128gcm';

    if (!endpoint || !publicKey || !authToken) {
        return null;
    }

    return {
        endpoint,
        publicKey,
        authToken,
        contentEncoding,
    };
}

function resolveBrowserNotificationWorldSlug() {
    if (!browserNotificationConfig) {
        return 'chroniken-der-asche';
    }

    const fromPath = window.location.pathname.match(/^\/w\/([^/]+)/);
    if (fromPath && fromPath[1]) {
        return decodeURIComponent(fromPath[1]);
    }

    if (typeof browserNotificationConfig.worldSlug === 'string' && browserNotificationConfig.worldSlug.trim() !== '') {
        return browserNotificationConfig.worldSlug.trim();
    }

    return 'chroniken-der-asche';
}

function getCsrfToken() {
    const tokenNode = document.querySelector('meta[name="csrf-token"]');

    if (!(tokenNode instanceof HTMLMetaElement)) {
        return '';
    }

    return tokenNode.content || '';
}

function readLocalStorageValue(key) {
    if (!key) {
        return null;
    }

    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

function writeLocalStorageValue(key, value) {
    if (!key) {
        return;
    }

    try {
        window.localStorage.setItem(key, value);
    } catch {
        // Ignore storage write errors.
    }
}

function removeLocalStorageValue(key) {
    if (!key) {
        return;
    }

    try {
        window.localStorage.removeItem(key);
    } catch {
        // Ignore storage remove errors.
    }
}

function debounce(callback, waitMs) {
    let timeoutId = null;

    return (...args) => {
        if (timeoutId) {
            window.clearTimeout(timeoutId);
        }

        timeoutId = window.setTimeout(() => {
            callback(...args);
        }, waitMs);
    };
}

async function postJson(url, payload) {
    const headers = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (browserNotificationConfig?.csrfToken) {
        headers['X-CSRF-TOKEN'] = browserNotificationConfig.csrfToken;
    }

    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers,
        body: JSON.stringify(payload),
    });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status} for ${url}`);
    }

    return response;
}

function urlBase64ToUint8Array(base64String) {
    const normalizedBase64 = base64String.replace(/-/g, '+').replace(/_/g, '/');
    const padding = '='.repeat((4 - (normalizedBase64.length % 4)) % 4);
    const decoded = window.atob(normalizedBase64 + padding);
    const output = new Uint8Array(decoded.length);

    for (let index = 0; index < decoded.length; index += 1) {
        output[index] = decoded.charCodeAt(index);
    }

    return output;
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
            /^\/w\/[^/]+\/campaigns\/[^/]+\/scenes\/[^/]+\/?$/.test(parsed.pathname) ||
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
        source_url: `${window.location.pathname}${window.location.search}`,
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

function formatRetryHint(nextRetryAt) {
    const retryTimestampMs = Date.parse(typeof nextRetryAt === 'string' ? nextRetryAt : '');

    if (!Number.isFinite(retryTimestampMs)) {
        return 'später';
    }

    const remainingSeconds = Math.max(1, Math.round((retryTimestampMs - Date.now()) / 1000));

    if (remainingSeconds < 60) {
        return `in ${remainingSeconds}s`;
    }

    const remainingMinutes = Math.ceil(remainingSeconds / 60);
    return `in ${remainingMinutes}min`;
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
