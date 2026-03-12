const STATIC_CACHE = 'chroniken-static-v8';
const PAGE_CACHE = 'chroniken-pages-v6';
const CONTENT_CACHE = 'chroniken-content-v6';
const QUEUE_DB_NAME = 'chroniken-pbp';
const QUEUE_STORE_NAME = 'postQueue';
const SYNC_TAG_POSTS = 'pbp-sync-posts';
const OFFLINE_URL = '/offline.html';
const RETRY_MIN_DELAY_MS = 5 * 1000;
const RETRY_BASE_DELAY_MS = 30 * 1000;
const RETRY_MAX_DELAY_MS = 15 * 60 * 1000;
const RETRY_JITTER_RATIO = 0.2;

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
        if (isOfflineReadablePath(requestUrl.pathname)) {
            event.respondWith(networkFirst(event.request, PAGE_CACHE, true));
            return;
        }

        event.respondWith(networkOnlyWithOfflineFallback(event.request));
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

async function networkOnlyWithOfflineFallback(request) {
    try {
        return await fetch(request);
    } catch {
        return (await caches.match(OFFLINE_URL)) || Response.error();
    }
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
    const queuedPosts = (await getQueuedPosts()).sort((left, right) => Number(left.id || 0) - Number(right.id || 0));

    if (!queuedPosts.length) {
        return;
    }

    notifyClients('POST_SYNC_STARTED', {
        count: queuedPosts.length,
    });

    const now = Date.now();

    for (const rawItem of queuedPosts) {
        const item = normalizeQueuedPost(rawItem);

        if (isRetryDeferred(item, now)) {
            continue;
        }

        const firstAttempt = await submitQueuedPost(item);

        if (firstAttempt.ok) {
            await deleteQueuedPost(item.id);
            notifyClients('POST_SYNC_SUCCESS', {
                id: item.id,
                url: item.url,
            });
            continue;
        }

        if (firstAttempt.status === 419) {
            const resigned = await reSignQueuedPost(item);

            if (resigned.item) {
                notifyClients('POST_SYNC_AUTH_RETRY', {
                    id: item.id,
                    url: resigned.item.url,
                });

                const secondAttempt = await submitQueuedPost(resigned.item);

                if (secondAttempt.ok) {
                    await deleteQueuedPost(resigned.item.id);
                    notifyClients('POST_SYNC_SUCCESS', {
                        id: resigned.item.id,
                        url: resigned.item.url,
                    });
                    continue;
                }

                const shouldContinue = await handleFailedQueuedPost(resigned.item, secondAttempt);

                if (!shouldContinue) {
                    break;
                }

                continue;
            }

            const scheduledItem = await scheduleQueuedPostRetry(item, {
                status: 419,
                response: resigned.response || null,
                reason: resigned.reason || 'resign-failed',
            });

            notifyClients('POST_SYNC_AUTH_REQUIRED', {
                id: item.id,
                url: item.url,
                status: 419,
                nextRetryAt: scheduledItem.next_retry_at,
            });
            break;
        }

        const shouldContinue = await handleFailedQueuedPost(item, firstAttempt);

        if (!shouldContinue) {
            break;
        }
    }

    const remaining = (await getQueuedPosts()).length;

    notifyClients('POST_SYNC_FINISHED', {
        remaining,
    });
}

function normalizeQueuedPost(item) {
    return {
        ...item,
        entries: normalizeQueueEntries(item.entries),
    };
}

function normalizeQueueEntries(entries) {
    if (!Array.isArray(entries)) {
        return [];
    }

    const normalizedEntries = [];

    for (const entry of entries) {
        if (!Array.isArray(entry) || entry.length !== 2) {
            continue;
        }

        const [key, value] = entry;

        if (typeof key !== 'string') {
            continue;
        }

        if (typeof value !== 'string' && typeof value !== 'number' && typeof value !== 'boolean') {
            continue;
        }

        normalizedEntries.push([key, String(value)]);
    }

    return normalizedEntries;
}

function isRetryDeferred(item, nowMs) {
    const nextRetryAt = Date.parse(typeof item.next_retry_at === 'string' ? item.next_retry_at : '');

    return Number.isFinite(nextRetryAt) && nextRetryAt > nowMs;
}

