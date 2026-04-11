const CACHEABLE_LINK_SELECTOR = 'a[href*="/campaigns/"][href*="/scenes/"], a[href*="/characters/"]';
const LOGOUT_FORM_SELECTOR = 'form[data-logout-form]';

export function createServiceWorkerRuntime({
    resolveActiveWorldSlug,
    resolveStoredWorldSlugContext,
    defaultWorldSlug,
    resolveOfflineQueueEnabled,
} = {}) {
    const resolveActiveWorldSlugFn = typeof resolveActiveWorldSlug === 'function'
        ? resolveActiveWorldSlug
        : () => '';
    const resolveStoredWorldSlugContextFn = typeof resolveStoredWorldSlugContext === 'function'
        ? resolveStoredWorldSlugContext
        : () => '';
    const fallbackWorldSlug = typeof defaultWorldSlug === 'string' && defaultWorldSlug.trim() !== ''
        ? defaultWorldSlug.trim()
        : 'default';
    const resolveOfflineQueueEnabledFn = typeof resolveOfflineQueueEnabled === 'function'
        ? resolveOfflineQueueEnabled
        : () => true;

    let swRegistration = null;

    async function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            return null;
        }

        try {
            const versionTag = encodeURIComponent(resolveServiceWorkerVersionTag());
            const worldSlug = encodeURIComponent(
                resolveActiveWorldSlugFn()
                || resolveStoredWorldSlugContextFn()
                || fallbackWorldSlug
            );
            const offlineQueue = resolveOfflineQueueEnabledFn() ? '1' : '0';
            const registration = await navigator.serviceWorker.register(`/sw.js?v=${versionTag}&world=${worldSlug}&offline_queue=${offlineQueue}`);
            const readyRegistration = await navigator.serviceWorker.ready.catch(() => null);

            if (readyRegistration) {
                swRegistration = readyRegistration;
                await syncOfflineQueuePreference(resolveOfflineQueueEnabledFn());
                return readyRegistration;
            }

            swRegistration = registration;
            await syncOfflineQueuePreference(resolveOfflineQueueEnabledFn());
            return registration;
        } catch (error) {
            console.error('Service worker registration failed:', error);

            return null;
        }
    }

    async function warmOfflineReadingCache() {
        if (!('serviceWorker' in navigator)) {
            return;
        }

        if (!resolveOfflineQueueEnabledFn()) {
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

    async function syncOfflineQueuePreference(enabledOverride = null) {
        const enabled = typeof enabledOverride === 'boolean'
            ? enabledOverride
            : resolveOfflineQueueEnabledFn();

        await postMessageToActiveServiceWorker({
            type: 'SET_OFFLINE_QUEUE_PREFERENCE',
            enabled,
        });

        if (!enabled) {
            await postMessageToActiveServiceWorker({
                type: 'CLEAR_PRIVATE_DATA',
            });
        }
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

    return {
        registerServiceWorker,
        warmOfflineReadingCache,
        getActiveServiceWorkerRegistration,
        postMessageToActiveServiceWorker,
        syncOfflineQueuePreference,
        setupServiceWorkerLogoutCleanup,
    };
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
