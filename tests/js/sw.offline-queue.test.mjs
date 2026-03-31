import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const SERVICE_WORKER_PATH = new URL('../../public/sw.js', import.meta.url);
const APP_ORIGIN = 'https://example.test';
const SCENE_PATH = '/w/chroniken-der-asche/campaigns/1/scenes/1';
const POSTS_PATH = `${SCENE_PATH}/posts`;
const SOURCE_URL = `${APP_ORIGIN}${SCENE_PATH}`;
const POSTS_URL = `${APP_ORIGIN}${POSTS_PATH}`;
const RESIGNED_POSTS_URL = `${APP_ORIGIN}${POSTS_PATH}?signature=fresh`;

test('resolveOfflineFallbackUrl adds world and path context for scene routes', async () => {
    const harness = await createServiceWorkerHarness({
        queueItems: [],
        fetchImpl: async () => new Response('ok', { status: 200 }),
    });

    const fallbackUrl = harness.context.resolveOfflineFallbackUrl(new Request(SOURCE_URL));

    assert.equal(
        fallbackUrl,
        '/offline.html?world=chroniken-der-asche&path=%2Fw%2Fchroniken-der-asche%2Fcampaigns%2F1%2Fscenes%2F1',
    );
});

test('resolveOfflineFallbackUrl keeps default world context for non-world routes', async () => {
    const harness = await createServiceWorkerHarness({
        queueItems: [],
        fetchImpl: async () => new Response('ok', { status: 200 }),
    });

    const fallbackUrl = harness.context.resolveOfflineFallbackUrl(new Request(`${APP_ORIGIN}/characters/42`));

    assert.equal(
        fallbackUrl,
        '/offline.html?world=chroniken-der-asche&path=%2Fcharacters%2F42',
    );
});

test('clearPrivateSessionData removes private caches and queue database state', async () => {
    const deletedCacheKeys = [];
    let deletedDatabaseName = null;

    const harness = await createServiceWorkerHarness({
        queueItems: [],
        fetchImpl: async () => new Response('ok', { status: 200 }),
    });

    harness.context.caches.keys = async () => [
        'chroniken-static-v1',
        'chroniken-pages-v2',
        'chroniken-content-v2',
        'chroniken-pages-v1',
    ];
    harness.context.caches.delete = async (cacheKey) => {
        deletedCacheKeys.push(cacheKey);
        return true;
    };
    harness.context.indexedDB.deleteDatabase = (dbName) => {
        deletedDatabaseName = dbName;
        const request = {};
        queueMicrotask(() => {
            request.onsuccess?.();
        });

        return request;
    };

    await harness.context.clearPrivateSessionData();

    assert.deepEqual(
        deletedCacheKeys.sort(),
        ['chroniken-content-v2', 'chroniken-pages-v1', 'chroniken-pages-v2'],
    );
    assert.equal(deletedDatabaseName, 'chroniken-pbp');
    assert.ok(harness.eventTypes().includes('PRIVATE_DATA_CLEARED'));
});

