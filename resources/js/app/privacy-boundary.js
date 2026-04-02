import { readLocalStorageValue, writeLocalStorageValue } from '../immersion/utils';

const AUTH_USER_BOUNDARY_META_SELECTOR = 'meta[name="auth-user-id"]';
const AUTH_USER_BOUNDARY_STORAGE_KEY = 'c76:auth-user-boundary';
const PRIVATE_PAGE_CACHE_PREFIX = 'chroniken-pages-';
const PRIVATE_CONTENT_CACHE_PREFIX = 'chroniken-content-';
const OFFLINE_QUEUE_DB_NAME = 'chroniken-pbp';

export async function enforcePrivateDataBoundaryOnAuthChange({ postMessageToActiveServiceWorker } = {}) {
    const currentBoundary = resolveCurrentAuthBoundary();
    const previousBoundary = readLocalStorageValue(AUTH_USER_BOUNDARY_STORAGE_KEY);

    if (previousBoundary === currentBoundary) {
        return;
    }

    try {
        await clearPrivateOfflineData({ postMessageToActiveServiceWorker });
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

async function clearPrivateOfflineData({ postMessageToActiveServiceWorker } = {}) {
    const postMessageFn = typeof postMessageToActiveServiceWorker === 'function'
        ? postMessageToActiveServiceWorker
        : async () => undefined;

    await Promise.all([
        postMessageFn({
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
