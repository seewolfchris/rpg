import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import {
    enforcePrivateDataBoundaryOnAuthChange,
    renderPrivateDataBoundaryFailure,
} from '../../resources/js/app/privacy-boundary.js';

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

test('auth boundary cleanup removes managed drafts and session context but preserves boundary keys', async () => {
    const harness = createBrowserHarness({
        userBoundary: '23',
        sessionBoundary: 'session-current',
        previousBoundary: 'guest|session-old',
        deleteDatabaseMode: 'success',
    });
    harness.localStorage.setItem('c76:post-draft:scene-7-user-23-new', '{"content":"private"}');
    harness.localStorage.setItem('unrelated-preference', 'keep');
    harness.sessionStorage.setItem('c76:last-world-slug', 'chroniken-der-asche');
    harness.sessionStorage.setItem('c76:scene-reading-mode:7', '1');

    const cleanupCompleted = await enforcePrivateDataBoundaryOnAuthChange({
        postMessageToActiveServiceWorker: async () => undefined,
    });

    assert.equal(cleanupCompleted, true);
    assert.equal(harness.localStorage.getItem('c76:post-draft:scene-7-user-23-new'), null);
    assert.equal(harness.sessionStorage.getItem('c76:last-world-slug'), null);
    assert.equal(harness.sessionStorage.getItem('c76:scene-reading-mode:7'), null);
    assert.equal(harness.localStorage.getItem('unrelated-preference'), 'keep');
    assert.equal(harness.localStorage.getItem(BOUNDARY_KEY), '23|session-current');
});

test('application boot fails closed before offline queue and service worker setup', async () => {
    const source = await readFile(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    const bootSource = source.slice(source.indexOf('const bootApplication = async () =>'));
    const boundaryIndex = bootSource.indexOf('await enforcePrivateDataBoundaryOnAuthChange');
    const failureGuardIndex = bootSource.indexOf('if (!privateDataBoundaryReady)');
    const failureMessageIndex = bootSource.indexOf('renderPrivateDataBoundaryFailure();');
    const failureReturnIndex = bootSource.indexOf('return;', failureMessageIndex);

    assert.ok(boundaryIndex >= 0, 'boot must enforce the private-data boundary');
    assert.ok(failureGuardIndex > boundaryIndex, 'boot must check the boundary result');
    assert.ok(failureMessageIndex > failureGuardIndex, 'failed cleanup must render an accessible warning');
    assert.ok(failureReturnIndex > failureMessageIndex, 'failed cleanup must stop application boot');

    for (const setupCall of [
        "document.addEventListener('htmx:afterSwap'",
        'setupOfflinePostQueue();',
        'setupOnlineSyncTrigger();',
        'setupServiceWorkerMessageHandling();',
        'setupOfflineQueuePreferenceToggle();',
        'serviceWorkerRuntime.setupServiceWorkerLogoutCleanup();',
        'await serviceWorkerRuntime.registerServiceWorker();',
    ]) {
        const setupIndex = bootSource.indexOf(setupCall);

        assert.ok(setupIndex > failureReturnIndex, `${setupCall} must only run after successful cleanup`);
    }
});

test('boundary failure keeps non-persistent navigation and form controls available', async () => {
    const source = await readFile(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    const bootSource = source.slice(source.indexOf('const bootApplication = async () =>'));
    const boundaryIndex = bootSource.indexOf('await enforcePrivateDataBoundaryOnAuthChange');

    for (const safeSetupCall of [
        'setupFormSubmitConfirmDialogs();',
        'setupMobileSheetNavigation();',
        'setupPostEditorEnhancements();',
        'setupInlineImageControls();',
    ]) {
        const setupIndex = bootSource.indexOf(safeSetupCall);

        assert.ok(setupIndex >= 0 && setupIndex < boundaryIndex, `${safeSetupCall} must remain available on boundary failure`);
    }
});

test('boundary failure renders one focusable assertive alert', () => {
    const harness = createFailureMessageHarness();

    const firstFailure = renderPrivateDataBoundaryFailure();
    const secondFailure = renderPrivateDataBoundaryFailure();

    assert.equal(firstFailure, secondFailure);
    assert.equal(harness.mainContent.children.length, 1);
    assert.equal(firstFailure?.attributes.get('role'), 'alert');
    assert.equal(firstFailure?.attributes.get('aria-live'), 'assertive');
    assert.equal(firstFailure?.attributes.get('tabindex'), '-1');
    assert.equal(firstFailure?.focused, true);
    assert.match(firstFailure?.children[1]?.textContent || '', /Offline-Funktionen bleiben deaktiviert/);
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

    const buildStorage = (storageMap = new Map()) => ({
        get length() {
            return storageMap.size;
        },
        key(index) {
            return Array.from(storageMap.keys())[index] ?? null;
        },
        getItem(key) {
            return storageMap.has(key) ? storageMap.get(key) : null;
        },
        setItem(key, value) {
            storageMap.set(String(key), String(value));
        },
        removeItem(key) {
            storageMap.delete(String(key));
        },
    });
    const localStorage = buildStorage(storage);
    const sessionStorage = buildStorage();

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
        sessionStorage,
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
        sessionStorage,
    };
}

function createFailureMessageHarness() {
    class FakeElement {
        constructor(tagName) {
            this.tagName = String(tagName).toUpperCase();
            this.attributes = new Map();
            this.children = [];
            this.className = '';
            this.dataset = {};
            this.focused = false;
            this.textContent = '';
        }

        append(...children) {
            this.children.push(...children);
        }

        prepend(child) {
            this.children.unshift(child);
        }

        focus() {
            this.focused = true;
        }

        querySelector(selector) {
            if (selector !== '[data-private-data-boundary-failure]') {
                return null;
            }

            return this.children.find((child) => child.dataset.privateDataBoundaryFailure === 'true') || null;
        }

        setAttribute(name, value) {
            this.attributes.set(name, String(value));
        }
    }

    const mainContent = new FakeElement('main');

    globalThis.HTMLElement = FakeElement;
    globalThis.document = {
        createElement: (tagName) => new FakeElement(tagName),
        querySelector: (selector) => selector === '#app-main' ? mainContent : null,
    };

    return {
        mainContent,
    };
}
