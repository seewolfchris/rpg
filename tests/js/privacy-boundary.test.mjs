import assert from 'node:assert/strict';
import test from 'node:test';
import { enforcePrivateDataBoundaryOnAuthChange } from '../../resources/js/app/privacy-boundary.js';

const BOUNDARY_KEY = 'c76:auth-user-boundary';
const BOUNDARY_PENDING_KEY = 'c76:auth-user-boundary-pending';

test('auth boundary is not finalized when IndexedDB deletion is blocked', async () => {
    const harness = createBrowserHarness({
        userBoundary: '42',
        sessionBoundary: 'session-new',
        previousBoundary: 'guest|session-old',
        deleteDatabaseMode: 'blocked',
    });

    const cleanupCompleted = await enforcePrivateDataBoundaryOnAuthChange({
        postMessageToActiveServiceWorker: async () => undefined,
    });

    assert.equal(cleanupCompleted, false);
    assert.equal(harness.localStorage.getItem(BOUNDARY_KEY), 'guest|session-old');
    assert.equal(harness.localStorage.getItem(BOUNDARY_PENDING_KEY), '42|session-new');
});

test('auth boundary is finalized and pending state removed after successful cleanup', async () => {
    const harness = createBrowserHarness({
        userBoundary: '17',
        sessionBoundary: 'session-final',
        previousBoundary: 'guest|session-old',
        pendingBoundary: '17|session-final',
        deleteDatabaseMode: 'success',
    });

    const cleanupCompleted = await enforcePrivateDataBoundaryOnAuthChange({
        postMessageToActiveServiceWorker: async () => undefined,
    });

    assert.equal(cleanupCompleted, true);
    assert.equal(harness.localStorage.getItem(BOUNDARY_KEY), '17|session-final');
    assert.equal(harness.localStorage.getItem(BOUNDARY_PENDING_KEY), null);
});

function createBrowserHarness({
    userBoundary,
    sessionBoundary,
    previousBoundary = null,
    pendingBoundary = null,
    deleteDatabaseMode = 'success',
}) {
    class FakeMetaElement {
        constructor(content) {
            this.content = content;
        }
    }

    const storage = new Map();
    if (typeof previousBoundary === 'string') {
        storage.set(BOUNDARY_KEY, previousBoundary);
    }
    if (typeof pendingBoundary === 'string') {
        storage.set(BOUNDARY_PENDING_KEY, pendingBoundary);
    }

    const localStorage = {
        getItem(key) {
            return storage.has(key) ? storage.get(key) : null;
        },
        setItem(key, value) {
            storage.set(String(key), String(value));
        },
        removeItem(key) {
            storage.delete(String(key));
        },
    };

    const document = {
        querySelector(selector) {
            if (selector === 'meta[name="auth-user-id"]') {
                return new FakeMetaElement(userBoundary);
            }

            if (selector === 'meta[name="auth-session-boundary"]') {
                return new FakeMetaElement(sessionBoundary);
            }

            return null;
        },
    };

    const window = {
        localStorage,
        caches: {
            keys: async () => ['chroniken-pages-e2e-private', 'chroniken-content-e2e-private'],
            delete: async () => true,
        },
        indexedDB: {
            deleteDatabase: () => {
                const request = {};
                queueMicrotask(() => {
                    if (deleteDatabaseMode === 'blocked') {
                        request.onblocked?.();
                        return;
                    }

                    if (deleteDatabaseMode === 'error') {
                        request.onerror?.();
                        return;
                    }

                    request.onsuccess?.();
                });

                return request;
            },
        },
    };

    globalThis.HTMLMetaElement = FakeMetaElement;
    globalThis.document = document;
    globalThis.window = window;

    return {
        localStorage,
    };
}
