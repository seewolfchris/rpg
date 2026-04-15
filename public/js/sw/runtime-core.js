var CACHE_VERSION_TAG = resolveCacheVersionTag();
var AUTH_BOUNDARY_TAG = resolveAuthBoundaryTag();
var STATIC_CACHE = `chroniken-static-${CACHE_VERSION_TAG}`;
var PAGE_CACHE = `chroniken-pages-${CACHE_VERSION_TAG}-${AUTH_BOUNDARY_TAG}`;
var CONTENT_CACHE = `chroniken-content-${CACHE_VERSION_TAG}-${AUTH_BOUNDARY_TAG}`;
var QUEUE_DB_NAME = 'chroniken-pbp';
var QUEUE_STORE_NAME = 'postQueue';
var DEAD_LETTER_STORE_NAME = 'postDeadLetters';
var SYNC_TAG_POSTS = 'pbp-sync-posts';
var OFFLINE_URL = '/offline.html';
var DEFAULT_WORLD_SLUG = resolveDefaultWorldSlug();
var RETRY_MIN_DELAY_MS = 5 * 1000;
var RETRY_BASE_DELAY_MS = 30 * 1000;
var RETRY_MAX_DELAY_MS = 15 * 60 * 1000;
var RETRY_JITTER_RATIO = 0.2;
var MAX_SERVER_RETRIES = 5;
var OFFLINE_QUEUE_ENABLED = resolveOfflineQueuePreferenceFromQuery();

var activePostSyncPromise = null;

var STATIC_ASSETS = [
    '/',
    OFFLINE_URL,
    '/manifest.webmanifest',
    '/favicon.ico',
    '/favicon-16x16.png',
    '/favicon-32x32.png',
    '/images/hero-placeholder.svg',
    '/images/character-placeholder.svg',
    '/images/icons/apple-touch-icon.png',
    '/images/icons/icon-96.png',
    '/images/icons/icon-192.png',
    '/images/icons/icon-512.png',
    '/fonts/Goldman-Bold.woff2',
    '/fonts/Goldman-Regular.woff2',
    '/fonts/DMSerifText-Regular.woff2',
    '/fonts/DMMono-Regular.woff2',
    '/js/character-sheet.global.js',
    '/js/alpinejs-3.14.8.min.js',
];

function resolveCacheVersionTag() {
    try {
        const parsed = new URL(self.location.href);
        const rawVersion = parsed.searchParams.get('v') || 'dev';
        const normalizedVersion = rawVersion
            .trim()
            .replace(/[^A-Za-z0-9._-]+/g, '-')
            .replace(/^-+|-+$/g, '');

        return normalizedVersion !== '' ? normalizedVersion : 'dev';
    } catch {
        return 'dev';
    }
}

function resolveAuthBoundaryTag() {
    try {
        const parsed = new URL(self.location.href);
        const rawBoundary = parsed.searchParams.get('boundary') || 'guest-session-unknown';
        const normalizedBoundary = rawBoundary
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9._-]+/g, '-')
            .replace(/^-+|-+$/g, '');

        if (normalizedBoundary === '') {
            return 'guest-session-unknown';
        }

        return normalizedBoundary.slice(0, 96);
    } catch {
        return 'guest-session-unknown';
    }
}

function resolveDefaultWorldSlug() {
    try {
        const parsed = new URL(self.location.href);
        const rawWorld = parsed.searchParams.get('world') || 'default';
        const normalizedWorld = rawWorld
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9-]+/g, '-')
            .replace(/^-+|-+$/g, '');

        return normalizedWorld !== '' ? normalizedWorld : 'default';
    } catch {
        return 'default';
    }
}

function resolveOfflineQueuePreferenceFromQuery() {
    try {
        const parsed = new URL(self.location.href);
        const rawPreference = parsed.searchParams.get('offline_queue');

        if (rawPreference === null) {
            return true;
        }

        return normalizeOfflineQueuePreference(rawPreference, true);
    } catch {
        return true;
    }
}

function normalizeOfflineQueuePreference(value, fallbackValue = true) {
    if (typeof value === 'boolean') {
        return value;
    }

    if (typeof value === 'number') {
        return value !== 0;
    }

    if (typeof value === 'string') {
        const normalized = value.trim().toLowerCase();

        if (normalized === '1' || normalized === 'true' || normalized === 'on' || normalized === 'yes') {
            return true;
        }

        if (normalized === '0' || normalized === 'false' || normalized === 'off' || normalized === 'no') {
            return false;
        }
    }

    return Boolean(fallbackValue);
}

function isOfflineQueueEnabled() {
    return OFFLINE_QUEUE_ENABLED;
}

