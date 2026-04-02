import { expect, test } from '@playwright/test';

async function getQueuedPostCount(page) {
    return page.evaluate(async () => {
        return new Promise((resolve, reject) => {
            const openRequest = indexedDB.open('chroniken-pbp', 2);

            openRequest.onsuccess = () => {
                const database = openRequest.result;

                if (!database.objectStoreNames.contains('postQueue')) {
                    database.close();
                    resolve(0);
                    return;
                }

                const transaction = database.transaction('postQueue', 'readonly');
                const countRequest = transaction.objectStore('postQueue').count();

                countRequest.onsuccess = () => {
                    const count = Number(countRequest.result || 0);
                    transaction.oncomplete = () => {
                        database.close();
                        resolve(count);
                    };
                };

                countRequest.onerror = () => {
                    database.close();
                    reject(countRequest.error || new Error('Queue count failed.'));
                };
            };

            openRequest.onerror = () => {
                reject(openRequest.error || new Error('Queue database open failed.'));
            };
        });
    });
}

async function getFirstQueuedPost(page) {
    return page.evaluate(async () => {
        return new Promise((resolve, reject) => {
            const openRequest = indexedDB.open('chroniken-pbp', 2);

            openRequest.onsuccess = () => {
                const database = openRequest.result;

                if (!database.objectStoreNames.contains('postQueue')) {
                    database.close();
                    resolve(null);
                    return;
                }

                const transaction = database.transaction('postQueue', 'readonly');
                const request = transaction.objectStore('postQueue').getAll();

                request.onsuccess = () => {
                    const entries = Array.isArray(request.result) ? request.result : [];
                    const first = entries[0] || null;

                    transaction.oncomplete = () => {
                        database.close();
                        resolve(first);
                    };
                };

                request.onerror = () => {
                    database.close();
                    reject(request.error || new Error('Queue read failed.'));
                };
            };

            openRequest.onerror = () => {
                reject(openRequest.error || new Error('Queue database open failed.'));
            };
        });
    });
}

async function mutateFirstQueuedPost(page, mutate) {
    await page.evaluate(async (mutateType) => {
        await new Promise((resolve, reject) => {
            const openRequest = indexedDB.open('chroniken-pbp', 2);

            openRequest.onsuccess = () => {
                const database = openRequest.result;

                if (!database.objectStoreNames.contains('postQueue')) {
                    database.close();
                    reject(new Error('postQueue store missing.'));
                    return;
                }

                const transaction = database.transaction('postQueue', 'readwrite');
                const store = transaction.objectStore('postQueue');
                const getRequest = store.getAll();

                getRequest.onsuccess = () => {
                    const entries = Array.isArray(getRequest.result) ? getRequest.result : [];
                    const item = entries[0];

                    if (!item) {
                        reject(new Error('No queued post found.'));
                        return;
                    }

                    const normalizedEntries = Array.isArray(item.entries)
                        ? item.entries
                            .filter((entry) => Array.isArray(entry) && entry.length === 2)
                            .map((entry) => [String(entry[0]), String(entry[1])])
                        : [];

                    let hasToken = false;

                    for (const entry of normalizedEntries) {
                        if (entry[0] !== '_token') {
                            continue;
                        }

                        entry[1] = 'stale-token';
                        hasToken = true;
                    }

                    if (!hasToken) {
                        normalizedEntries.push(['_token', 'stale-token']);
                    }

                    item.entries = normalizedEntries;
                    item.retry_count = 0;
                    item.next_retry_at = null;
                    item.last_error_status = null;
                    item.last_error_reason = null;

                    if (mutateType === 'auth-required') {
                        item.source_url = '/dashboard';
                        item.source_path = '/dashboard';
                    }

                    const putRequest = store.put(item);

                    putRequest.onsuccess = () => resolve(undefined);
                    putRequest.onerror = () => reject(putRequest.error || new Error('Queue mutation failed.'));
                };

                getRequest.onerror = () => reject(getRequest.error || new Error('Queue read failed.'));

                transaction.oncomplete = () => {
                    database.close();
                };
            };

            openRequest.onerror = () => {
                reject(openRequest.error || new Error('Queue database open failed.'));
            };
        });
    }, mutate);
}

async function startManualSync(page) {
    await page.evaluate(async () => {
        const registration = await navigator.serviceWorker.ready;
        const worker = navigator.serviceWorker.controller
            || registration.active
            || registration.waiting
            || registration.installing
            || null;

        if (!worker) {
            throw new Error('No active service worker available for sync trigger.');
        }

        worker.postMessage({ type: 'SYNC_POSTS_NOW' });
    });
}

