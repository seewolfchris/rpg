if (typeof self.importScripts !== 'function') {
    throw new Error('importScripts is required to bootstrap this service worker.');
}

self.importScripts('/js/sw/runtime-core.js');
self.importScripts('/js/sw/runtime-queue.js');
