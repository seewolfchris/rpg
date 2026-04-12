import './bootstrap';
import { characterSheetForm, registerCharacterSheetComponent } from './character-sheet';
import { createQueueModule } from './immersion/queue';
import { setupSceneThreadReadingMode } from './immersion/reading-mode';
import { setupBrowserNotifications } from './app/browser-notifications';
import { setupFormSubmitConfirmDialogs } from './app/confirm-dialogs';
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

function resolveOfflineQueueEnabledFromDocument() {
    const preferenceNode = document.querySelector('meta[name="offline-queue-enabled"]');

    if (!(preferenceNode instanceof HTMLMetaElement)) {
        return true;
    }

    const rawValue = String(preferenceNode.content || '').trim().toLowerCase();

    if (rawValue === '0' || rawValue === 'false' || rawValue === 'off' || rawValue === 'no') {
        return false;
    }

    return true;
}

function resolveAuthBoundaryKeyFromDocument() {
    const userBoundaryNode = document.querySelector('meta[name="auth-user-id"]');
    const sessionBoundaryNode = document.querySelector('meta[name="auth-session-boundary"]');

    const userBoundary = userBoundaryNode instanceof HTMLMetaElement
        ? String(userBoundaryNode.content || '').trim() || 'guest'
        : 'guest';
    const sessionBoundary = sessionBoundaryNode instanceof HTMLMetaElement
        ? String(sessionBoundaryNode.content || '').trim() || 'session-unknown'
        : 'session-unknown';

    return `${userBoundary}|${sessionBoundary}`;
}

window.characterSheetForm = characterSheetForm;

if (window.Alpine) {
    registerCharacterSheetComponent(window.Alpine);
    window.Alpine.start();
}

const serviceWorkerRuntime = createServiceWorkerRuntime({
    resolveActiveWorldSlug,
    resolveStoredWorldSlugContext,
    defaultWorldSlug: DEFAULT_WORLD_SLUG,
    resolveOfflineQueueEnabled: resolveOfflineQueueEnabledFromDocument,
    resolveAuthBoundaryKey: resolveAuthBoundaryKeyFromDocument,
});

const {
    setupOfflinePostQueue,
    setupOnlineSyncTrigger,
    setupServiceWorkerMessageHandling,
    setupOfflineQueuePreferenceToggle,
    renderDeadLetterPanel,
    renderOfflineQueueStatusPanel,
    triggerQueuedPostSync,
} = createQueueModule({
    getActiveServiceWorkerRegistration: serviceWorkerRuntime.getActiveServiceWorkerRegistration,
    postMessageToActiveServiceWorker: serviceWorkerRuntime.postMessageToActiveServiceWorker,
    resolveOfflineQueueEnabled: resolveOfflineQueueEnabledFromDocument,
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
    setupOfflineQueuePreferenceToggle();
    void renderDeadLetterPanel();
    void renderOfflineQueueStatusPanel();
});

const bootApplication = async () => {
    setupFormSubmitConfirmDialogs();
    persistActiveWorldSlugContext();
    setupSceneThreadReadingMode();
    setupAtmosphericParallax();
    setupPostEditorEnhancements();
    setupPwaInstallPrompt();
    setupOfflinePostQueue();
    setupOnlineSyncTrigger();
    setupServiceWorkerMessageHandling();
    setupOfflineQueuePreferenceToggle();
    serviceWorkerRuntime.setupServiceWorkerLogoutCleanup();
    await enforcePrivateDataBoundaryOnAuthChange({
        postMessageToActiveServiceWorker: serviceWorkerRuntime.postMessageToActiveServiceWorker,
    });
    await renderDeadLetterPanel();
    await renderOfflineQueueStatusPanel();

    await serviceWorkerRuntime.registerServiceWorker();
    await serviceWorkerRuntime.syncOfflineQueuePreference();
    await serviceWorkerRuntime.warmOfflineReadingCache();
    setupBrowserNotifications({
        getActiveServiceWorkerRegistration: serviceWorkerRuntime.getActiveServiceWorkerRegistration,
        resolveActiveWorldSlug,
        resolveStoredWorldSlugContext,
        defaultWorldSlug: DEFAULT_WORLD_SLUG,
        getCsrfToken,
    });

    if (navigator.onLine && resolveOfflineQueueEnabledFromDocument()) {
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