async function setOfflineQueuePreference(value) {
    OFFLINE_QUEUE_ENABLED = normalizeOfflineQueuePreference(value, true);

    if (!OFFLINE_QUEUE_ENABLED) {
        await clearPrivateSessionData();
    }
}

self.addEventListener('install', (event) => {
    event.waitUntil(
        (async () => {
            const cache = await caches.open(STATIC_CACHE);
            await Promise.all(
                STATIC_ASSETS.map(async (asset) => {
                    try {
                        await cache.add(asset);
                    } catch {
                        // Keep service worker install resilient if a single asset is missing.
                    }
                }),
            );
            await self.skipWaiting();
        })(),
    );
});

self.addEventListener('activate', (event) => {
    const allowedCaches = [STATIC_CACHE, PAGE_CACHE, CONTENT_CACHE];

    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) => isManagedCacheKey(key) && !allowedCaches.includes(key))
                    .map((key) => caches.delete(key))
            ))
            .then(async () => {
                if (!isOfflineQueueEnabled()) {
                    await clearPrivateSessionData();
                }
            })
            .then(() => self.clients.claim()),
    );
});

function isManagedCacheKey(key) {
    return (
        typeof key === 'string'
        && (
            key.startsWith('chroniken-static-')
            || key.startsWith('chroniken-pages-')
            || key.startsWith('chroniken-content-')
        )
    );
}

function isPrivateManagedCacheKey(key) {
    return (
        typeof key === 'string'
        && (
            key.startsWith('chroniken-pages-')
            || key.startsWith('chroniken-content-')
        )
    );
}

async function clearPrivateSessionData() {
    const [cachesCleared, queueDatabaseCleared] = await Promise.all([
        clearPrivateCaches(),
        clearQueueDatabase(),
    ]);

    const cleanupCompleted = cachesCleared && queueDatabaseCleared;

    if (!cleanupCompleted) {
        await notifyClients('PRIVATE_DATA_CLEAR_INCOMPLETE', {
            cachesCleared,
            queueDatabaseCleared,
        });
        return false;
    }

    activePostSyncPromise = null;

    await notifyClients('PRIVATE_DATA_CLEARED');

    return true;
}

async function clearPrivateCaches() {
    let cacheKeys = [];

    try {
        cacheKeys = await caches.keys();
    } catch {
        return false;
    }

    const privateCacheKeys = cacheKeys.filter((cacheKey) => isPrivateManagedCacheKey(cacheKey));

    if (privateCacheKeys.length === 0) {
        return true;
    }

    const deletionResults = await Promise.all(privateCacheKeys.map(async (cacheKey) => {
        try {
            return await caches.delete(cacheKey);
        } catch {
            return false;
        }
    }));

    return deletionResults.every((result) => result === true);
}

async function clearQueueDatabase() {
    if (typeof indexedDB === 'undefined' || typeof indexedDB.deleteDatabase !== 'function') {
        return true;
    }

    return new Promise((resolve) => {
        let request;

        try {
            request = indexedDB.deleteDatabase(QUEUE_DB_NAME);
        } catch {
            resolve(false);

            return;
        }

        request.onsuccess = () => resolve(true);
        request.onerror = () => resolve(false);
        request.onblocked = () => resolve(false);
    });
}

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const requestUrl = new URL(event.request.url);

    if (requestUrl.origin !== self.location.origin) {
        return;
    }

    if (!isOfflineQueueEnabled() && isOfflineReadablePath(requestUrl.pathname)) {
        event.respondWith(networkOnlyWithOfflineFallback(event.request));
        return;
    }

    if (event.request.mode === 'navigate') {
        if (isOfflineReadablePath(requestUrl.pathname)) {
            event.respondWith(networkFirst(event.request, PAGE_CACHE, true));
        }
        return;
    }

    if (isBuildAssetPath(requestUrl.pathname)) {
        event.respondWith(networkFirst(event.request, STATIC_CACHE, false));
        return;
    }

    if (isStaticAssetRequest(event.request, requestUrl.pathname)) {
        event.respondWith(staleWhileRevalidate(event.request, STATIC_CACHE, false));
        return;
    }

    if (isOfflineReadablePath(requestUrl.pathname)) {
        event.respondWith(staleWhileRevalidate(event.request, PAGE_CACHE, true));
        return;
    }

    event.respondWith(networkFirst(event.request, CONTENT_CACHE, false));
});

self.addEventListener('message', (event) => {
    const data = event.data;

    if (!data || typeof data !== 'object') {
        return;
    }

    if (data.type === 'CACHE_URLS' && Array.isArray(data.urls)) {
        if (!isOfflineQueueEnabled()) {
            return;
        }

        event.waitUntil(cacheProvidedUrls(data.urls));
        return;
    }

    if (data.type === 'SYNC_POSTS_NOW') {
        event.waitUntil(runQueuedPostsSync());
        return;
    }

    if (data.type === 'CLEAR_PRIVATE_DATA') {
        event.waitUntil(clearPrivateSessionData());
        return;
    }

    if (data.type === 'SET_OFFLINE_QUEUE_PREFERENCE') {
        event.waitUntil(setOfflineQueuePreference(data.enabled));
    }
});