async function submitQueuedPost(item) {
    const formData = buildFormData(item.entries);
    const csrfToken = findFormEntryValue(item.entries, '_token');
    const headers = {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'text/html, application/xhtml+xml',
    };

    if (csrfToken) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }

    try {
        const response = await fetch(item.url, {
            method: item.method || 'POST',
            credentials: 'include',
            headers,
            body: formData,
        });

        if (isSuccessfulPostResponse(response)) {
            return {
                ok: true,
                status: response.status || 200,
                response,
            };
        }

        if (isAuthRedirectResponse(response)) {
            return {
                ok: false,
                status: 401,
                reason: 'auth-redirect',
                response,
            };
        }

        return {
            ok: false,
            status: Number(response.status || 0),
            response,
        };
    } catch {
        return {
            ok: false,
            status: 0,
            reason: 'network-error',
        };
    }
}

function buildFormData(entries) {
    const formData = new FormData();

    for (const entry of normalizeQueueEntries(entries)) {
        formData.append(entry[0], entry[1]);
    }

    return formData;
}

function findFormEntryValue(entries, key) {
    for (const entry of normalizeQueueEntries(entries)) {
        if (entry[0] === key) {
            return entry[1];
        }
    }

    return null;
}

function replaceFormEntry(entries, key, value) {
    const normalizedEntries = normalizeQueueEntries(entries).filter((entry) => entry[0] !== key);
    normalizedEntries.push([key, value]);

    return normalizedEntries;
}

function isSuccessfulPostResponse(response) {
    if (!response) {
        return false;
    }

    if (response.ok) {
        return true;
    }

    if (!response.redirected) {
        return false;
    }

    return !isAuthRedirectResponse(response);
}

function isAuthRedirectResponse(response) {
    if (!response || !response.redirected || typeof response.url !== 'string') {
        return false;
    }

    try {
        const redirectedUrl = new URL(response.url, self.location.origin);

        return isAuthenticationPath(redirectedUrl.pathname);
    } catch {
        return false;
    }
}

function isAuthenticationPath(pathname) {
    if (typeof pathname !== 'string' || pathname === '') {
        return false;
    }

    return (
        pathname === '/login' ||
        pathname.startsWith('/login/') ||
        pathname.startsWith('/password/') ||
        pathname.startsWith('/two-factor')
    );
}

async function reSignQueuedPost(item) {
    const signingSources = resolveSigningSourceCandidates(item);

    for (const sourceUrl of signingSources) {
        let response;

        try {
            response = await fetch(sourceUrl, {
                method: 'GET',
                credentials: 'include',
                headers: {
                    Accept: 'text/html, application/xhtml+xml',
                },
            });
        } catch {
            continue;
        }

        if (response.status === 401 || isAuthRedirectResponse(response)) {
            return {
                item: null,
                reason: 'auth-required',
                response,
            };
        }

        if (!response.ok) {
            continue;
        }

        const html = await response.text();
        const csrfToken = extractCsrfTokenFromHtml(html);
        const signedAction = extractOfflineFormActionFromHtml(html);

        if (!csrfToken && !signedAction) {
            continue;
        }

        const updatedEntries = csrfToken
            ? replaceFormEntry(item.entries, '_token', csrfToken)
            : normalizeQueueEntries(item.entries);
        const resolvedActionUrl = signedAction
            ? resolveSameOriginUrl(signedAction, sourceUrl)
            : resolveSameOriginUrl(item.url, self.location.origin);

        if (!resolvedActionUrl) {
            continue;
        }

        const sourcePathname = safePathnameFromUrl(sourceUrl);
        const nowIso = new Date().toISOString();
        const updatedItem = {
            ...item,
            url: resolvedActionUrl,
            entries: updatedEntries,
            source_url: sourceUrl,
            source_path: sourcePathname || item.source_path || null,
            last_resigned_at: nowIso,
            next_retry_at: null,
            last_error_status: null,
            last_error_reason: null,
            last_attempt_at: nowIso,
        };

        await putQueuedPost(updatedItem);

        return {
            item: updatedItem,
            reason: null,
            response,
        };
    }

    return {
        item: null,
        reason: 'signing-context-unavailable',
        response: null,
    };
}

