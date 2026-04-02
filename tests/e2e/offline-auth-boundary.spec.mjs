import { expect, test } from '@playwright/test';

test('offline fallback page resolves world and source path context', async ({ page }) => {
    await page.goto('/offline.html?world=chroniken-der-asche&path=%2Fw%2Fchroniken-der-asche%2Fcampaigns%2F1%2Fscenes%2F1');

    await expect(page.locator('#offline-world-label')).toHaveText('Chroniken der Asche');
    await expect(page.locator('#offline-source-path')).toHaveText('/w/chroniken-der-asche/campaigns/1/scenes/1');
});

test('auth boundary change clears private caches and offline queue database', async ({ page }) => {
    await page.goto('/login');
    await page.waitForLoadState('networkidle');

    await page.evaluate(async () => {
        localStorage.setItem('c76:auth-user-boundary', 'user-42');

        const pageCache = await caches.open('chroniken-pages-e2e-private');
        await pageCache.put('/_e2e/private-page-probe', new Response('private-page'));

        const contentCache = await caches.open('chroniken-content-e2e-private');
        await contentCache.put('/_e2e/private-content-probe', new Response('private-content'));

        await new Promise((resolve, reject) => {
            const openRequest = indexedDB.open('chroniken-pbp', 2);

            openRequest.onupgradeneeded = () => {
                const database = openRequest.result;

                if (!database.objectStoreNames.contains('postQueue')) {
                    database.createObjectStore('postQueue', {
                        keyPath: 'id',
                        autoIncrement: true,
                    });
                }

                if (!database.objectStoreNames.contains('postDeadLetters')) {
                    database.createObjectStore('postDeadLetters', {
                        keyPath: 'id',
                        autoIncrement: true,
                    });
                }
            };

            openRequest.onsuccess = () => {
                const database = openRequest.result;
                const transaction = database.transaction('postQueue', 'readwrite');
                transaction.objectStore('postQueue').add({
                    url: '/_e2e/offline-queue/submit',
                    method: 'POST',
                    entries: [['content', 'probe']],
                });

                transaction.oncomplete = () => {
                    database.close();
                    resolve(undefined);
                };

                transaction.onerror = () => {
                    database.close();
                    reject(transaction.error || new Error('Could not seed queue record.'));
                };
            };

            openRequest.onerror = () => {
                reject(openRequest.error || new Error('Could not open queue database.'));
            };
        });
    });

    await page.reload({ waitUntil: 'networkidle' });
    await expect.poll(async () => {
        return page.evaluate(async () => {
            const cacheKeys = await caches.keys();
            const privateCacheCount = cacheKeys.filter((cacheKey) => (
                cacheKey.startsWith('chroniken-pages-') || cacheKey.startsWith('chroniken-content-')
            )).length;

            const queueCount = await new Promise((resolve) => {
                const openRequest = indexedDB.open('chroniken-pbp');

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
                        resolve(-1);
                    };
                };

                openRequest.onerror = () => resolve(-1);
            });

            return {
                privateCacheCount,
                queueCount,
            };
        });
    }, {
        timeout: 20_000,
    }).toEqual({
        privateCacheCount: 0,
        queueCount: 0,
    });

    const state = await page.evaluate(async () => {
        const cacheKeys = await caches.keys();
        const privateCacheKeys = cacheKeys.filter((cacheKey) => (
            cacheKey.startsWith('chroniken-pages-') || cacheKey.startsWith('chroniken-content-')
        ));

        const queueState = await new Promise((resolve) => {
            const openRequest = indexedDB.open('chroniken-pbp');

            openRequest.onsuccess = () => {
                const database = openRequest.result;
                const stores = Array.from(database.objectStoreNames);

                if (!stores.includes('postQueue')) {
                    database.close();
                    resolve({ stores, queueCount: 0 });
                    return;
                }

                const transaction = database.transaction('postQueue', 'readonly');
                const countRequest = transaction.objectStore('postQueue').count();

                countRequest.onsuccess = () => {
                    const queueCount = Number(countRequest.result || 0);
                    transaction.oncomplete = () => {
                        database.close();
                        resolve({ stores, queueCount });
                    };
                };

                countRequest.onerror = () => {
                    database.close();
                    resolve({ stores, queueCount: -1 });
                };
            };

            openRequest.onerror = () => {
                resolve({ stores: ['__error__'], queueCount: -1 });
            };
        });

        return {
            privateCacheKeys,
            queueState,
        };
    });

    expect(state.privateCacheKeys).toEqual([]);
    expect(state.queueState.queueCount).toBe(0);
});
