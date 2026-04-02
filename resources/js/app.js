import './bootstrap';
import { characterSheetForm, registerCharacterSheetComponent } from './character-sheet';
import { createQueueModule } from './immersion/queue';
import { setupSceneThreadReadingMode } from './immersion/reading-mode';
import { setupBrowserNotifications } from './app/browser-notifications';
import { getCsrfToken } from './app/csrf';
import { setupAtmosphericParallax } from './app/parallax';
import { setupPostEditorEnhancements } from './app/post-editor-enhancements';
import { enforcePrivateDataBoundaryOnAuthChange } from './app/privacy-boundary';
import { setupPwaInstallPrompt } from './app/pwa-install';
import { createServiceWorkerRuntime } from './app/service-worker-runtime';
import {
    DEFAULT_WORLD_SLUG,
    persistActiveWorldSlugContext,
    resolveActiveWorldSlug,
    resolveStoredWorldSlugContext,
} from './app/world-context';

window.characterSheetForm = characterSheetForm;

if (window.Alpine) {
    registerCharacterSheetComponent(window.Alpine);
    window.Alpine.start();
}

const serviceWorkerRuntime = createServiceWorkerRuntime({
    resolveActiveWorldSlug,
    resolveStoredWorldSlugContext,
    defaultWorldSlug: DEFAULT_WORLD_SLUG,
});

const {
    setupOfflinePostQueue,
    setupOnlineSyncTrigger,
    setupServiceWorkerMessageHandling,
    renderDeadLetterPanel,
    renderOfflineQueueStatusPanel,
    triggerQueuedPostSync,
} = createQueueModule({
    getActiveServiceWorkerRegistration: serviceWorkerRuntime.getActiveServiceWorkerRegistration,
});

document.addEventListener('htmx:afterSwap', (event) => {
    const target = event.detail?.target;

    if (window.Alpine && target instanceof HTMLElement) {
        window.Alpine.initTree(target);
    }

    persistActiveWorldSlugContext();
    setupSceneThreadReadingMode();
    setupAtmosphericParallax();
    setupPostEditorEnhancements();
    setupOfflinePostQueue();
    void renderDeadLetterPanel();
    void renderOfflineQueueStatusPanel();
});

const bootApplication = async () => {
    persistActiveWorldSlugContext();
    setupSceneThreadReadingMode();
    setupAtmosphericParallax();
    setupPostEditorEnhancements();
    setupPwaInstallPrompt();
    setupOfflinePostQueue();
    setupOnlineSyncTrigger();
    setupServiceWorkerMessageHandling();
    serviceWorkerRuntime.setupServiceWorkerLogoutCleanup();
    await enforcePrivateDataBoundaryOnAuthChange({
        postMessageToActiveServiceWorker: serviceWorkerRuntime.postMessageToActiveServiceWorker,
    });
    await renderDeadLetterPanel();
    await renderOfflineQueueStatusPanel();

    await serviceWorkerRuntime.registerServiceWorker();
    await serviceWorkerRuntime.warmOfflineReadingCache();
    setupBrowserNotifications({
        getActiveServiceWorkerRegistration: serviceWorkerRuntime.getActiveServiceWorkerRegistration,
        resolveActiveWorldSlug,
        resolveStoredWorldSlugContext,
        defaultWorldSlug: DEFAULT_WORLD_SLUG,
        getCsrfToken,
    });

    if (navigator.onLine) {
        await triggerQueuedPostSync();
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        bootApplication();
    });
} else {
    bootApplication();
}
