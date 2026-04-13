import { readLocalStorageValue, removeLocalStorageValue, writeLocalStorageValue } from '../immersion/utils.js';

const AUTH_USER_BOUNDARY_META_SELECTOR = 'meta[name="auth-user-id"]';
const AUTH_SESSION_BOUNDARY_META_SELECTOR = 'meta[name="auth-session-boundary"]';
const AUTH_USER_BOUNDARY_STORAGE_KEY = 'c76:auth-user-boundary';
const AUTH_USER_BOUNDARY_PENDING_STORAGE_KEY = 'c76:auth-user-boundary-pending';
const PRIVATE_PAGE_CACHE_PREFIX = 'chroniken-pages-';
const PRIVATE_CONTENT_CACHE_PREFIX = 'chroniken-content-';
const OFFLINE_QUEUE_DB_NAME = 'chroniken-pbp';

export async function enforcePrivateDataBoundaryOnAuthChange({ postMessageToActiveServiceWorker } = {}) {
    const currentBoundary = resolveCurrentAuthBoundary();
    const previousBoundary = readLocalStorageValue(AUTH_USER_BOUNDARY_STORAGE_KEY);
    const pendingBoundary = readLocalStorageValue(AUTH_USER_BOUNDARY_PENDING_STORAGE_KEY);

    if (previousBoundary === currentBoundary && pendingBoundary !== currentBoundary) {
        return true;
    }

    const cleanupCompleted = await clearPrivateOfflineData({ postMessageToActiveServiceWorker });

    if (!cleanupCompleted) {
        writeLocalStorageValue(AUTH_USER_BOUNDARY_PENDING_STORAGE_KEY, currentBoundary);

        return false;
    }

    writeLocalStorageValue(AUTH_USER_BOUNDARY_STORAGE_KEY, currentBoundary);
    removeLocalStorageValue(AUTH_USER_BOUNDARY_PENDING_STORAGE_KEY);

    return true;
}

function resolveCurrentAuthBoundary() {
    const userBoundary = resolveBoundaryMetaContent(AUTH_USER_BOUNDARY_META_SELECTOR, 'guest');
    const sessionBoundary = resolveBoundaryMetaContent(AUTH_SESSION_BOUNDARY_META_SELECTOR, 'session-unknown');

    return `${userBoundary}|${sessionBoundary}`;
}

function resolveBoundaryMetaContent(selector, fallbackValue) {
    const boundaryMeta = document.querySelector(selector);

    if (!(boundaryMeta instanceof HTMLMetaElement)) {
        return fallbackValue;
    }

    const value = String(boundaryMeta.content || '').trim();

    return value !== '' ? value : fallbackValue;
}

async function clearPrivateOfflineData({ postMessageToActiveServiceWorker } = {}) {
    const postMessageFn = typeof postMessageToActiveServiceWorker === 'function'
        ? postMessageToActiveServiceWorker
        : async () => undefined;

    const [cachesCleared, queueDatabaseCleared] = await Promise.all([
        clearPrivateOfflineCaches(),
        clearPrivateOfflineQueueDatabase(),
    ]);

    await postMessageFn({
        type: 'CLEAR_PRIVATE_DATA',
    }).catch(() => undefined);

    return cachesCleared && queueDatabaseCleared;
}

async function clearPrivateOfflineCaches() {
    if (!('caches' in window)) {
        return true;
    }

    let cacheKeys = [];

    try {
        cacheKeys = await window.caches.keys();
    } catch {
        return false;
    }

    const privateCacheKeys = cacheKeys.filter((cacheKey) => (
        typeof cacheKey === 'string'
        && (
            cacheKey.startsWith(PRIVATE_PAGE_CACHE_PREFIX)
            || cacheKey.startsWith(PRIVATE_CONTENT_CACHE_PREFIX)
        )
    ));

    if (privateCacheKeys.length === 0) {
        return true;
    }

    const deletionResults = await Promise.all(privateCacheKeys.map(async (cacheKey) => {
        try {
            await window.caches.delete(cacheKey);
            return true;
        } catch {
            // Ignore cache clear failures in privacy mode / unsupported browser contexts.
            return false;
        }
    }));

    return deletionResults.every((result) => result === true);
}

async function clearPrivateOfflineQueueDatabase() {
    if (typeof window.indexedDB === 'undefined' || typeof window.indexedDB.deleteDatabase !== 'function') {
        return true;
    }

    return new Promise((resolve) => {
        let request;

        try {
            request = window.indexedDB.deleteDatabase(OFFLINE_QUEUE_DB_NAME);
        } catch {
            resolve(false);
            return;
        }

        request.onsuccess = () => resolve(true);
        request.onerror = () => resolve(false);
        request.onblocked = () => resolve(false);
    });
}
