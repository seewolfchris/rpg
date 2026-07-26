import { readLocalStorageValue, removeLocalStorageValue, writeLocalStorageValue } from '../immersion/utils.js';

const AUTH_USER_BOUNDARY_META_SELECTOR = 'meta[name="auth-user-id"]';
const AUTH_SESSION_BOUNDARY_META_SELECTOR = 'meta[name="auth-session-boundary"]';
const AUTH_USER_BOUNDARY_STORAGE_KEY = 'c76:auth-user-boundary';
const AUTH_USER_BOUNDARY_PENDING_STORAGE_KEY = 'c76:auth-user-boundary-pending';
const MANAGED_STATIC_CACHE_PREFIX = 'chroniken-static-';
const PRIVATE_PAGE_CACHE_PREFIX = 'chroniken-pages-';
const PRIVATE_CONTENT_CACHE_PREFIX = 'chroniken-content-';
const OFFLINE_QUEUE_DB_NAME = 'chroniken-pbp';
const PRIVATE_DATA_BOUNDARY_FAILURE_SELECTOR = '[data-private-data-boundary-failure]';
const PRIVATE_BROWSER_STORAGE_PREFIXES = [
    'c76:post-draft:',
    'c76:scene-ooc-open:',
    'c76:scene-reading-mode:',
];
const PRIVATE_BROWSER_STORAGE_KEYS = new Set([
    'c76:last-world-slug',
]);

export function renderPrivateDataBoundaryFailure() {
    const mainContent = document.querySelector('#app-main');

    if (!(mainContent instanceof HTMLElement)) {
        return null;
    }

    const existingFailure = mainContent.querySelector(PRIVATE_DATA_BOUNDARY_FAILURE_SELECTOR);

    if (existingFailure instanceof HTMLElement) {
        existingFailure.focus();

        return existingFailure;
    }

    const failure = document.createElement('section');
    failure.dataset.privateDataBoundaryFailure = 'true';
    failure.className = 'mb-6 rounded-xl border border-red-500/70 bg-red-950/35 p-4 text-red-100';
    failure.setAttribute('role', 'alert');
    failure.setAttribute('aria-live', 'assertive');
    failure.setAttribute('tabindex', '-1');

    const heading = document.createElement('h2');
    heading.className = 'font-heading text-lg';
    heading.textContent = 'Offline-Schutz konnte nicht aktiviert werden';

    const explanation = document.createElement('p');
    explanation.className = 'mt-2 text-sm';
    explanation.textContent = 'Private Offline-Daten konnten nicht sicher bereinigt werden. Offline-Funktionen bleiben deaktiviert. Lösche die Website-Daten in deinem Browser und lade die Seite neu.';

    failure.append(heading, explanation);
    mainContent.prepend(failure);
    failure.focus();

    return failure;
}

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

export async function clearPrivateOfflineData({ postMessageToActiveServiceWorker } = {}) {
    const postMessageFn = typeof postMessageToActiveServiceWorker === 'function'
        ? postMessageToActiveServiceWorker
        : async () => undefined;

    const browserStorageCleared = clearPrivateClientStorage();
    const [cachesCleared, queueDatabaseCleared] = await Promise.all([
        clearPrivateOfflineCaches(),
        clearPrivateOfflineQueueDatabase(),
    ]);

    await postMessageFn({
        type: 'CLEAR_PRIVATE_DATA',
    }).catch(() => undefined);

    return browserStorageCleared && cachesCleared && queueDatabaseCleared;
}

export function clearPrivateClientStorage() {
    try {
        return [
            window.localStorage,
            window.sessionStorage,
        ].every((storage) => clearPrivateStorageKeys(storage));
    } catch {
        return false;
    }
}

function clearPrivateStorageKeys(storage) {
    try {
        const keysToRemove = [];

        for (let index = 0; index < storage.length; index += 1) {
            const key = storage.key(index);

            if (
                typeof key === 'string'
                && (
                    PRIVATE_BROWSER_STORAGE_KEYS.has(key)
                    || PRIVATE_BROWSER_STORAGE_PREFIXES.some((prefix) => key.startsWith(prefix))
                )
            ) {
                keysToRemove.push(key);
            }
        }

        keysToRemove.forEach((key) => storage.removeItem(key));

        return true;
    } catch {
        return false;
    }
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
            cacheKey.startsWith(MANAGED_STATIC_CACHE_PREFIX)
            || cacheKey.startsWith(PRIVATE_PAGE_CACHE_PREFIX)
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
