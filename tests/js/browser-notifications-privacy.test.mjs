import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const SOURCE_PATH = new URL('../../resources/js/app/browser-notifications.js', import.meta.url);

test('browser push synchronizes broad device metadata without persisting a raw user agent', async () => {
    const source = await readFile(SOURCE_PATH, 'utf8');

    assert.match(source, /device_name: resolvePushDeviceName\(\)/);
    assert.match(source, /ensureActiveServiceWorkerRegistration/);
    assert.match(source, /Firefox/);
    assert.match(source, /Mobilgerät/);
    assert.doesNotMatch(source, /device_name:\s*navigator\.userAgent/);
});

test('browser push releases endpoint ownership with subscription credentials', async () => {
    const source = await readFile(SOURCE_PATH, 'utf8');

    assert.match(source, /public_key: payload\?\.publicKey \|\| null/);
    assert.match(source, /auth_token: payload\?\.authToken \|\| null/);
    assert.match(source, /setupBrowserNotificationLogoutCleanup\(\)/);
    assert.match(source, /HTMLFormElement\.prototype\.submit\.call\(form\)/);
    assert.match(source, /PUSH_DEVICE_OPT_OUT_KEY = 'c76:push-device-opt-out'/);
    assert.match(source, /unsubscribeBrowserPush\(subscription, false, true, false\)/);
    assert.match(source, /HTMLFormElement\.prototype\.submit\.call\(removeForm\)/);
});

test('current push device is matched by a SHA-256 endpoint digest', async () => {
    const source = await readFile(SOURCE_PATH, 'utf8');

    assert.match(source, /crypto\.subtle\.digest\('SHA-256'/);
    assert.match(source, /deviceNode\.dataset\.endpointHash !== endpointHash/);
});
