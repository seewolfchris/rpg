import './bootstrap';
import { characterSheetForm, registerCharacterSheetComponent } from './character-sheet';
import { setupSceneThreadReadingMode } from './immersion/reading-mode';
import { createQueueModule } from './immersion/queue';
import {
    debounce,
    readLocalStorageValue,
    removeLocalStorageValue,
    showSyncNotice,
    writeLocalStorageValue,
} from './immersion/utils';
import {
    createPostDraftStorageKey,
    hasPostDraftContent,
    normalizePostDraftForRestore,
    parsePostDraftPayload,
} from './post-editor-draft';

const CACHEABLE_LINK_SELECTOR = 'a[href*="/campaigns/"][href*="/scenes/"], a[href*="/characters/"]';
const PWA_INSTALL_BUTTON_SELECTOR = '[data-pwa-install-button]';
const BROWSER_NOTIFICATION_ROOT_SELECTOR = '[data-browser-notifications]';
const BROWSER_NOTIFICATION_STATUS_SELECTOR = '[data-browser-notifications-status]';
const BROWSER_NOTIFICATION_ENABLE_SELECTOR = '[data-browser-notifications-enable]';
const PARALLAX_SCENE_SELECTOR = '[data-parallax-scene]';
const LOGOUT_FORM_SELECTOR = 'form[data-logout-form]';
const POST_EDITOR_SELECTOR = 'form[data-post-editor]';
const AUTH_USER_BOUNDARY_META_SELECTOR = 'meta[name="auth-user-id"]';
const AUTH_USER_BOUNDARY_STORAGE_KEY = 'c76:auth-user-boundary';
const PRIVATE_PAGE_CACHE_PREFIX = 'chroniken-pages-';
const PRIVATE_CONTENT_CACHE_PREFIX = 'chroniken-content-';
const OFFLINE_QUEUE_DB_NAME = 'chroniken-pbp';
const DEFAULT_WORLD_SLUG = 'default';
const POST_PREVIEW_DEBOUNCE_MS = 450;
const POST_DRAFT_DEBOUNCE_MS = 350;

let swRegistration = null;
let deferredInstallPrompt = null;
let browserNotificationConfig = null;
const {
    setupOfflinePostQueue,
    setupOnlineSyncTrigger,
    setupServiceWorkerMessageHandling,
    renderDeadLetterPanel,
    renderOfflineQueueStatusPanel,
    triggerQueuedPostSync,
} = createQueueModule({
    getActiveServiceWorkerRegistration,
});

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

    persistActiveWorldSlugContext();
    setupSceneThreadReadingMode();
    setupAtmosphericParallax();
    setupPostEditorEnhancements();
    setupOfflinePostQueue();
    void renderDeadLetterPanel();
    void renderOfflineQueueStatusPanel();
});