function resolveSigningSourceCandidates(item) {
    const rawCandidates = [
        item.source_url,
        item.source_path,
        inferSourcePathFromSubmissionUrl(item.url),
    ];
    const uniqueCandidates = [];

    for (const rawCandidate of rawCandidates) {
        const normalizedCandidate = resolveSameOriginUrl(rawCandidate, self.location.origin);

        if (!normalizedCandidate) {
            continue;
        }

        if (!uniqueCandidates.includes(normalizedCandidate)) {
            uniqueCandidates.push(normalizedCandidate);
        }
    }

    return uniqueCandidates;
}

function inferSourcePathFromSubmissionUrl(rawUrl) {
    if (typeof rawUrl !== 'string' || rawUrl.trim() === '') {
        return null;
    }

    try {
        const parsedUrl = new URL(rawUrl, self.location.origin);

        if (parsedUrl.origin !== self.location.origin) {
            return null;
        }

        if (parsedUrl.pathname.endsWith('/posts')) {
            return parsedUrl.pathname.slice(0, -'/posts'.length) || '/';
        }

        return parsedUrl.pathname;
    } catch {
        return null;
    }
}

function resolveSameOriginUrl(rawUrl, baseUrl) {
    if (typeof rawUrl !== 'string' || rawUrl.trim() === '') {
        return null;
    }

    try {
        const resolvedUrl = new URL(rawUrl, baseUrl || self.location.origin);

        if (resolvedUrl.origin !== self.location.origin) {
            return null;
        }

        return resolvedUrl.toString();
    } catch {
        return null;
    }
}

function safePathnameFromUrl(rawUrl) {
    if (typeof rawUrl !== 'string' || rawUrl.trim() === '') {
        return null;
    }

    try {
        const parsed = new URL(rawUrl, self.location.origin);
        return parsed.pathname;
    } catch {
        return null;
    }
}

function extractCsrfTokenFromHtml(html) {
    if (typeof html !== 'string' || html === '') {
        return null;
    }

    const metaTags = html.match(/<meta\b[^>]*>/gi) || [];

    for (const tag of metaTags) {
        const name = extractTagAttribute(tag, 'name');

        if (typeof name !== 'string' || name.toLowerCase() !== 'csrf-token') {
            continue;
        }

        const content = extractTagAttribute(tag, 'content');

        if (content) {
            return content;
        }
    }

    const inputTags = html.match(/<input\b[^>]*>/gi) || [];

    for (const tag of inputTags) {
        const name = extractTagAttribute(tag, 'name');

        if (name !== '_token') {
            continue;
        }

        const value = extractTagAttribute(tag, 'value');

        if (value) {
            return value;
        }
    }

    return null;
}

function extractOfflineFormActionFromHtml(html) {
    if (typeof html !== 'string' || html === '') {
        return null;
    }

    const formTags = html.match(/<form\b[^>]*>/gi) || [];

    for (const tag of formTags) {
        if (!hasTagAttribute(tag, 'data-offline-post-form')) {
            continue;
        }

        const action = extractTagAttribute(tag, 'action');

        if (action) {
            return action;
        }
    }

    return null;
}

function hasTagAttribute(tag, attributeName) {
    if (typeof tag !== 'string' || typeof attributeName !== 'string') {
        return false;
    }

    const pattern = new RegExp(`\\b${attributeName}(?:\\s*=\\s*(?:\"[^\"]*\"|'[^']*'|[^\\s>]+))?`, 'i');
    return pattern.test(tag);
}

function extractTagAttribute(tag, attributeName) {
    if (typeof tag !== 'string' || typeof attributeName !== 'string') {
        return null;
    }

    const quotedPattern = new RegExp(`\\b${attributeName}\\s*=\\s*(\"([^\"]*)\"|'([^']*)')`, 'i');
    const quotedMatch = tag.match(quotedPattern);

    if (quotedMatch) {
        const rawValue = quotedMatch[2] ?? quotedMatch[3] ?? '';
        return decodeHtmlAttribute(rawValue);
    }

    const barePattern = new RegExp(`\\b${attributeName}\\s*=\\s*([^\\s>]+)`, 'i');
    const bareMatch = tag.match(barePattern);

    if (!bareMatch) {
        return null;
    }

    return decodeHtmlAttribute(bareMatch[1]);
}