test('syncQueuedPosts retries a 419 post after re-signing and clears queue', async () => {
    let submitAttempt = 0;
    const submitRequests = [];

    const harness = await createServiceWorkerHarness({
        queueItems: [
            createQueuedPost({
                id: 1,
                url: POSTS_URL,
                source_url: SOURCE_URL,
                source_path: SCENE_PATH,
                token: 'stale-token',
            }),
        ],
        fetchImpl: async (input, init) => {
            const url = resolveUrl(input);
            const method = String(init?.method || 'GET').toUpperCase();

            if (method === 'POST') {
                submitAttempt += 1;
                submitRequests.push({
                    url,
                    headers: init?.headers || {},
                    body: formDataToObject(init?.body),
                });

                if (submitAttempt === 1) {
                    return new Response('Page Expired', {
                        status: 419,
                    });
                }

                return new Response('ok', {
                    status: 200,
                });
            }

            if (url === SOURCE_URL) {
                return new Response(
                    [
                        '<html><head><meta name="csrf-token" content="fresh-token"></head>',
                        `<body><form data-offline-post-form action="${RESIGNED_POSTS_URL}"></form></body></html>`,
                    ].join(''),
                    {
                        status: 200,
                        headers: {
                            'Content-Type': 'text/html',
                        },
                    },
                );
            }

            throw new Error(`Unexpected fetch request: ${method} ${url}`);
        },
    });

    await harness.context.syncQueuedPosts();

    assert.deepEqual(
        harness.eventTypes(),
        [
            'POST_SYNC_STARTED',
            'POST_SYNC_AUTH_RETRY',
            'POST_SYNC_SUCCESS',
            'POST_SYNC_FINISHED',
        ],
    );

    assert.equal(harness.queue.length, 0);
    assert.equal(submitRequests.length, 2);
    assert.equal(submitRequests[0].url, POSTS_URL);
    assert.equal(submitRequests[0].headers['X-CSRF-TOKEN'], 'stale-token');
    assert.equal(submitRequests[1].url, RESIGNED_POSTS_URL);
    assert.equal(submitRequests[1].headers['X-CSRF-TOKEN'], 'fresh-token');
    assert.equal(submitRequests[1].body._token, 'fresh-token');
});

test('syncQueuedPosts schedules retry and requests auth when re-signing requires login', async () => {
    const nowMs = Date.parse('2026-03-12T00:00:00.000Z');
    let submitAttempt = 0;
    let sourceFetchAttempt = 0;

    const harness = await createServiceWorkerHarness({
        queueItems: [
            createQueuedPost({
                id: 7,
                url: POSTS_URL,
                source_url: SOURCE_URL,
                source_path: SCENE_PATH,
                token: 'stale-token',
            }),
        ],
        dateNowMs: nowMs,
        randomValue: 0,
        fetchImpl: async (input, init) => {
            const url = resolveUrl(input);
            const method = String(init?.method || 'GET').toUpperCase();

            if (method === 'POST') {
                submitAttempt += 1;
                assert.equal(url, POSTS_URL);
                assert.equal(submitAttempt, 1);

                return new Response('Page Expired', {
                    status: 419,
                });
            }

            if (url === SOURCE_URL) {
                sourceFetchAttempt += 1;
                assert.equal(sourceFetchAttempt, 1);

                return new Response('Unauthorized', {
                    status: 401,
                });
            }

            throw new Error(`Unexpected fetch request: ${method} ${url}`);
        },
    });

    await harness.context.syncQueuedPosts();

    assert.deepEqual(
        harness.eventTypes(),
        [
            'POST_SYNC_STARTED',
            'POST_SYNC_RETRY_SCHEDULED',
            'POST_SYNC_AUTH_REQUIRED',
            'POST_SYNC_FINISHED',
        ],
    );

    assert.equal(harness.queue.length, 1);
    const queuedItem = harness.queue[0];
    assert.equal(queuedItem.retry_count, 1);
    assert.equal(queuedItem.last_error_status, 419);
    assert.equal(queuedItem.last_error_reason, 'auth-required');
    assert.ok(typeof queuedItem.next_retry_at === 'string' && queuedItem.next_retry_at !== '');

    const nextRetryAtMs = Date.parse(queuedItem.next_retry_at);
    assert.ok(Number.isFinite(nextRetryAtMs));
    assert.equal(nextRetryAtMs - nowMs, 45_000);
});

