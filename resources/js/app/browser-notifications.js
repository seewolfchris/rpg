import { showSyncNotice } from '../immersion/utils';

const BROWSER_NOTIFICATION_ROOT_SELECTOR = '[data-browser-notifications]';
const BROWSER_NOTIFICATION_STATUS_SELECTOR = '[data-browser-notifications-status]';
const BROWSER_NOTIFICATION_ENABLE_SELECTOR = '[data-browser-notifications-enable]';
const LOGOUT_FORM_SELECTOR = 'form[data-logout-form]';
const PUSH_DEVICE_OPT_OUT_KEY = 'c76:push-device-opt-out';

let browserNotificationConfig = null;

export function setupBrowserNotifications({
    getActiveServiceWorkerRegistration,
    ensureActiveServiceWorkerRegistration,
    resolveActiveWorldSlug,
    resolveStoredWorldSlugContext,
    defaultWorldSlug = 'default',
    getCsrfToken = () => '',
} = {}) {
    const resolveActiveWorldSlugFn = typeof resolveActiveWorldSlug === 'function'
        ? resolveActiveWorldSlug
        : () => '';
    const resolveStoredWorldSlugContextFn = typeof resolveStoredWorldSlugContext === 'function'
        ? resolveStoredWorldSlugContext
        : () => '';
    const resolveRegistrationFn = typeof getActiveServiceWorkerRegistration === 'function'
        ? getActiveServiceWorkerRegistration
        : async () => null;
    const ensureRegistrationFn = typeof ensureActiveServiceWorkerRegistration === 'function'
        ? ensureActiveServiceWorkerRegistration
        : resolveRegistrationFn;
    const fallbackWorldSlug = typeof defaultWorldSlug === 'string' && defaultWorldSlug.trim() !== ''
        ? defaultWorldSlug.trim()
        : 'default';

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
        resolveRegistrationFn,
        ensureRegistrationFn,
        resolveActiveWorldSlugFn,
        resolveStoredWorldSlugContextFn,
        fallbackWorldSlug,
        deviceOptedOut: readPushDeviceOptOut(),
    };

    if (browserNotificationConfig.enableButton instanceof HTMLButtonElement) {
        browserNotificationConfig.enableButton.addEventListener('click', async () => {
            await requestBrowserNotificationPermission();
        });
    }

    setupBrowserNotificationLogoutCleanup();

    window.addEventListener('online', () => {
        if (isBrowserPushReady()) {
            void syncBrowserNotificationSubscriptionState();
        }
    });

    updateBrowserNotificationStatus();
    void syncBrowserNotificationSubscriptionState();
}

function setupBrowserNotificationLogoutCleanup() {
    document.querySelectorAll(LOGOUT_FORM_SELECTOR).forEach((form) => {
        if (!(form instanceof HTMLFormElement) || form.dataset.pushLogoutCleanupBound === '1') {
            return;
        }

        form.dataset.pushLogoutCleanupBound = '1';
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            try {
                const registration = await browserNotificationConfig?.resolveRegistrationFn?.();
                const subscription = registration?.pushManager && typeof registration.pushManager.getSubscription === 'function'
                    ? await registration.pushManager.getSubscription().catch(() => null)
                    : null;

                if (subscription) {
                    await unsubscribeBrowserPush(subscription, true);
                }
            } catch (error) {
                console.error('Browser push logout cleanup failed:', error);
            } finally {
                HTMLFormElement.prototype.submit.call(form);
            }
        });
    });
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
    } else if (browserNotificationConfig.deviceOptedOut) {
        statusMessage = 'Dieses Gerät ist nicht mehr mit Browser-Push verknüpft.';
        enableButtonLabel = 'Gerät erneut verknüpfen';
    } else if (!supportsNotifications) {
        statusMessage = 'Dieser Browser unterstützt Web Push nicht vollständig.';
        disableEnableButton = true;
    } else if (!browserNotificationConfig.vapidPublicKey.trim()) {
        statusMessage = 'Web-Push ist serverseitig nicht konfiguriert (VAPID-Key fehlt).';
        disableEnableButton = true;
    } else if (Notification.permission === 'granted') {
        statusMessage = 'Browser-Push ist aktiv und mit diesem Gerät verknüpft.';
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

    setPushDeviceOptOut(false);

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
        !browserNotificationConfig.deviceOptedOut &&
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

    let registration = await browserNotificationConfig.resolveRegistrationFn();

    if (!registration && isBrowserPushReady()) {
        registration = await browserNotificationConfig.ensureRegistrationFn();
    }

    if (!registration || !registration.pushManager) {
        return;
    }

    const currentSubscription = await registration.pushManager.getSubscription().catch(() => null);
    await bindPushDeviceControls(currentSubscription);

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

async function unsubscribeBrowserPush(
    subscription,
    silent = false,
    rememberDeviceOptOut = false,
    syncServer = true,
) {
    if (!browserNotificationConfig) {
        return;
    }

    const endpoint = typeof subscription?.endpoint === 'string' ? subscription.endpoint : '';
    const payload = normalizePushSubscriptionPayload(subscription);

    try {
        await subscription.unsubscribe();
    } catch (error) {
        console.error('Browser push unsubscribe failed:', error);
    }

    if (endpoint !== '' && syncServer) {
        try {
            await postJson(browserNotificationConfig.unsubscribeUrl, {
                world_slug: resolveBrowserNotificationWorldSlug(),
                endpoint,
                public_key: payload?.publicKey || null,
                auth_token: payload?.authToken || null,
            });
        } catch (error) {
            console.error('Browser push unsubscribe sync failed:', error);
        }
    }

    if (rememberDeviceOptOut) {
        setPushDeviceOptOut(true);
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
        device_name: resolvePushDeviceName(),
    });
}