self.addEventListener('sync', (event) => {
    if (event.tag === SYNC_TAG_POSTS) {
        event.waitUntil(runQueuedPostsSync());
    }
});

self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    event.waitUntil(
        (async () => {
            const payload = parsePushPayload(event.data);

            if (!payload) {
                return;
            }

            const title =
                typeof payload.title === 'string' && payload.title.trim() !== ''
                    ? payload.title.trim()
                    : 'C76-RPG';

            const data = normalizeNotificationData(payload.data);
            const options = {
                body: typeof payload.body === 'string' ? payload.body : '',
                icon: typeof payload.icon === 'string' ? payload.icon : '/images/icons/icon-192.png',
                badge: typeof payload.badge === 'string' ? payload.badge : '/images/icons/icon-96.png',
                image: typeof payload.image === 'string' ? payload.image : undefined,
                tag: typeof payload.tag === 'string' ? payload.tag : undefined,
                renotify: typeof payload.renotify === 'boolean' ? payload.renotify : undefined,
                requireInteraction: typeof payload.requireInteraction === 'boolean' ? payload.requireInteraction : undefined,
                actions: Array.isArray(payload.actions) ? payload.actions : [],
                data,
            };

            await self.registration.showNotification(title, options);
        })(),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification?.close();

    const rawActionUrl =
        event.notification?.data?.actionUrl ||
        event.notification?.data?.canonicalUrl ||
        event.notification?.data?.canonical_url;

    if (typeof rawActionUrl !== 'string' || rawActionUrl.trim() === '') {
        return;
    }

    event.waitUntil(
        (async () => {
            let targetUrl;

            try {
                const parsedTarget = new URL(rawActionUrl, self.location.origin);
                targetUrl = parsedTarget.origin === self.location.origin
                    ? parsedTarget.toString()
                    : new URL('/notifications', self.location.origin).toString();
            } catch {
                return;
            }

            const clients = await self.clients.matchAll({
                type: 'window',
                includeUncontrolled: true,
            });

            for (const client of clients) {
                if (!('url' in client) || !('focus' in client)) {
                    continue;
                }

                if (client.url === targetUrl) {
                    await client.focus();
                    return;
                }
            }

            for (const client of clients) {
                if (!('url' in client) || !('focus' in client) || !('navigate' in client)) {
                    continue;
                }

                if (client.url.startsWith(self.location.origin)) {
                    await client.focus();
                    await client.navigate(targetUrl);
                    return;
                }
            }

            await self.clients.openWindow(targetUrl);
        })(),
    );
});

function parsePushPayload(data) {
    try {
        return data.json();
    } catch {
        try {
            const text = data.text();
            return JSON.parse(text);
        } catch {
            return null;
        }
    }
}

function normalizeNotificationData(rawData) {
    if (!rawData || typeof rawData !== 'object') {
        return {
            actionUrl: '/notifications',
        };
    }

    const actionUrl =
        typeof rawData.actionUrl === 'string'
            ? rawData.actionUrl
            : (typeof rawData.canonicalUrl === 'string' ? rawData.canonicalUrl : '/notifications');

    return {
        ...rawData,
        actionUrl,
    };
}

function isOfflineReadablePath(pathname) {
    return (
        /^\/w\/[^/]+\/campaigns\/[^/]+\/scenes\/[^/]+\/?$/.test(pathname) ||
        /^\/campaigns\/[^/]+\/scenes\/[^/]+\/?$/.test(pathname) ||
        /^\/characters\/[^/]+\/?$/.test(pathname)
    );
}

function isStaticAssetRequest(request, pathname) {
    if (
        request.destination === 'style' ||
        request.destination === 'script' ||
        request.destination === 'image' ||
        request.destination === 'font'
    ) {
        return true;
    }

    return (
        pathname.startsWith('/build/') ||
        pathname.startsWith('/js/') ||
        pathname.startsWith('/images/') ||
        pathname.startsWith('/fonts/') ||
        pathname === '/manifest.webmanifest' ||
        pathname === '/favicon.ico'
    );
}

function isBuildAssetPath(pathname) {
    return pathname.startsWith('/build/');
}

function resolveOfflineFallbackUrl(request) {
    const pathname = resolveRequestPathname(request);
    const worldSlug = resolveOfflineWorldSlug(pathname);

    return `${OFFLINE_URL}?world=${encodeURIComponent(worldSlug)}&path=${encodeURIComponent(pathname)}`;
}