function decodeHtmlAttribute(value) {
    if (typeof value !== 'string' || value === '') {
        return value;
    }

    return value
        .replace(/&amp;/g, '&')
        .replace(/&quot;/g, '"')
        .replace(/&apos;/g, "'")
        .replace(/&#039;/g, "'")
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>');
}

async function handleFailedQueuedPost(item, attempt) {
    const status = Number(attempt?.status || 0);

    if (status >= 400 && status < 500 && status !== 401 && status !== 419 && status !== 429) {
        await deleteQueuedPost(item.id);
        notifyClients('POST_SYNC_DROPPED', {
            id: item.id,
            url: item.url,
            status,
        });
        return true;
    }

    const scheduledItem = await scheduleQueuedPostRetry(item, {
        status,
        response: attempt?.response || null,
        reason: attempt?.reason || null,
    });

    if (status === 401 || status === 419) {
        notifyClients('POST_SYNC_AUTH_REQUIRED', {
            id: item.id,
            url: item.url,
            status,
            nextRetryAt: scheduledItem.next_retry_at,
        });
    }

    return false;
}

async function scheduleQueuedPostRetry(item, details = {}) {
    const status = Number(details.status || 0);
    const retryCount = Math.max(0, Number(item.retry_count || 0)) + 1;
    const delayMs = resolveRetryDelayMs({
        status,
        retryCount,
        response: details.response || null,
    });
    const nowIso = new Date().toISOString();
    const nextRetryAt = new Date(Date.now() + delayMs).toISOString();
    const updatedItem = {
        ...item,
        retry_count: retryCount,
        next_retry_at: nextRetryAt,
        last_error_status: status || null,
        last_error_reason: details.reason || null,
        last_attempt_at: nowIso,
    };

    await putQueuedPost(updatedItem);

    notifyClients('POST_SYNC_RETRY_SCHEDULED', {
        id: updatedItem.id,
        url: updatedItem.url,
        status: status || 0,
        retryCount,
        nextRetryAt,
        delayMs,
    });

    return updatedItem;
}

function resolveRetryDelayMs({ status, retryCount, response }) {
    const retryAfterDelayMs = parseRetryAfterDelayMs(response);

    if (status === 429 && retryAfterDelayMs !== null) {
        return applyRetryJitter(clampRetryDelayMs(retryAfterDelayMs));
    }

    let baseDelayMs = RETRY_BASE_DELAY_MS;

    if (status === 401 || status === 419) {
        baseDelayMs = 45 * 1000;
    } else if (status === 429) {
        baseDelayMs = 60 * 1000;
    }

    const exponent = Math.max(0, Math.min(Number(retryCount || 1) - 1, 6));
    const backoffDelayMs = baseDelayMs * 2 ** exponent;

    return applyRetryJitter(clampRetryDelayMs(backoffDelayMs));
}

function parseRetryAfterDelayMs(response) {
    if (!response || typeof response.headers?.get !== 'function') {
        return null;
    }

    const retryAfter = response.headers.get('Retry-After');

    if (!retryAfter) {
        return null;
    }

    const retryAfterSeconds = Number(retryAfter);

    if (Number.isFinite(retryAfterSeconds)) {
        return Math.max(0, Math.round(retryAfterSeconds * 1000));
    }

    const retryAfterDateMs = Date.parse(retryAfter);

    if (!Number.isFinite(retryAfterDateMs)) {
        return null;
    }

    return Math.max(0, retryAfterDateMs - Date.now());
}

function applyRetryJitter(delayMs) {
    const jitterMs = Math.round(delayMs * RETRY_JITTER_RATIO * Math.random());

    return clampRetryDelayMs(delayMs + jitterMs);
}

function clampRetryDelayMs(delayMs) {
    if (!Number.isFinite(delayMs)) {
        return RETRY_BASE_DELAY_MS;
    }

    return Math.min(RETRY_MAX_DELAY_MS, Math.max(RETRY_MIN_DELAY_MS, Math.round(delayMs)));
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

async function putQueuedPost(item) {
    const database = await openQueueDatabase();

    return new Promise((resolve, reject) => {
        const transaction = database.transaction(QUEUE_STORE_NAME, 'readwrite');
        const store = transaction.objectStore(QUEUE_STORE_NAME);
        const request = store.put(item);

        request.onsuccess = () => resolve(undefined);
        request.onerror = () => reject(request.error || new Error('Unable to update queue entry.'));

        transaction.oncomplete = () => {
            database.close();
        };
    });
}