function resolvePushDeviceName() {
    const userAgent = String(navigator.userAgent || '').toLowerCase();
    const browser = userAgent.includes('firefox/')
        ? 'Firefox'
        : (userAgent.includes('edg/') ? 'Edge' : (userAgent.includes('chrome/') ? 'Chrome' : (userAgent.includes('safari/') ? 'Safari' : 'Browser')));
    const device = /android|iphone|ipad|mobile/.test(userAgent) ? 'Mobilgerät' : 'Computer';

    return `${browser} auf ${device}`;
}

async function bindPushDeviceControls(subscription) {
    if (!subscription || typeof subscription.endpoint !== 'string' || subscription.endpoint === '') {
        return;
    }

    const endpointHash = await sha256Hex(subscription.endpoint);

    if (endpointHash === '') {
        return;
    }

    document.querySelectorAll('[data-push-device]').forEach((deviceNode) => {
        if (!(deviceNode instanceof HTMLElement) || deviceNode.dataset.endpointHash !== endpointHash) {
            return;
        }

        const badge = deviceNode.querySelector('[data-push-current-badge]');
        if (badge instanceof HTMLElement) {
            badge.classList.remove('hidden');
        }

        const removeForm = deviceNode.querySelector('[data-push-device-remove-form]');
        if (!(removeForm instanceof HTMLFormElement) || removeForm.dataset.pushCurrentBound === '1') {
            return;
        }

        removeForm.dataset.pushCurrentBound = '1';
        removeForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            await unsubscribeBrowserPush(subscription, false, true, false);
            HTMLFormElement.prototype.submit.call(removeForm);
        });
    });

    const removeAllForm = document.querySelector('[data-push-device-remove-all-form]');
    if (removeAllForm instanceof HTMLFormElement && removeAllForm.dataset.pushCurrentBound !== '1') {
        removeAllForm.dataset.pushCurrentBound = '1';
        removeAllForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            await unsubscribeBrowserPush(subscription, true, true, false);
            HTMLFormElement.prototype.submit.call(removeAllForm);
        });
    }
}

function readPushDeviceOptOut() {
    try {
        return window.localStorage.getItem(PUSH_DEVICE_OPT_OUT_KEY) === '1';
    } catch {
        return false;
    }
}

function setPushDeviceOptOut(optedOut) {
    if (browserNotificationConfig) {
        browserNotificationConfig.deviceOptedOut = Boolean(optedOut);
    }

    try {
        if (optedOut) {
            window.localStorage.setItem(PUSH_DEVICE_OPT_OUT_KEY, '1');
            return;
        }

        window.localStorage.removeItem(PUSH_DEVICE_OPT_OUT_KEY);
    } catch {
        // Keep browser push usable when local storage is unavailable.
    }
}

async function sha256Hex(value) {
    if (!window.crypto?.subtle || typeof TextEncoder !== 'function') {
        return '';
    }

    try {
        const digest = await window.crypto.subtle.digest('SHA-256', new TextEncoder().encode(value));

        return Array.from(new Uint8Array(digest))
            .map((byte) => byte.toString(16).padStart(2, '0'))
            .join('');
    } catch {
        return '';
    }
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

    const activeWorld = browserNotificationConfig?.resolveActiveWorldSlugFn?.() || '';
    if (activeWorld !== '') {
        return activeWorld;
    }

    const storedWorld = browserNotificationConfig?.resolveStoredWorldSlugContextFn?.() || '';
    if (storedWorld !== '') {
        return storedWorld;
    }

    return browserNotificationConfig?.fallbackWorldSlug || 'default';
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