test('syncQueuedPosts moves 422 responses to dead-letter and continues with next item', async () => {
    const submitRequests = [];

    const harness = await createServiceWorkerHarness({
        queueItems: [
            createQueuedPost({
                id: 11,
                url: POSTS_URL,
                source_url: SOURCE_URL,
                source_path: SCENE_PATH,
                token: 'token-11',
            }),
            createQueuedPost({
                id: 12,
                url: POSTS_URL,
                source_url: SOURCE_URL,
                source_path: SCENE_PATH,
                token: 'token-12',
            }),
        ],
        fetchImpl: async (input, init) => {
            const method = String(init?.method || 'GET').toUpperCase();

            if (method !== 'POST') {
                throw new Error(`Unexpected fetch request: ${method} ${resolveUrl(input)}`);
            }

            const body = formDataToObject(init?.body);
            submitRequests.push(body.content);

            if (String(body.content || '').includes('Queued post 11')) {
                return new Response(
                    JSON.stringify({
                        message: 'Die angegebenen Daten sind ungültig.',
                        errors: {
                            content: ['Text zu kurz'],
                        },
                    }),
                    {
                        status: 422,
                        headers: {
                            'Content-Type': 'application/json',
                        },
                    },
                );
            }

            return new Response('ok', { status: 200 });
        },
    });

    await harness.context.syncQueuedPosts();

    assert.deepEqual(
        harness.eventTypes(),
        [
            'POST_SYNC_STARTED',
            'POST_SYNC_DEAD_LETTERED',
            'POST_SYNC_SUCCESS',
            'POST_SYNC_FINISHED',
        ],
    );
    assert.equal(harness.queue.length, 0);
    assert.equal(harness.deadLetters.length, 1);
    assert.equal(harness.deadLetters[0].source_queue_id, 11);
    assert.equal(harness.deadLetters[0].error_summary, 'Text zu kurz');
    assert.deepEqual(submitRequests, ['Queued post 11', 'Queued post 12']);
});

test('syncQueuedPosts retries 500 responses five times, then dead-letters and continues', async () => {
    const nowMs = Date.parse('2026-03-12T00:00:00.000Z');
    let failingAttempts = 0;
    let successfulAttempts = 0;

    const harness = await createServiceWorkerHarness({
        queueItems: [
            createQueuedPost({
                id: 21,
                url: POSTS_URL,
                source_url: SOURCE_URL,
                source_path: SCENE_PATH,
                token: 'token-21',
            }),
            createQueuedPost({
                id: 22,
                url: POSTS_URL,
                source_url: SOURCE_URL,
                source_path: SCENE_PATH,
                token: 'token-22',
            }),
        ],
        dateNowMs: nowMs,
        randomValue: 0,
        fetchImpl: async (input, init) => {
            const method = String(init?.method || 'GET').toUpperCase();

            if (method !== 'POST') {
                throw new Error(`Unexpected fetch request: ${method} ${resolveUrl(input)}`);
            }

            const body = formDataToObject(init?.body);
            const content = String(body.content || '');

            if (content.includes('Queued post 21')) {
                failingAttempts += 1;
                return new Response('Internal Server Error', { status: 500 });
            }

            successfulAttempts += 1;
            return new Response('ok', { status: 200 });
        },
    });

    for (let cycle = 0; cycle < 6; cycle += 1) {
        await harness.context.syncQueuedPosts();
        harness.advanceTime(900_000);
    }

    assert.equal(failingAttempts, 6);
    assert.equal(successfulAttempts, 1);
    assert.equal(harness.queue.length, 0);
    assert.equal(harness.deadLetters.length, 1);
    assert.equal(harness.deadLetters[0].source_queue_id, 21);
    assert.equal(harness.deadLetters[0].last_error_status, 500);
    assert.equal(harness.deadLetters[0].error_summary, 'Server-Fehler (500)');
    assert.ok(harness.eventTypes().includes('POST_SYNC_DEAD_LETTERED'));
    assert.ok(harness.eventTypes().includes('POST_SYNC_SUCCESS'));
});

function createQueuedPost({ id, url, source_url, source_path, token }) {
    return {
        id,
        url,
        method: 'POST',
        entries: [
            ['_token', token],
            ['post_type', 'ooc'],
            ['content_format', 'plain'],
            ['content', `Queued post ${id}`],
        ],
        queued_at: '2026-03-12T00:00:00.000Z',
        source_url,
        source_path,
    };
}