async function waitForOnlineStatusEndpoint(page) {
    await expect.poll(async () => {
        try {
            const response = await page.request.get('/_e2e/offline-queue/status');
            return response.ok();
        } catch {
            return false;
        }
    }, {
        timeout: 10_000,
    }).toBe(true);
}

test.beforeEach(async ({ page }) => {
    await page.goto('/_e2e/offline-queue');
    await page.waitForLoadState('networkidle');
    await page.evaluate(async () => {
        if (!('serviceWorker' in navigator)) {
            throw new Error('Service workers are not supported in this browser context.');
        }

        await navigator.serviceWorker.register('/sw.js?v=e2e-playwright&world=default');
        await navigator.serviceWorker.ready;
    });
    await page.reload({ waitUntil: 'networkidle' });
    await page.evaluate(async () => {
        const registration = await navigator.serviceWorker.getRegistration();

        if (!registration?.active) {
            throw new Error('No active service worker registration available for E2E queue tests.');
        }
    });

    await page.evaluate(() => {
        window.__e2eSwEvents = [];

        if (window.__e2eSwEventCollectorBound === true) {
            return;
        }

        window.__e2eSwEventCollectorBound = true;

        navigator.serviceWorker.addEventListener('message', (event) => {
            const type = String(event.data?.type || '').trim();

            if (type === '') {
                return;
            }

            window.__e2eSwEvents.push(type);
        });
    });
});

test('queued post retries after 419 via re-signing and syncs successfully', async ({ page, context }) => {
    const content = `E2E retry success ${Date.now()}`;

    await context.setOffline(true);
    await page.fill('textarea[name="content"]', content);
    await page.click('button[type="submit"]');

    await expect.poll(async () => getQueuedPostCount(page), { timeout: 10_000 }).toBe(1);

    await mutateFirstQueuedPost(page, 'success');

    await context.setOffline(false);
    await waitForOnlineStatusEndpoint(page);
    let queueFlushed = false;

    for (let attempt = 1; attempt <= 3; attempt += 1) {
        await startManualSync(page);
        await page.waitForFunction((minSyncStartEvents) => {
            if (!Array.isArray(window.__e2eSwEvents)) {
                return false;
            }

            const syncStartCount = window.__e2eSwEvents.filter((type) => type === 'POST_SYNC_STARTED').length;
            return syncStartCount >= minSyncStartEvents;
        }, attempt, {
            timeout: 10_000,
        });

        try {
            await expect.poll(async () => getQueuedPostCount(page), {
                timeout: 8_000,
            }).toBe(0);
            queueFlushed = true;
            break;
        } catch {
            if (attempt === 3) {
                break;
            }

            await mutateFirstQueuedPost(page, 'success');
        }
    }

    expect(queueFlushed).toBeTruthy();
    await page.waitForFunction(() => Array.isArray(window.__e2eSwEvents) && window.__e2eSwEvents.includes('POST_SYNC_SUCCESS'));

    const statusResponse = await page.request.get('/_e2e/offline-queue/status');
    expect(statusResponse.ok()).toBeTruthy();
    const payload = await statusResponse.json();

    expect(payload.last_submission).toBe(content);

    const events = await page.evaluate(() => window.__e2eSwEvents || []);
    expect(events).toContain('POST_SYNC_AUTH_RETRY');
    expect(events).toContain('POST_SYNC_SUCCESS');
});

test('queued post emits auth-required and remains queued when re-signing needs login', async ({ page, context }) => {
    await context.setOffline(true);
    await page.fill('textarea[name="content"]', `E2E auth required ${Date.now()}`);
    await page.click('button[type="submit"]');

    await expect.poll(async () => getQueuedPostCount(page), { timeout: 10_000 }).toBe(1);

    await mutateFirstQueuedPost(page, 'auth-required');

    await context.setOffline(false);
    await waitForOnlineStatusEndpoint(page);
    await startManualSync(page);
    await expect.poll(async () => Number((await getFirstQueuedPost(page))?.retry_count || 0), {
        timeout: 20_000,
    }).toBeGreaterThan(0);
    await expect.poll(async () => String((await getFirstQueuedPost(page))?.next_retry_at || ''), {
        timeout: 20_000,
    }).not.toBe('');

    expect(await getQueuedPostCount(page)).toBe(1);
    const queueItem = await getFirstQueuedPost(page);
    const events = await page.evaluate(() => window.__e2eSwEvents || []);

    expect(Number(queueItem?.retry_count || 0)).toBeGreaterThan(0);
    expect(typeof queueItem?.next_retry_at).toBe('string');
    expect(String(queueItem?.next_retry_at || '')).not.toBe('');
    expect(events.some((type) => (
        type === 'POST_SYNC_AUTH_REQUIRED'
        || type === 'POST_SYNC_RETRY_SCHEDULED'
    ))).toBeTruthy();
});
