import './bootstrap';
import { characterSheetForm, registerCharacterSheetComponent } from './character-sheet';
import { createQueueModule } from './immersion/queue';
import { setupSceneThreadReadingMode } from './immersion/reading-mode';
import { setupBrowserNotifications } from './app/browser-notifications';
import { setupFormSubmitConfirmDialogs } from './app/confirm-dialogs';
import { getCsrfToken } from './app/csrf';
import { setupInlineImageControls } from './app/inline-image-controls';
import { setupAtmosphericParallax } from './app/parallax';
import { setupMobileSheetNavigation } from './app/navigation/mobile-sheet';
import { setupPostEditorEnhancements } from './app/post-editor-enhancements';
import { registerPostFormStateComponent } from './app/post-form-state';
import {
    enforcePrivateDataBoundaryOnAuthChange,
    renderPrivateDataBoundaryFailure,
} from './app/privacy-boundary';
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
        return false;
    }

    const rawValue = String(preferenceNode.content || '').trim().toLowerCase();

    if (rawValue === '0' || rawValue === 'false' || rawValue === 'off' || rawValue === 'no') {
        return false;
    }

    return rawValue === '1' || rawValue === 'true' || rawValue === 'on' || rawValue === 'yes';
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
    registerPostFormStateComponent(window.Alpine);
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
    ensureActiveServiceWorkerRegistration: serviceWorkerRuntime.registerServiceWorker,
    postMessageToActiveServiceWorker: serviceWorkerRuntime.postMessageToActiveServiceWorker,
    resolveOfflineQueueEnabled: resolveOfflineQueueEnabledFromDocument,
});

const handleHtmxAfterSwap = (event) => {
    const target = event.detail?.target;

    if (window.Alpine && target instanceof HTMLElement) {
        window.Alpine.initTree(target);
    }

    persistActiveWorldSlugContext();
    setupSceneThreadReadingMode();
    setupAtmosphericParallax();
    setupMobileSheetNavigation();
    setupPostEditorEnhancements();
    setupInlineImageControls(target);
    setupOfflinePostQueue();
    setupOfflineQueuePreferenceToggle();
    void renderDeadLetterPanel();
    void renderOfflineQueueStatusPanel();
};

const bootApplication = async () => {
    setupFormSubmitConfirmDialogs();
    setupMobileSheetNavigation();
    setupPostEditorEnhancements();
    setupInlineImageControls();

    const privateDataBoundaryReady = await enforcePrivateDataBoundaryOnAuthChange({
        postMessageToActiveServiceWorker: serviceWorkerRuntime.postMessageToActiveServiceWorker,
    });

    if (!privateDataBoundaryReady) {
        renderPrivateDataBoundaryFailure();

        return;
    }

    document.addEventListener('htmx:afterSwap', handleHtmxAfterSwap);
    persistActiveWorldSlugContext();
    setupSceneThreadReadingMode();
    setupAtmosphericParallax();
    setupPwaInstallPrompt();
    setupOfflinePostQueue();
    setupOnlineSyncTrigger();
    setupServiceWorkerMessageHandling();
    setupOfflineQueuePreferenceToggle();
    serviceWorkerRuntime.setupServiceWorkerLogoutCleanup();
    await renderDeadLetterPanel();
    await renderOfflineQueueStatusPanel();

    if (resolveOfflineQueueEnabledFromDocument()) {
        await serviceWorkerRuntime.registerServiceWorker();
        await serviceWorkerRuntime.syncOfflineQueuePreference(true);
        await serviceWorkerRuntime.warmOfflineReadingCache();
    } else {
        await serviceWorkerRuntime.syncOfflineQueuePreference(false);
        await serviceWorkerRuntime.unregisterServiceWorkerWhenUnused();
    }

    setupBrowserNotifications({
        getActiveServiceWorkerRegistration: serviceWorkerRuntime.getActiveServiceWorkerRegistration,
        ensureActiveServiceWorkerRegistration: serviceWorkerRuntime.registerServiceWorker,
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