async function createServiceWorkerHarness({ queueItems, fetchImpl, randomValue = 0.1234, dateNowMs = null }) {
    const scriptSource = await readFile(SERVICE_WORKER_PATH, 'utf8');
    const queueState = deepClone(queueItems);
    const deadLetterState = [];
    const emittedEvents = [];
    const dateState = { nowMs: dateNowMs };
    const DateClass = createDateClass(dateState);
    const MathObject = Object.create(Math);
    MathObject.random = () => randomValue;

    const context = {
        URL,
        Response,
        Request,
        Headers,
        FormData,
        setTimeout,
        clearTimeout,
        Date: DateClass,
        Math: MathObject,
        console,
        fetch: fetchImpl,
        caches: {
            open: async () => ({
                addAll: async () => undefined,
                put: async () => undefined,
                match: async () => null,
            }),
            keys: async () => [],
            delete: async () => true,
            match: async () => null,
        },
        indexedDB: {
            open: () => {
                throw new Error('indexedDB.open should not be called in this test harness');
            },
        },
        self: {
            location: new URL(APP_ORIGIN),
            addEventListener: () => undefined,
            skipWaiting: async () => undefined,
            clients: {
                claim: async () => undefined,
                matchAll: async () => [],
            },
            registration: {},
        },
    };

    vm.createContext(context);
    vm.runInContext(scriptSource, context, {
        filename: 'public/sw.js',
    });

    context.getQueuedPosts = async () => deepClone(queueState);
    context.deleteQueuedPost = async (id) => {
        const index = queueState.findIndex((item) => Number(item.id) === Number(id));

        if (index >= 0) {
            queueState.splice(index, 1);
        }
    };
    context.putQueuedPost = async (item) => {
        const index = queueState.findIndex((queuedItem) => Number(queuedItem.id) === Number(item.id));
        const normalizedItem = deepClone(item);

        if (index >= 0) {
            queueState[index] = normalizedItem;
            return;
        }

        queueState.push(normalizedItem);
    };
    context.putDeadLetter = async (item) => {
        const nextId = deadLetterState.reduce((maxId, entry) => Math.max(maxId, Number(entry.id || 0)), 0) + 1;
        const normalizedItem = deepClone({
            ...item,
            id: nextId,
        });

        deadLetterState.push(normalizedItem);

        return normalizedItem;
    };
    context.notifyClients = async (type, payload = {}) => {
        emittedEvents.push({
            type,
            payload: deepClone(payload),
        });
    };

    return {
        context,
        queue: queueState,
        deadLetters: deadLetterState,
        events: emittedEvents,
        eventTypes() {
            return emittedEvents.map((event) => event.type);
        },
        advanceTime(milliseconds) {
            if (dateState.nowMs === null) {
                return;
            }

            dateState.nowMs += Number(milliseconds || 0);
        },
    };
}

function createDateClass(state) {
    if (state.nowMs === null) {
        return Date;
    }

    return class FakeDate extends Date {
        constructor(...args) {
            if (args.length === 0) {
                super(state.nowMs);
                return;
            }

            super(...args);
        }

        static now() {
            return state.nowMs;
        }
    };
}

function resolveUrl(input) {
    if (typeof input === 'string') {
        return input;
    }

    if (input instanceof URL) {
        return input.toString();
    }

    if (typeof input?.url === 'string') {
        return input.url;
    }

    return String(input);
}

function formDataToObject(formData) {
    if (!formData || typeof formData.entries !== 'function') {
        return {};
    }

    const values = {};

    for (const [key, value] of formData.entries()) {
        values[key] = value;
    }

    return values;
}

function deepClone(value) {
    return JSON.parse(JSON.stringify(value));
}