const bootApplication = async () => {
    persistActiveWorldSlugContext();
    setupSceneThreadReadingMode();
    setupAtmosphericParallax();
    setupPostEditorEnhancements();
    setupPwaInstallPrompt();
    setupOfflinePostQueue();
    setupOnlineSyncTrigger();
    setupServiceWorkerMessageHandling();
    setupServiceWorkerLogoutCleanup();
    await enforcePrivateDataBoundaryOnAuthChange();
    await renderDeadLetterPanel();
    await renderOfflineQueueStatusPanel();

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

function setupAtmosphericParallax() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    if (window.matchMedia('(pointer: coarse)').matches || window.innerWidth < 768) {
        return;
    }

    document.querySelectorAll(PARALLAX_SCENE_SELECTOR).forEach((scene) => {
        if (!(scene instanceof HTMLElement) || scene.dataset.parallaxBound === '1') {
            return;
        }

        scene.dataset.parallaxBound = '1';
        const layers = Array.from(scene.querySelectorAll('[data-parallax-layer]'))
            .filter((node) => node instanceof HTMLElement);

        if (layers.length === 0) {
            return;
        }

        scene.addEventListener('pointermove', (event) => {
            const rect = scene.getBoundingClientRect();
            const x = ((event.clientX - rect.left) / rect.width) - 0.5;
            const y = ((event.clientY - rect.top) / rect.height) - 0.5;

            layers.forEach((layer) => {
                const depth = Number(layer.dataset.parallaxDepth || '0.02');
                const translateX = x * depth * 22;
                const translateY = y * depth * 28;
                layer.style.transform = `translate3d(${translateX}px, ${translateY}px, 0)`;
            });
        });

        scene.addEventListener('pointerleave', () => {
            layers.forEach((layer) => {
                layer.style.transform = 'translate3d(0, 0, 0)';
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
        const versionTag = encodeURIComponent(resolveServiceWorkerVersionTag());
        const worldSlug = encodeURIComponent(
            resolveActiveWorldSlug()
            || resolveStoredWorldSlugContext()
            || DEFAULT_WORLD_SLUG
        );
        const registration = await navigator.serviceWorker.register(`/sw.js?v=${versionTag}&world=${worldSlug}`);
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

function resolveServiceWorkerVersionTag() {
    const swVersionNode = document.querySelector('meta[name="sw-version"]');

    if (swVersionNode instanceof HTMLMetaElement && swVersionNode.content.trim() !== '') {
        return swVersionNode.content.trim();
    }

    const appVersionNode = document.querySelector('meta[name="application-version"]');

    if (appVersionNode instanceof HTMLMetaElement && appVersionNode.content.trim() !== '') {
        return appVersionNode.content.trim();
    }

    return 'dev';
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
        worldSlug: String(root.dataset.worldSlug || '').trim(),
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
    const fromPath = window.location.pathname.match(/^\/w\/([^/]+)/);
    if (fromPath && fromPath[1]) {
        return decodeURIComponent(fromPath[1]);
    }

    if (browserNotificationConfig && browserNotificationConfig.worldSlug.trim() !== '') {
        return browserNotificationConfig.worldSlug.trim();
    }

    const activeWorld = resolveActiveWorldSlug();
    if (activeWorld !== '') {
        return activeWorld;
    }

    const storedWorld = resolveStoredWorldSlugContext();
    if (storedWorld !== '') {
        return storedWorld;
    }

    return DEFAULT_WORLD_SLUG;
}

function getCsrfToken() {
    const tokenNode = document.querySelector('meta[name="csrf-token"]');

    if (!(tokenNode instanceof HTMLMetaElement)) {
        return '';
    }

    return tokenNode.content || '';
}

function persistActiveWorldSlugContext() {
    const worldSlug = resolveActiveWorldSlug();

    if (worldSlug === '') {
        return;
    }

    writeLocalStorageValue('c76:last-world-slug', worldSlug);
}

function resolveStoredWorldSlugContext() {
    const storedValue = readLocalStorageValue('c76:last-world-slug');
    const normalized = normalizeWorldSlug(storedValue);

    return normalized ?? '';
}

function resolveActiveWorldSlug() {
    const htmlSlug = document.documentElement?.dataset?.worldSlug || '';
    const bodySlug = document.body?.dataset?.worldSlug || '';
    const pathMatch = window.location.pathname.match(/^\/w\/([^/]+)/);
    let fromPath = '';

    if (pathMatch && pathMatch[1]) {
        try {
            fromPath = decodeURIComponent(pathMatch[1]);
        } catch {
            fromPath = pathMatch[1];
        }
    }

    return normalizeWorldSlug(htmlSlug || bodySlug || fromPath || '') ?? '';
}

function normalizeWorldSlug(value) {
    if (typeof value !== 'string') {
        return null;
    }

    const slug = value.trim().toLowerCase();

    if (slug === '' || /^[a-z0-9-]+$/.test(slug) !== true) {
        return null;
    }

    return slug;
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

    await postMessageToActiveServiceWorker({
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

function setupServiceWorkerLogoutCleanup() {
    document.querySelectorAll(LOGOUT_FORM_SELECTOR).forEach((form) => {
        if (!(form instanceof HTMLFormElement) || form.dataset.swLogoutCleanupBound === '1') {
            return;
        }

        form.dataset.swLogoutCleanupBound = '1';

        form.addEventListener('submit', () => {
            void postMessageToActiveServiceWorker({
                type: 'CLEAR_PRIVATE_DATA',
            });
        }, { once: true });
    });
}

async function enforcePrivateDataBoundaryOnAuthChange() {
    const currentBoundary = resolveCurrentAuthBoundary();
    const previousBoundary = readLocalStorageValue(AUTH_USER_BOUNDARY_STORAGE_KEY);

    if (previousBoundary === currentBoundary) {
        return;
    }

    try {
        await clearPrivateOfflineData();
    } finally {
        writeLocalStorageValue(AUTH_USER_BOUNDARY_STORAGE_KEY, currentBoundary);
    }
}

function resolveCurrentAuthBoundary() {
    const boundaryMeta = document.querySelector(AUTH_USER_BOUNDARY_META_SELECTOR);

    if (!(boundaryMeta instanceof HTMLMetaElement)) {
        return 'guest';
    }

    const value = String(boundaryMeta.content || '').trim();

    return value !== '' ? value : 'guest';
}

async function clearPrivateOfflineData() {
    await Promise.all([
        postMessageToActiveServiceWorker({
            type: 'CLEAR_PRIVATE_DATA',
        }),
        clearPrivateOfflineCaches(),
        clearPrivateOfflineQueueDatabase(),
    ]);
}

async function clearPrivateOfflineCaches() {
    if (!('caches' in window)) {
        return;
    }

    let cacheKeys = [];

    try {
        cacheKeys = await window.caches.keys();
    } catch {
        return;
    }

    const privateCacheKeys = cacheKeys.filter((cacheKey) => (
        typeof cacheKey === 'string'
        && (
            cacheKey.startsWith(PRIVATE_PAGE_CACHE_PREFIX)
            || cacheKey.startsWith(PRIVATE_CONTENT_CACHE_PREFIX)
        )
    ));

    if (privateCacheKeys.length === 0) {
        return;
    }

    await Promise.all(privateCacheKeys.map(async (cacheKey) => {
        try {
            await window.caches.delete(cacheKey);
        } catch {
            // Ignore cache clear failures in privacy mode / unsupported browser contexts.
        }
    }));
}

async function clearPrivateOfflineQueueDatabase() {
    if (typeof window.indexedDB === 'undefined' || typeof window.indexedDB.deleteDatabase !== 'function') {
        return;
    }

    await new Promise((resolve) => {
        let request;

        try {
            request = window.indexedDB.deleteDatabase(OFFLINE_QUEUE_DB_NAME);
        } catch {
            resolve(undefined);
            return;
        }

        request.onsuccess = () => resolve(undefined);
        request.onerror = () => resolve(undefined);
        request.onblocked = () => resolve(undefined);
    });
}

async function postMessageToActiveServiceWorker(message) {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    const activeWorker =
        navigator.serviceWorker.controller ||
        (await getActiveServiceWorkerRegistration())?.active ||
        null;

    if (!activeWorker) {
        return;
    }

    activeWorker.postMessage(message);
}
