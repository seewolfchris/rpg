const STATIC_CACHE = 'chroniken-static-v7';
const PAGE_CACHE = 'chroniken-pages-v6';
const CONTENT_CACHE = 'chroniken-content-v6';
const QUEUE_DB_NAME = 'chroniken-pbp';
const QUEUE_STORE_NAME = 'postQueue';
const SYNC_TAG_POSTS = 'pbp-sync-posts';
const OFFLINE_URL = '/offline.html';

const STATIC_ASSETS = [
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

self.addEventListener('install', (event) => {
    event.waitUntil(
        (async () => {
            const cache = await caches.open(STATIC_CACHE);
            await cache.addAll(STATIC_ASSETS);
            await self.skipWaiting();
        })(),
    );
});

self.addEventListener('activate', (event) => {
    const allowedCaches = [STATIC_CACHE, PAGE_CACHE, CONTENT_CACHE];

    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => !allowedCaches.includes(key)).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const requestUrl = new URL(event.request.url);

    if (requestUrl.origin !== self.location.origin) {
        return;
    }

    if (event.request.mode === 'navigate') {
        event.respondWith(networkFirst(event.request, PAGE_CACHE, true));
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
        event.waitUntil(cacheProvidedUrls(data.urls));
        return;
    }

    if (data.type === 'SYNC_POSTS_NOW') {
        event.waitUntil(syncQueuedPosts());
    }
});

self.addEventListener('sync', (event) => {
    if (event.tag === SYNC_TAG_POSTS) {
        event.waitUntil(syncQueuedPosts());
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
                targetUrl = new URL(rawActionUrl, self.location.origin).toString();
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

async function networkFirst(request, cacheName, fallbackToOffline) {
    const cache = await caches.open(cacheName);

    try {
        const networkResponse = await fetch(request);

        if (shouldCache(networkResponse)) {
            await cache.put(request, networkResponse.clone());
        }

        return networkResponse;
    } catch {
        const cached = await cache.match(request);

        if (cached) {
            return cached;
        }

        if (fallbackToOffline) {
            return caches.match(OFFLINE_URL);
        }

        return Response.error();
    }
}

async function staleWhileRevalidate(request, cacheName, fallbackToOffline) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);

    const networkPromise = fetch(request)
        .then(async (response) => {
            if (shouldCache(response)) {
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
        return caches.match(OFFLINE_URL);
    }

    return Response.error();
}

function shouldCache(response) {
    return Boolean(response) && response.status === 200;
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

                if (shouldCache(response)) {
                    await cache.put(target, response.clone());
                }
            } catch {
                // Ignore network errors while warming up cache.
            }
        }),
    );
}

async function syncQueuedPosts() {
    const queuedPosts = await getQueuedPosts();

    if (!queuedPosts.length) {
        return;
    }

    notifyClients('POST_SYNC_STARTED', {
        count: queuedPosts.length,
    });

    for (const item of queuedPosts) {
        const formData = new FormData();

        for (const entry of item.entries) {
            if (!Array.isArray(entry) || entry.length !== 2) {
                continue;
            }

            formData.append(entry[0], entry[1]);
        }

        try {
            const response = await fetch(item.url, {
                method: item.method || 'POST',
                credentials: 'include',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'text/html, application/xhtml+xml',
                },
                body: formData,
            });

            if (response.ok || response.redirected) {
                await deleteQueuedPost(item.id);
                notifyClients('POST_SYNC_SUCCESS', {
                    id: item.id,
                    url: item.url,
                });
                continue;
            }

            if (response.status >= 400 && response.status < 500) {
                if (response.status === 401 || response.status === 419 || response.status === 429) {
                    break;
                }

                await deleteQueuedPost(item.id);
                notifyClients('POST_SYNC_DROPPED', {
                    id: item.id,
                    url: item.url,
                    status: response.status,
                });
                continue;
            }

            break;
        } catch {
            break;
        }
    }

    const remaining = (await getQueuedPosts()).length;

    notifyClients('POST_SYNC_FINISHED', {
        remaining,
    });
}

function notifyClients(type, payload = {}) {
    return self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
        clients.forEach((client) => {
            client.postMessage({ type, payload });
        });
    });
}

function openQueueDatabase() {
    return new Promise((resolve, reject) => {
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
        request.onerror = () => reject(request.error || new Error('Unable to open queue database.'));
    });
}

async function getQueuedPosts() {
    const database = await openQueueDatabase();

    return new Promise((resolve, reject) => {
        const transaction = database.transaction(QUEUE_STORE_NAME, 'readonly');
        const store = transaction.objectStore(QUEUE_STORE_NAME);
        const request = store.getAll();

        request.onsuccess = () => {
            resolve(Array.isArray(request.result) ? request.result : []);
        };

        request.onerror = () => {
            reject(request.error || new Error('Unable to read queue entries.'));
        };

        transaction.oncomplete = () => {
            database.close();
        };
    });
}

async function deleteQueuedPost(id) {
    const database = await openQueueDatabase();

    return new Promise((resolve, reject) => {
        const transaction = database.transaction(QUEUE_STORE_NAME, 'readwrite');
        const store = transaction.objectStore(QUEUE_STORE_NAME);
        const request = store.delete(id);

        request.onsuccess = () => resolve(undefined);
        request.onerror = () => reject(request.error || new Error('Unable to delete queue entry.'));

        transaction.oncomplete = () => {
            database.close();
        };
    });
}