function resolveRequestPathname(request) {
    if (request instanceof Request) {
        try {
            return new URL(request.url).pathname || '/';
        } catch {
            return '/';
        }
    }

    if (typeof request === 'string') {
        try {
            return new URL(request, self.location.origin).pathname || '/';
        } catch {
            return '/';
        }
    }

    return '/';
}

function resolveOfflineWorldSlug(pathname) {
    if (typeof pathname !== 'string') {
        return DEFAULT_WORLD_SLUG;
    }

    const worldMatch = pathname.match(/^\/w\/([^/]+)/);

    if (!worldMatch || !worldMatch[1]) {
        return DEFAULT_WORLD_SLUG;
    }

    try {
        const decodedWorldSlug = decodeURIComponent(worldMatch[1]);

        return decodedWorldSlug !== '' ? decodedWorldSlug : DEFAULT_WORLD_SLUG;
    } catch {
        return worldMatch[1];
    }
}

async function resolveOfflineFallbackResponse(request) {
    const fallbackUrl = resolveOfflineFallbackUrl(request);
    const cachedFallback = await caches.match(fallbackUrl, {
        ignoreSearch: true,
    });

    if (cachedFallback) {
        return cachedFallback;
    }

    return Response.error();
}

async function matchCacheEntry(cache, request) {
    if (shouldIgnoreSearchForRequest(request)) {
        return cache.match(request, {
            ignoreSearch: true,
        });
    }

    return cache.match(request);
}

function shouldIgnoreSearchForRequest(request) {
    try {
        const requestUrl = request instanceof Request
            ? new URL(request.url)
            : new URL(String(request || ''), self.location.origin);

        return requestUrl.pathname === OFFLINE_URL;
    } catch {
        return false;
    }
}

async function networkFirst(request, cacheName, fallbackToOffline) {
    const cache = await caches.open(cacheName);

    try {
        const networkResponse = await fetch(request);

        if (shouldCache(request, networkResponse)) {
            await cache.put(request, networkResponse.clone());
        }

        return networkResponse;
    } catch {
        const cached = await matchCacheEntry(cache, request);

        if (cached) {
            return cached;
        }

        if (fallbackToOffline) {
            return resolveOfflineFallbackResponse(request);
        }

        return Response.error();
    }
}

async function staleWhileRevalidate(request, cacheName, fallbackToOffline) {
    const cache = await caches.open(cacheName);
    const cached = await matchCacheEntry(cache, request);

    const networkPromise = fetch(request)
        .then(async (response) => {
            if (shouldCache(request, response)) {
                await cache.put(request, response.clone());
            }

            return response;
        })
        .catch(() => null);

    if (cached) {
        networkPromise.catch(() => null);
        return cached;
    }

    const networkResponse = await networkPromise;

    if (networkResponse) {
        return networkResponse;
    }

    if (fallbackToOffline) {
        return resolveOfflineFallbackResponse(request);
    }

    return Response.error();
}

async function networkOnlyWithOfflineFallback(request) {
    try {
        return await fetch(request);
    } catch {
        return resolveOfflineFallbackResponse(request);
    }
}

function shouldCache(request, response) {
    if (!response || response.status !== 200) {
        return false;
    }

    const cacheControl = String(response.headers.get('Cache-Control') || '').toLowerCase();

    if (!cacheControl.includes('no-store') && !cacheControl.includes('private')) {
        return true;
    }

    return isExplicitlyAllowedPrivateOfflineResponse(request, response);
}

function isExplicitlyAllowedPrivateOfflineResponse(request, response) {
    const offlineCacheSignal = String(response.headers.get('X-C76-Offline-Cache') || '').trim().toLowerCase();

    if (offlineCacheSignal !== 'allow-private-html') {
        return false;
    }

    const contentType = String(response.headers.get('Content-Type') || '').toLowerCase();

    if (!contentType.includes('text/html')) {
        return false;
    }

    return isOfflineReadablePath(resolveRequestPathname(request));
}

async function cacheProvidedUrls(urls) {
    const cache = await caches.open(PAGE_CACHE);
    const targets = urls
        .map((url) => {
            try {
                const parsed = new URL(url, self.location.origin);

                if (parsed.origin !== self.location.origin) {
                    return null;
                }

                if (!isOfflineReadablePath(parsed.pathname)) {
                    return null;
                }

                return parsed.pathname + parsed.search;
            } catch {
                return null;
            }
        })
        .filter((target) => typeof target === 'string');

    const uniqueTargets = Array.from(new Set(targets));

    await Promise.all(
        uniqueTargets.map(async (target) => {
            try {
                const response = await fetch(target);

                if (shouldCache(target, response)) {
                    await cache.put(target, response.clone());
                }
            } catch {
                // Ignore network errors while warming up cache.
            }
        }),
    );
}
