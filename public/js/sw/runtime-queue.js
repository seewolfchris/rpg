function runQueuedPostsSync() {
    if (activePostSyncPromise) {
        return activePostSyncPromise;
    }

    activePostSyncPromise = syncQueuedPosts().finally(() => {
        activePostSyncPromise = null;
    });

    return activePostSyncPromise;
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

                const secondAttempt = await submitQueuedPost(resigned.item, resigned.csrfToken || null);

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

const SENSITIVE_QUEUE_KEYS = new Set([
    '_token',
    '_method',
    'password',
    'password_confirmation',
    'current_password',
    'new_password',
    'remember_token',
]);

function isSensitiveQueueKey(rawKey) {
    if (typeof rawKey !== 'string') {
        return true;
    }

    const key = rawKey.trim().toLowerCase();

    if (key === '') {
        return true;
    }

    if (SENSITIVE_QUEUE_KEYS.has(key)) {
        return true;
    }

    return key.includes('csrf') || key.includes('password') || key.endsWith('_token');
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

        if (isSensitiveQueueKey(key)) {
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

async function submitQueuedPost(item, csrfTokenOverride = null) {
    const targetUrl = resolveSameOriginUrl(item.url, self.location.origin);

    if (!targetUrl) {
        return {
            ok: false,
            status: 0,
            reason: 'invalid-target-origin',
        };
    }

    const method = String(item.method || 'POST').toUpperCase();

    if (method !== 'POST') {
        return {
            ok: false,
            status: 0,
            reason: 'invalid-method',
        };
    }

    const formData = buildFormData(item.entries);
    const headers = {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json, text/html, application/xhtml+xml',
    };

    if (typeof csrfTokenOverride === 'string' && csrfTokenOverride.trim() !== '') {
        headers['X-CSRF-TOKEN'] = csrfTokenOverride.trim();
    }

    try {
        const response = await fetch(targetUrl, {
            method: 'POST',
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
                csrfToken: null,
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

        const updatedEntries = normalizeQueueEntries(item.entries);
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
            csrfToken: csrfToken || null,
        };
    }

    return {
        item: null,
        reason: 'signing-context-unavailable',
        response: null,
        csrfToken: null,
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
    const isClientError = status >= 400 && status < 500 && status !== 401 && status !== 419 && status !== 429;
    const isServerError = status === 0 || status >= 500;

    if (isClientError) {
        await moveQueuedPostToDeadLetter(item, attempt, status);
        return true;
    }

    const currentRetryCount = Math.max(0, Number(item.retry_count || 0));

    if (isServerError && currentRetryCount >= MAX_SERVER_RETRIES) {
        await moveQueuedPostToDeadLetter(item, attempt, status);
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

async function moveQueuedPostToDeadLetter(item, attempt, status) {
    const deadLetter = await buildDeadLetterEntry(item, attempt, status);
    const storedDeadLetter = await putDeadLetter(deadLetter);

    await deleteQueuedPost(item.id);

    notifyClients('POST_SYNC_DEAD_LETTERED', {
        id: item.id,
        deadLetterId: storedDeadLetter.id,
        url: item.url,
        status,
        errorSummary: storedDeadLetter.error_summary,
    });
}

async function buildDeadLetterEntry(item, attempt, status) {
    const errorSummary = await buildErrorSummary(status, attempt);
    const nowIso = new Date().toISOString();

    return {
        source_queue_id: Number(item.id || 0) || null,
        url: item.url || null,
        method: item.method || 'POST',
        entries: normalizeQueueEntries(item.entries),
        queued_at: typeof item.queued_at === 'string' ? item.queued_at : null,
        dead_lettered_at: nowIso,
        last_error_status: status || null,
        last_error_reason: attempt?.reason || null,
        error_summary: errorSummary,
    };
}

async function buildErrorSummary(status, attempt) {
    const validationSummary = status === 422
        ? await extractFirstValidationMessage(attempt?.response || null)
        : null;

    if (validationSummary) {
        return validationSummary;
    }

    if (status >= 500) {
        return `Server-Fehler (${status})`;
    }

    if (status === 403) {
        return 'Zugriff verweigert (403)';
    }

    if (status === 404) {
        return 'Ressource nicht gefunden (404)';
    }

    if (status >= 400 && status < 500) {
        return `Client-Fehler (${status})`;
    }

    if (status > 0) {
        return `Synchronisierung fehlgeschlagen (${status})`;
    }

    return 'Synchronisierung fehlgeschlagen (unknown)';
}

async function extractFirstValidationMessage(response) {
    if (!response || typeof response.clone !== 'function') {
        return null;
    }

    let payload = null;

    try {
        payload = await response.clone().json();
    } catch {
        payload = null;
    }

    if (!payload || typeof payload !== 'object') {
        return null;
    }

    const errors = payload.errors;

    if (errors && typeof errors === 'object') {
        for (const messages of Object.values(errors)) {
            if (!Array.isArray(messages)) {
                continue;
            }

            for (const message of messages) {
                if (typeof message === 'string' && message.trim() !== '') {
                    return message.trim();
                }
            }
        }
    }

    if (typeof payload.message === 'string' && payload.message.trim() !== '') {
        return payload.message.trim();
    }

    return null;
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
        const request = indexedDB.open(QUEUE_DB_NAME, 2);

        request.onupgradeneeded = () => {
            const database = request.result;

            if (!database.objectStoreNames.contains(QUEUE_STORE_NAME)) {
                database.createObjectStore(QUEUE_STORE_NAME, {
                    keyPath: 'id',
                    autoIncrement: true,
                });
            }

            if (!database.objectStoreNames.contains(DEAD_LETTER_STORE_NAME)) {
                database.createObjectStore(DEAD_LETTER_STORE_NAME, {
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

async function putDeadLetter(item) {
    const database = await openQueueDatabase();

    return new Promise((resolve, reject) => {
        const transaction = database.transaction(DEAD_LETTER_STORE_NAME, 'readwrite');
        const store = transaction.objectStore(DEAD_LETTER_STORE_NAME);
        const request = store.add(item);

        request.onsuccess = () => {
            resolve({
                ...item,
                id: Number(request.result || 0),
            });
        };
        request.onerror = () => reject(request.error || new Error('Unable to write dead-letter entry.'));

        transaction.oncomplete = () => {
            database.close();
        };
    });
}
