import './bootstrap';
import { characterSheetForm, registerCharacterSheetComponent } from './character-sheet';
import {
    DEAD_LETTER_MERGE_APPEND,
    DEAD_LETTER_MERGE_CANCEL,
    DEAD_LETTER_MERGE_REPLACE,
    hasEditorContent,
    mergeDeadLetterContent,
} from './offline-dead-letter.mjs';
import {
    createPostDraftStorageKey,
    hasPostDraftContent,
    normalizePostDraftForRestore,
    parsePostDraftPayload,
} from './post-editor-draft';

const QUEUE_DB_NAME = 'chroniken-pbp';
const QUEUE_STORE_NAME = 'postQueue';
const DEAD_LETTER_STORE_NAME = 'postDeadLetters';
const SYNC_TAG_POSTS = 'pbp-sync-posts';
const OFFLINE_POST_FORM_SELECTOR = 'form[data-offline-post-form]';
const OFFLINE_POST_CONTENT_SELECTOR = `${OFFLINE_POST_FORM_SELECTOR} textarea[name="content"]`;
const OFFLINE_DEAD_LETTER_PANEL_ID = 'offline-dead-letter-panel';
const OFFLINE_QUEUE_STATUS_PANEL_ID = 'offline-queue-status-panel';
const CACHEABLE_LINK_SELECTOR = 'a[href*="/campaigns/"][href*="/scenes/"], a[href*="/characters/"]';
const PWA_INSTALL_BUTTON_SELECTOR = '[data-pwa-install-button]';
const BROWSER_NOTIFICATION_ROOT_SELECTOR = '[data-browser-notifications]';
const BROWSER_NOTIFICATION_STATUS_SELECTOR = '[data-browser-notifications-status]';
const BROWSER_NOTIFICATION_ENABLE_SELECTOR = '[data-browser-notifications-enable]';
const SCENE_THREAD_READING_MODE_SELECTOR = '[data-scene-thread-reading-mode]';
const READING_MODE_TOGGLE_SELECTOR = '[data-reading-mode-toggle]';
const READING_MODE_FULLSCREEN_SELECTOR = '[data-reading-mode-fullscreen]';
const READING_POST_SELECTOR = '[data-reading-post-anchor]';
const READING_PROGRESS_BOOKMARK_SELECTOR = '[data-reading-progress-bookmark]';
const READING_PROGRESS_VALUE_SELECTOR = '[data-reading-progress-value]';
const READING_PROGRESS_PERCENT_SELECTOR = '[data-reading-progress-percent]';
const READING_PROGRESS_BAR_SELECTOR = '[data-reading-progress-bar]';
const PARALLAX_SCENE_SELECTOR = '[data-parallax-scene]';
const POST_EDITOR_SELECTOR = 'form[data-post-editor]';
const POST_PREVIEW_DEBOUNCE_MS = 450;
const POST_DRAFT_DEBOUNCE_MS = 350;

let swRegistration = null;
let deferredInstallPrompt = null;
let browserNotificationConfig = null;

window.characterSheetForm = characterSheetForm;

if (window.Alpine) {
    registerCharacterSheetComponent(window.Alpine);
    window.Alpine.start();
}

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
    await renderDeadLetterPanel();
    await renderOfflineQueueStatusPanel();

    swRegistration = await registerServiceWorker();
    await warmOfflineReadingCache();
    setupBrowserNotifications();

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

function setupSceneThreadReadingMode() {
    const readingRoots = Array.from(document.querySelectorAll(SCENE_THREAD_READING_MODE_SELECTOR));

    if (readingRoots.length === 0) {
        document.body.classList.remove('is-reading-mode');
        return;
    }

    readingRoots.forEach((root) => {
        if (!(root instanceof HTMLElement)) {
            return;
        }

        const sceneId = String(root.dataset.sceneId || '').trim();
        const oocDetailsList = Array.from(root.querySelectorAll('details[data-ooc-thread]'))
            .filter((element) => element instanceof HTMLDetailsElement);

        if (!sceneId) {
            return;
        }

        const oocStorageKey = `c76:scene-ooc-open:${sceneId}`;
        const readingStorageKey = `c76:scene-reading-mode:${sceneId}`;
        const oocStored = readLocalStorageValue(oocStorageKey);
        const readingStored = readLocalStorageValue(readingStorageKey) === '1';

        if (oocDetailsList.length > 0) {
            oocDetailsList.forEach((oocDetails) => {
                if (oocStored === '1') {
                    oocDetails.open = true;
                } else if (oocStored === '0') {
                    oocDetails.open = false;
                }

                if (oocDetails.dataset.readingModeBound === '1') {
                    return;
                }

                oocDetails.dataset.readingModeBound = '1';
                oocDetails.addEventListener('toggle', () => {
                    writeLocalStorageValue(oocStorageKey, oocDetails.open ? '1' : '0');
                });
            });
        }

        applyReadingModeState(root, readingStored, false);
        bindReadingModeButtons(root, readingStorageKey);
        bindReadingKeyboardShortcuts(root, readingStorageKey);
        bindThreadPostReveal(root);
        bindReadingProgressBookmark(root);
        bindThreadHashFocusHandling(root);
        syncThreadPostHashFocus(root);
    });
}

function bindReadingModeButtons(root, readingStorageKey) {
    const toggleButtons = root.querySelectorAll(READING_MODE_TOGGLE_SELECTOR);
    const fullscreenButtons = root.querySelectorAll(READING_MODE_FULLSCREEN_SELECTOR);

    toggleButtons.forEach((buttonNode) => {
        if (!(buttonNode instanceof HTMLButtonElement) || buttonNode.dataset.readingBound === '1') {
            return;
        }

        buttonNode.dataset.readingBound = '1';
        buttonNode.addEventListener('click', () => {
            const enabled = !document.body.classList.contains('is-reading-mode');
            applyReadingModeState(root, enabled, true, readingStorageKey);
        });
    });

    fullscreenButtons.forEach((buttonNode) => {
        if (!(buttonNode instanceof HTMLButtonElement) || buttonNode.dataset.readingBound === '1') {
            return;
        }

        buttonNode.dataset.readingBound = '1';
        buttonNode.addEventListener('click', async () => {
            await requestReadingFullscreen(root);
        });
    });
}

function applyReadingModeState(root, enabled, persist = true, storageKey = '') {
    document.body.classList.toggle('is-reading-mode', enabled);
    root.dataset.readingModeEnabled = enabled ? '1' : '0';

    if (!enabled) {
        collectReadingPosts(root).forEach((post) => {
            post.classList.remove('is-reading-post-active');
        });
    }

    root.querySelectorAll(READING_MODE_TOGGLE_SELECTOR).forEach((buttonNode) => {
        if (!(buttonNode instanceof HTMLButtonElement)) {
            return;
        }

        const offLabel = String(buttonNode.dataset.stateOff || 'Romanmodus starten');
        const onLabel = String(buttonNode.dataset.stateOn || 'Romanmodus beenden');
        buttonNode.textContent = enabled ? onLabel : offLabel;
        buttonNode.setAttribute('aria-pressed', enabled ? 'true' : 'false');
    });

    if (persist && storageKey !== '') {
        writeLocalStorageValue(storageKey, enabled ? '1' : '0');
    }

    updateReadingProgressBookmark(root);
}

async function requestReadingFullscreen(root) {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    if (document.fullscreenElement) {
        await document.exitFullscreen().catch(() => {});
        return;
    }

    if (typeof root.requestFullscreen === 'function') {
        await root.requestFullscreen().catch(() => {});
    }
}

function bindReadingKeyboardShortcuts(root, readingStorageKey) {
    if (root.dataset.readingShortcutsBound === '1') {
        return;
    }

    root.dataset.readingShortcutsBound = '1';

    document.addEventListener('keydown', (event) => {
        if (!document.body.classList.contains('is-reading-mode')) {
            return;
        }

        if (event.metaKey || event.ctrlKey || event.altKey) {
            return;
        }

        if (hasOpenOverlayDialog()) {
            return;
        }

        const key = event.key.toLowerCase();
        const target = event.target;

        if (key === 'escape') {
            event.preventDefault();
            applyReadingModeState(root, false, true, readingStorageKey);
            return;
        }

        if (key !== 'n' && key !== 'p') {
            return;
        }

        if (hasInteractiveTypingTarget(target)) {
            return;
        }

        const posts = collectReadingPosts(root);

        if (posts.length === 0) {
            return;
        }

        event.preventDefault();
        const currentIndex = findNearestReadingPostIndex(posts);
        const nextIndex = key === 'n'
            ? Math.min(posts.length - 1, currentIndex + 1)
            : Math.max(0, currentIndex - 1);
        const targetPost = posts[nextIndex];

        focusReadingPost(posts, targetPost);
        updateReadingProgressBookmark(root);
    });
}

function hasInteractiveTypingTarget(target) {
    if (!(target instanceof HTMLElement)) {
        return false;
    }

    if (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)) {
        return true;
    }

    return Boolean(target.closest('button, a[href], summary, [role="button"], [role="link"]'));
}

function hasOpenOverlayDialog() {
    return document.body.classList.contains('has-overlay-modal')
        || document.querySelector('[data-overlay-modal-open="1"]') !== null;
}

function collectReadingPosts(root) {
    return Array.from(root.querySelectorAll(READING_POST_SELECTOR))
        .filter((node) => node instanceof HTMLElement);
}

function focusReadingPost(posts, targetPost, shouldScroll = true) {
    if (!(targetPost instanceof HTMLElement)) {
        return;
    }

    setActiveReadingPost(posts, targetPost);

    if (shouldScroll) {
        targetPost.scrollIntoView({
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
            block: 'start',
            inline: 'nearest',
        });
    }

    targetPost.focus({ preventScroll: shouldScroll });
}

function setActiveReadingPost(posts, activePost) {
    posts.forEach((post) => {
        post.classList.toggle('is-reading-post-active', post === activePost);
    });
}

function bindThreadHashFocusHandling(root) {
    if (root.dataset.readingHashBound === '1') {
        return;
    }

    root.dataset.readingHashBound = '1';
    window.addEventListener('hashchange', () => {
        syncThreadPostHashFocus(root);
    });
}

function syncThreadPostHashFocus(root) {
    const currentHash = window.location.hash;

    if (!currentHash.startsWith('#post-')) {
        return;
    }

    const targetPost = root.querySelector(currentHash);

    if (!(targetPost instanceof HTMLElement)) {
        return;
    }

    const posts = collectReadingPosts(root);
    focusReadingPost(posts, targetPost, false);
    updateReadingProgressBookmark(root);
}

function bindReadingProgressBookmark(root) {
    const progressNode = root.querySelector(READING_PROGRESS_BOOKMARK_SELECTOR);

    if (!(progressNode instanceof HTMLElement)) {
        return;
    }

    updateReadingProgressBookmark(root);

    if (progressNode.dataset.readingProgressBound === '1') {
        return;
    }

    progressNode.dataset.readingProgressBound = '1';
    let isQueued = false;

    const queueUpdate = () => {
        if (!document.body.classList.contains('is-reading-mode')) {
            return;
        }

        if (isQueued) {
            return;
        }

        isQueued = true;
        window.requestAnimationFrame(() => {
            isQueued = false;
            updateReadingProgressBookmark(root);
        });
    };

    window.addEventListener('scroll', queueUpdate, {
        passive: true,
    });
    window.addEventListener('resize', queueUpdate);
    root.addEventListener('focusin', queueUpdate);
}

function updateReadingProgressBookmark(root) {
    const progressNode = root.querySelector(READING_PROGRESS_BOOKMARK_SELECTOR);

    if (!(progressNode instanceof HTMLElement)) {
        return;
    }

    const posts = collectReadingPosts(root);
    const valueNode = progressNode.querySelector(READING_PROGRESS_VALUE_SELECTOR);
    const percentNode = progressNode.querySelector(READING_PROGRESS_PERCENT_SELECTOR);
    const barNode = progressNode.querySelector(READING_PROGRESS_BAR_SELECTOR);

    if (!document.body.classList.contains('is-reading-mode')) {
        if (barNode instanceof HTMLElement) {
            barNode.style.setProperty('--reading-progress-ratio', '0');
        }

        return;
    }

    if (posts.length === 0) {
        if (valueNode instanceof HTMLElement) {
            valueNode.textContent = 'Noch keine Posts';
        }

        if (percentNode instanceof HTMLElement) {
            percentNode.textContent = '0 %';
        }

        if (barNode instanceof HTMLElement) {
            barNode.style.setProperty('--reading-progress-ratio', '0');
        }

        return;
    }

    const currentIndex = findNearestReadingPostIndex(posts);
    const currentPosition = currentIndex + 1;
    const ratio = posts.length <= 1 ? 1 : currentIndex / (posts.length - 1);
    const percent = Math.max(0, Math.min(100, Math.round(ratio * 100)));

    if (valueNode instanceof HTMLElement) {
        valueNode.textContent = `Post ${currentPosition} / ${posts.length}`;
    }

    if (percentNode instanceof HTMLElement) {
        percentNode.textContent = `${percent} %`;
    }

    if (barNode instanceof HTMLElement) {
        barNode.style.setProperty('--reading-progress-ratio', String(ratio));
    }
}

function findNearestReadingPostIndex(posts) {
    const activeIndex = posts.findIndex((post) =>
        post.classList.contains('is-reading-post-active')
        || post === document.activeElement
        || post.contains(document.activeElement)
    );

    if (activeIndex >= 0) {
        return activeIndex;
    }

    const marker = window.innerHeight * 0.28;
    let bestIndex = 0;
    let bestDistance = Number.POSITIVE_INFINITY;

    posts.forEach((post, index) => {
        const distance = Math.abs(post.getBoundingClientRect().top - marker);

        if (distance < bestDistance) {
            bestDistance = distance;
            bestIndex = index;
        }
    });

    return bestIndex;
}

function bindThreadPostReveal(root) {
    const posts = Array.from(root.querySelectorAll(READING_POST_SELECTOR))
        .filter((node) => node instanceof HTMLElement);

    if (posts.length === 0) {
        return;
    }

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        posts.forEach((post) => post.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }

            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.18 });

    posts.forEach((post) => {
        if (post.dataset.readingRevealBound === '1') {
            return;
        }

        post.dataset.readingRevealBound = '1';
        post.classList.add('thread-post-reveal');
        observer.observe(post);
    });
}

function setupAtmosphericParallax() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    if (window.matchMedia('(pointer: coarse)').matches || window.innerWidth < 768) {
        return;
    }

    document.querySelectorAll(PARALLAX_SCENE_SELECTOR).forEach((scene) => {
        if (!(scene instanceof HTMLElement) || scene.dataset.parallaxBound === '1') {
            return;
        }

        scene.dataset.parallaxBound = '1';
        const layers = Array.from(scene.querySelectorAll('[data-parallax-layer]'))
            .filter((node) => node instanceof HTMLElement);

        if (layers.length === 0) {
            return;
        }

        scene.addEventListener('pointermove', (event) => {
            const rect = scene.getBoundingClientRect();
            const x = ((event.clientX - rect.left) / rect.width) - 0.5;
            const y = ((event.clientY - rect.top) / rect.height) - 0.5;

            layers.forEach((layer) => {
                const depth = Number(layer.dataset.parallaxDepth || '0.02');
                const translateX = x * depth * 22;
                const translateY = y * depth * 28;
                layer.style.transform = `translate3d(${translateX}px, ${translateY}px, 0)`;
            });
        });

        scene.addEventListener('pointerleave', () => {
            layers.forEach((layer) => {
                layer.style.transform = 'translate3d(0, 0, 0)';
            });
        });
    });
}

function setupPostEditorEnhancements() {
    document.querySelectorAll(POST_EDITOR_SELECTOR).forEach((formNode) => {
        if (!(formNode instanceof HTMLFormElement)) {
            return;
        }

        if (formNode.dataset.postEditorEnhanced === '1') {
            return;
        }

        formNode.dataset.postEditorEnhanced = '1';

        const contentField = formNode.querySelector('[data-post-content-input]');
        const formatField = formNode.querySelector('[data-post-content-format]');
        const postTypeField = formNode.querySelector('select[name="post_type"]');
        const characterField = formNode.querySelector('select[name="character_id"]');
        const icQuoteField = formNode.querySelector('input[name="ic_quote"]');
        const previewRoot = formNode.querySelector('[data-post-preview]');
        const previewStatusNode = formNode.querySelector('[data-post-preview-status]');
        const previewOutputNode = formNode.querySelector('[data-post-preview-output]');
        const previewUrl = String(formNode.dataset.previewUrl || '').trim();
        const draftKeySeed = String(formNode.dataset.draftKey || '').trim();
        const storageKey = createPostDraftStorageKey(draftKeySeed);

        if (!(contentField instanceof HTMLTextAreaElement) || !(formatField instanceof HTMLSelectElement)) {
            return;
        }

        const restoreDraftIfNeeded = () => {
            if (!storageKey || contentField.value.trim() !== '') {
                return;
            }

            const raw = readLocalStorageValue(storageKey);
            if (!raw) {
                return;
            }

            const parsed = parsePostDraftPayload(raw);

            if (!parsed) {
                removeLocalStorageValue(storageKey);
                return;
            }

            const restored = normalizePostDraftForRestore(parsed, {
                allowedFormats: Array.from(formatField.options).map((option) => option.value),
                allowedPostTypes: postTypeField instanceof HTMLSelectElement
                    ? Array.from(postTypeField.options).map((option) => option.value)
                    : [],
                allowedCharacterIds: characterField instanceof HTMLSelectElement
                    ? Array.from(characterField.options).map((option) => option.value)
                    : [],
            });

            if (!restored) {
                removeLocalStorageValue(storageKey);
                return;
            }

            contentField.value = restored.content;

            if (restored.content_format !== '') {
                formatField.value = restored.content_format;
            }

            if (postTypeField instanceof HTMLSelectElement && restored.post_type !== '') {
                postTypeField.value = restored.post_type;
            }

            if (characterField instanceof HTMLSelectElement && restored.character_id !== '') {
                characterField.value = restored.character_id;
            }

            if (icQuoteField instanceof HTMLInputElement) {
                icQuoteField.value = restored.ic_quote;
            }

            showSyncNotice('Lokaler Entwurf wiederhergestellt.', 'success');
        };

        const persistDraft = debounce(() => {
            if (!storageKey) {
                return;
            }

            const payload = {
                content: contentField.value,
                content_format: formatField.value,
                post_type: postTypeField instanceof HTMLSelectElement ? postTypeField.value : '',
                character_id: characterField instanceof HTMLSelectElement ? characterField.value : '',
                ic_quote: icQuoteField instanceof HTMLInputElement ? icQuoteField.value : '',
                saved_at: new Date().toISOString(),
            };

            if (!hasPostDraftContent(payload)) {
                removeLocalStorageValue(storageKey);
                return;
            }

            writeLocalStorageValue(storageKey, JSON.stringify(payload));
        }, POST_DRAFT_DEBOUNCE_MS);

        let previewAbortController = null;

        const renderPreview = debounce(async () => {
            if (!(previewRoot instanceof HTMLElement) || !(previewStatusNode instanceof HTMLElement) || !(previewOutputNode instanceof HTMLElement)) {
                return;
            }

            const format = String(formatField.value || '');
            const content = String(contentField.value || '');

            if (format !== 'markdown') {
                previewStatusNode.textContent = 'Live-Vorschau ist nur bei Markdown aktiv.';
                previewOutputNode.innerHTML = '<p class="text-stone-500">Für dieses Format ist keine Live-Vorschau aktiv.</p>';
                return;
            }

            if (content.trim() === '') {
                previewStatusNode.textContent = 'Schreibe Text, um eine Vorschau zu sehen.';
                previewOutputNode.innerHTML = '<p class="text-stone-500">Noch keine Vorschau verfügbar.</p>';
                return;
            }

            if (!previewUrl) {
                previewStatusNode.textContent = 'Live-Vorschau ist derzeit nicht verfügbar.';
                return;
            }

            if (previewAbortController instanceof AbortController) {
                previewAbortController.abort();
            }

            previewAbortController = new AbortController();
            previewStatusNode.textContent = 'Vorschau wird aktualisiert ...';

            try {
                const response = await fetch(previewUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        content_format: 'markdown',
                        content,
                    }),
                    signal: previewAbortController.signal,
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const payload = await response.json();
                const html = typeof payload?.html === 'string'
                    ? payload.html
                    : '<p class="text-stone-500">Keine Vorschau verfügbar.</p>';

                previewOutputNode.innerHTML = html;
                previewStatusNode.textContent = 'Live-Vorschau aktiv.';
            } catch (error) {
                if (error instanceof DOMException && error.name === 'AbortError') {
                    return;
                }

                previewStatusNode.textContent = 'Vorschau konnte nicht geladen werden.';
            }
        }, POST_PREVIEW_DEBOUNCE_MS);

        restoreDraftIfNeeded();
        renderPreview();

        contentField.addEventListener('input', () => {
            persistDraft();
            renderPreview();
        });

        formatField.addEventListener('change', () => {
            persistDraft();
            renderPreview();
        });

        if (postTypeField instanceof HTMLSelectElement) {
            postTypeField.addEventListener('change', persistDraft);
        }

        if (characterField instanceof HTMLSelectElement) {
            characterField.addEventListener('change', persistDraft);
        }

        if (icQuoteField instanceof HTMLInputElement) {
            icQuoteField.addEventListener('input', persistDraft);
        }

        formNode.addEventListener('submit', () => {
            if (storageKey) {
                removeLocalStorageValue(storageKey);
            }
        });
    });
}

async function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        return null;
    }

    try {
        const versionTag = encodeURIComponent(resolveServiceWorkerVersionTag());
        const registration = await navigator.serviceWorker.register(`/sw.js?v=${versionTag}`);
        const readyRegistration = await navigator.serviceWorker.ready.catch(() => null);

        if (readyRegistration) {
            return readyRegistration;
        }

        return registration;
    } catch (error) {
        console.error('Service worker registration failed:', error);

        return null;
    }
}

function resolveServiceWorkerVersionTag() {
    const swVersionNode = document.querySelector('meta[name="sw-version"]');

    if (swVersionNode instanceof HTMLMetaElement && swVersionNode.content.trim() !== '') {
        return swVersionNode.content.trim();
    }

    const appVersionNode = document.querySelector('meta[name="application-version"]');

    if (appVersionNode instanceof HTMLMetaElement && appVersionNode.content.trim() !== '') {
        return appVersionNode.content.trim();
    }

    return 'dev';
}

function setupPwaInstallPrompt() {
    const installButtons = Array.from(document.querySelectorAll(PWA_INSTALL_BUTTON_SELECTOR));

    if (!installButtons.length) {
        return;
    }

    hideInstallButtons(installButtons);

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredInstallPrompt = event;
        showInstallButtons(installButtons);
    });

    window.addEventListener('appinstalled', () => {
        deferredInstallPrompt = null;
        hideInstallButtons(installButtons);
        showSyncNotice('App wurde installiert und ist jetzt als Startbildschirm-App verfügbar.', 'success');
    });

    installButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            if (!deferredInstallPrompt) {
                showSyncNotice('Installationsdialog ist auf diesem Gerät aktuell nicht verfügbar.', 'warning');
                return;
            }

            deferredInstallPrompt.prompt();
            const choice = await deferredInstallPrompt.userChoice;

            deferredInstallPrompt = null;
            hideInstallButtons(installButtons);

            if (choice?.outcome !== 'accepted') {
                showSyncNotice('Installation wurde abgebrochen.', 'warning');
            }
        });
    });
}

function showInstallButtons(buttons) {
    buttons.forEach((button) => {
        button.classList.remove('hidden');
        button.removeAttribute('aria-hidden');
    });
}

function hideInstallButtons(buttons) {
    buttons.forEach((button) => {
        button.classList.add('hidden');
        button.setAttribute('aria-hidden', 'true');
    });
}

function setupOfflinePostQueue() {
    const postForms = document.querySelectorAll(OFFLINE_POST_FORM_SELECTOR);

    if (!postForms.length) {
        return;
    }

    postForms.forEach((form) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (form.dataset.offlineQueueBound === '1') {
            return;
        }

        form.dataset.offlineQueueBound = '1';

        form.addEventListener('submit', async (event) => {
            if (navigator.onLine) {
                return;
            }

            event.preventDefault();

            try {
                await queuePostSubmission(form);
                form.reset();
                await renderOfflineQueueStatusPanel();

                showSyncNotice('Brief in Vorbereitung: Der Beitrag wartet auf den naechsten Online-Moment.', 'success');

                const syncTriggered = await triggerQueuedPostSync();

                if (!syncTriggered) {
                    showSyncNotice('Kein Background Sync verfuegbar. Der Brief wird beim naechsten Besuch versendet.', 'warning');
                }
            } catch (error) {
                console.error('Offline post queue failed:', error);
                showSyncNotice('Der Brief konnte offline nicht abgelegt werden.', 'error');
            }
        });
    });
}

function setupOnlineSyncTrigger() {
    window.addEventListener('online', async () => {
        const syncTriggered = await triggerQueuedPostSync();

        if (syncTriggered) {
            showSyncNotice('Die Wege sind wieder offen: vorgemerkte Briefe werden zugestellt.', 'success');
        }

        await renderOfflineQueueStatusPanel();
    });
}

function setupServiceWorkerMessageHandling() {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    navigator.serviceWorker.addEventListener('message', (event) => {
        const data = event.data;

        if (!data || typeof data !== 'object' || !data.type) {
            return;
        }

        if (data.type === 'POST_SYNC_STARTED' || data.type === 'POST_SYNC_SUCCESS') {
            void renderOfflineQueueStatusPanel();
            return;
        }

        if (data.type === 'POST_SYNC_DEAD_LETTERED') {
            const summary = String(data.payload?.errorSummary || 'Synchronisierung fehlgeschlagen.');
            showSyncNotice(`Brief mit Zustellfehler im Entwurfsfach abgelegt: ${summary}`, 'warning');
            void renderDeadLetterPanel();
            void renderOfflineQueueStatusPanel();
            return;
        }

        if (data.type === 'POST_SYNC_AUTH_RETRY') {
            showSyncNotice('Siegel erneuert. Der Brief wird erneut auf den Weg gebracht.', 'warning');
            return;
        }

        if (data.type === 'POST_SYNC_AUTH_REQUIRED') {
            const retryHint = formatRetryHint(data.payload?.nextRetryAt);
            showSyncNotice(`Briefzustellung pausiert (Anmeldung/CSRF). Naechster Versuch ${retryHint}.`, 'warning');
            void renderOfflineQueueStatusPanel();
            return;
        }

        if (data.type === 'POST_SYNC_RETRY_SCHEDULED') {
            const status = Number(data.payload?.status || 0);

            if (status === 401 || status === 419) {
                return;
            }

            const retryHint = formatRetryHint(data.payload?.nextRetryAt);

            if (status === 429) {
                showSyncNotice(`Botengrenze erreicht. Naechster Zustellversuch ${retryHint}.`, 'warning');
                return;
            }

            showSyncNotice(`Briefzustellung pausiert. Naechster Versuch ${retryHint}.`, 'warning');
            void renderOfflineQueueStatusPanel();
            return;
        }

        if (data.type === 'POST_SYNC_FINISHED') {
            const remaining = Number(data.payload?.remaining || 0);

            if (remaining > 0) {
                showSyncNotice(`Noch ${remaining} Brief${remaining === 1 ? '' : 'e'} in Vorbereitung.`, 'warning');
                void renderOfflineQueueStatusPanel();
                return;
            }

            showSyncNotice('Alle vorgemerkten Briefe wurden zugestellt.', 'success');
            void renderDeadLetterPanel();
            void renderOfflineQueueStatusPanel();
        }
    });
}

function setupBrowserNotifications() {
    const root = document.querySelector(BROWSER_NOTIFICATION_ROOT_SELECTOR);

    if (!(root instanceof HTMLElement)) {
        return;
    }

    const subscribeUrl = root.dataset.subscribeUrl || '';
    const unsubscribeUrl = root.dataset.unsubscribeUrl || '';
    const vapidPublicKey = root.dataset.vapidPublicKey || '';

    if (!subscribeUrl || !unsubscribeUrl) {
        return;
    }

    browserNotificationConfig = {
        subscribeUrl,
        unsubscribeUrl,
        vapidPublicKey,
        worldSlug: root.dataset.worldSlug || 'chroniken-der-asche',
        appName: root.dataset.appName || 'C76-RPG',
        enabledKinds: normalizeBrowserNotificationKinds(root.dataset.enabledKinds),
        csrfToken: getCsrfToken(),
        statusNode: document.querySelector(BROWSER_NOTIFICATION_STATUS_SELECTOR),
        enableButton: document.querySelector(BROWSER_NOTIFICATION_ENABLE_SELECTOR),
    };

    if (browserNotificationConfig.enableButton instanceof HTMLButtonElement) {
        browserNotificationConfig.enableButton.addEventListener('click', async () => {
            await requestBrowserNotificationPermission();
        });
    }

    window.addEventListener('online', () => {
        if (isBrowserPushReady()) {
            void syncBrowserNotificationSubscriptionState();
        }
    });

    updateBrowserNotificationStatus();
    void syncBrowserNotificationSubscriptionState();
}

function normalizeBrowserNotificationKinds(rawKinds) {
    if (!rawKinds) {
        return [];
    }

    const source = Array.isArray(rawKinds) ? rawKinds : safeParseBrowserNotificationKinds(rawKinds);

    if (!Array.isArray(source)) {
        return [];
    }

    return source
        .filter((kind) => typeof kind === 'string')
        .map((kind) => kind.trim())
        .filter((kind) => kind !== '');
}

function safeParseBrowserNotificationKinds(rawKinds) {
    try {
        return JSON.parse(rawKinds);
    } catch {
        return null;
    }
}

function updateBrowserNotificationStatus() {
    if (!browserNotificationConfig) {
        return;
    }

    const statusNode = browserNotificationConfig.statusNode;
    const enableButton = browserNotificationConfig.enableButton;
    const hasBrowserKinds = browserNotificationConfig.enabledKinds.length > 0;
    const supportsNotifications = supportsWebPush();
    let statusMessage = 'Browser-Benachrichtigungen werden geprüft.';
    let disableEnableButton = false;
    let enableButtonLabel = 'Browser-Push aktivieren';

    if (!hasBrowserKinds) {
        statusMessage = 'Browser-Push ist in den Mitteilungs-Präferenzen aktuell deaktiviert.';
        disableEnableButton = true;
    } else if (!supportsNotifications) {
        statusMessage = 'Dieser Browser unterstützt Web Push nicht vollständig.';
        disableEnableButton = true;
    } else if (!browserNotificationConfig.vapidPublicKey.trim()) {
        statusMessage = 'Web-Push ist serverseitig nicht konfiguriert (VAPID-Key fehlt).';
        disableEnableButton = true;
    } else if (Notification.permission === 'granted') {
        statusMessage = 'Browser-Push ist aktiv und mit diesem Geraet verknuepft.';
        disableEnableButton = true;
        enableButtonLabel = 'Browser-Push aktiv';
    } else if (Notification.permission === 'denied') {
        statusMessage = 'Browser-Push wurde blockiert. Erlaube Notifications in deinen Browser-Einstellungen.';
        disableEnableButton = true;
        enableButtonLabel = 'Browser-Push blockiert';
    } else {
        statusMessage = 'Browser-Push wartet auf Freigabe.';
    }

    if (statusNode instanceof HTMLElement) {
        statusNode.textContent = statusMessage;
    }

    if (enableButton instanceof HTMLButtonElement) {
        enableButton.textContent = enableButtonLabel;
        enableButton.disabled = disableEnableButton;

        if (disableEnableButton) {
            enableButton.classList.add('cursor-not-allowed', 'opacity-60');
            enableButton.setAttribute('aria-disabled', 'true');
        } else {
            enableButton.classList.remove('cursor-not-allowed', 'opacity-60');
            enableButton.setAttribute('aria-disabled', 'false');
        }
    }
}

async function requestBrowserNotificationPermission() {
    if (!browserNotificationConfig) {
        return;
    }

    if (!supportsWebPush()) {
        showSyncNotice('Browser-Benachrichtigungen werden auf diesem Geraet nicht unterstuetzt.', 'warning');
        updateBrowserNotificationStatus();
        return;
    }

    if (!browserNotificationConfig.enabledKinds.length) {
        showSyncNotice('Aktiviere zuerst Browser-Push in den Mitteilungs-Präferenzen.', 'warning');
        updateBrowserNotificationStatus();
        return;
    }

    if (Notification.permission === 'denied') {
        showSyncNotice('Browser-Permission ist blockiert. Bitte im Browser manuell freigeben.', 'warning');
        updateBrowserNotificationStatus();
        return;
    }

    if (Notification.permission === 'granted') {
        await syncBrowserNotificationSubscriptionState();
        updateBrowserNotificationStatus();
        return;
    }

    try {
        const permission = await Notification.requestPermission();

        if (permission === 'granted') {
            showSyncNotice('Browser-Push wurde aktiviert.', 'success');
            await syncBrowserNotificationSubscriptionState();
        } else {
            showSyncNotice('Browser-Push wurde nicht freigegeben.', 'warning');
        }
    } catch (error) {
        console.error('Notification permission request failed:', error);
        showSyncNotice('Browser-Permission konnte nicht angefragt werden.', 'error');
    }

    updateBrowserNotificationStatus();
}

function supportsWebPush() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

function isBrowserPushReady() {
    return (
        Boolean(browserNotificationConfig) &&
        browserNotificationConfig.enabledKinds.length > 0 &&
        supportsWebPush() &&
        browserNotificationConfig.vapidPublicKey.trim() !== '' &&
        Notification.permission === 'granted'
    );
}

async function syncBrowserNotificationSubscriptionState() {
    if (!browserNotificationConfig || !supportsWebPush()) {
        return;
    }

    const registration = await getActiveServiceWorkerRegistration();

    if (!registration || !registration.pushManager) {
        return;
    }

    const currentSubscription = await registration.pushManager.getSubscription().catch(() => null);

    if (!isBrowserPushReady()) {
        if (currentSubscription) {
            await unsubscribeBrowserPush(currentSubscription, true);
        }
        updateBrowserNotificationStatus();
        return;
    }

    if (currentSubscription) {
        await syncBrowserPushSubscriptionWithServer(currentSubscription);
        updateBrowserNotificationStatus();
        return;
    }

    try {
        const newSubscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(browserNotificationConfig.vapidPublicKey),
        });

        await syncBrowserPushSubscriptionWithServer(newSubscription);
        showSyncNotice('Browser-Push wurde aktiviert.', 'success');
    } catch (error) {
        console.error('Browser push subscribe failed:', error);
        showSyncNotice('Browser-Push konnte nicht aktiviert werden.', 'error');
    }

    updateBrowserNotificationStatus();
}

async function unsubscribeBrowserPush(subscription, silent = false) {
    if (!browserNotificationConfig) {
        return;
    }

    const endpoint = typeof subscription?.endpoint === 'string' ? subscription.endpoint : '';

    try {
        await subscription.unsubscribe();
    } catch (error) {
        console.error('Browser push unsubscribe failed:', error);
    }

    if (endpoint !== '') {
        try {
            await postJson(browserNotificationConfig.unsubscribeUrl, {
                world_slug: resolveBrowserNotificationWorldSlug(),
                endpoint,
            });
        } catch (error) {
            console.error('Browser push unsubscribe sync failed:', error);
        }
    }

    if (!silent) {
        showSyncNotice('Browser-Push wurde deaktiviert.', 'warning');
    }
}

async function syncBrowserPushSubscriptionWithServer(subscription) {
    if (!browserNotificationConfig) {
        return;
    }

    const payload = normalizePushSubscriptionPayload(subscription);

    if (!payload) {
        throw new Error('Push subscription payload is invalid.');
    }

    await postJson(browserNotificationConfig.subscribeUrl, {
        world_slug: resolveBrowserNotificationWorldSlug(),
        endpoint: payload.endpoint,
        public_key: payload.publicKey,
        auth_token: payload.authToken,
        content_encoding: payload.contentEncoding,
    });
}

function normalizePushSubscriptionPayload(subscription) {
    if (!subscription || typeof subscription.toJSON !== 'function') {
        return null;
    }

    const json = subscription.toJSON();
    const endpoint = typeof json?.endpoint === 'string' ? json.endpoint : '';
    const publicKey = typeof json?.keys?.p256dh === 'string' ? json.keys.p256dh : '';
    const authToken = typeof json?.keys?.auth === 'string' ? json.keys.auth : '';
    const contentEncoding =
        typeof json?.contentEncoding === 'string' && json.contentEncoding.trim() !== ''
            ? json.contentEncoding.trim()
            : 'aes128gcm';

    if (!endpoint || !publicKey || !authToken) {
        return null;
    }

    return {
        endpoint,
        publicKey,
        authToken,
        contentEncoding,
    };
}

function resolveBrowserNotificationWorldSlug() {
    if (!browserNotificationConfig) {
        return 'chroniken-der-asche';
    }

    const fromPath = window.location.pathname.match(/^\/w\/([^/]+)/);
    if (fromPath && fromPath[1]) {
        return decodeURIComponent(fromPath[1]);
    }

    if (typeof browserNotificationConfig.worldSlug === 'string' && browserNotificationConfig.worldSlug.trim() !== '') {
        return browserNotificationConfig.worldSlug.trim();
    }

    return 'chroniken-der-asche';
}

function getCsrfToken() {
    const tokenNode = document.querySelector('meta[name="csrf-token"]');

    if (!(tokenNode instanceof HTMLMetaElement)) {
        return '';
    }

    return tokenNode.content || '';
}

function persistActiveWorldSlugContext() {
    const worldSlug = resolveActiveWorldSlug();

    if (worldSlug === '') {
        return;
    }

    writeLocalStorageValue('c76:last-world-slug', worldSlug);
}

function resolveActiveWorldSlug() {
    const htmlSlug = document.documentElement?.dataset?.worldSlug || '';
    const bodySlug = document.body?.dataset?.worldSlug || '';
    const pathMatch = window.location.pathname.match(/^\/w\/([^/]+)/);
    let fromPath = '';

    if (pathMatch && pathMatch[1]) {
        try {
            fromPath = decodeURIComponent(pathMatch[1]);
        } catch {
            fromPath = pathMatch[1];
        }
    }

    const slug = String(htmlSlug || bodySlug || fromPath || '').trim().toLowerCase();

    if (slug === '' || /^[a-z0-9-]+$/.test(slug) !== true) {
        return '';
    }

    return slug;
}

function readLocalStorageValue(key) {
    if (!key) {
        return null;
    }

    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

function writeLocalStorageValue(key, value) {
    if (!key) {
        return;
    }

    try {
        window.localStorage.setItem(key, value);
    } catch {
        // Ignore storage write errors.
    }
}

function removeLocalStorageValue(key) {
    if (!key) {
        return;
    }

    try {
        window.localStorage.removeItem(key);
    } catch {
        // Ignore storage remove errors.
    }
}

function debounce(callback, waitMs) {
    let timeoutId = null;

    return (...args) => {
        if (timeoutId) {
            window.clearTimeout(timeoutId);
        }

        timeoutId = window.setTimeout(() => {
            callback(...args);
        }, waitMs);
    };
}

async function postJson(url, payload) {
    const headers = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (browserNotificationConfig?.csrfToken) {
        headers['X-CSRF-TOKEN'] = browserNotificationConfig.csrfToken;
    }

    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers,
        body: JSON.stringify(payload),
    });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status} for ${url}`);
    }

    return response;
}

function urlBase64ToUint8Array(base64String) {
    const normalizedBase64 = base64String.replace(/-/g, '+').replace(/_/g, '/');
    const padding = '='.repeat((4 - (normalizedBase64.length % 4)) % 4);
    const decoded = window.atob(normalizedBase64 + padding);
    const output = new Uint8Array(decoded.length);

    for (let index = 0; index < decoded.length; index += 1) {
        output[index] = decoded.charCodeAt(index);
    }

    return output;
}

async function warmOfflineReadingCache() {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    const urls = [window.location.href];

    document.querySelectorAll(CACHEABLE_LINK_SELECTOR).forEach((link) => {
        if (link instanceof HTMLAnchorElement) {
            urls.push(link.href);
        }
    });

    const filteredUrls = Array.from(new Set(urls.filter((url) => isOfflineReadableUrl(url)).slice(0, 40)));

    if (!filteredUrls.length) {
        return;
    }

    const activeWorker =
        navigator.serviceWorker.controller ||
        (await getActiveServiceWorkerRegistration())?.active ||
        null;

    if (!activeWorker) {
        return;
    }

    activeWorker.postMessage({
        type: 'CACHE_URLS',
        urls: filteredUrls,
    });
}

function isOfflineReadableUrl(url) {
    try {
        const parsed = new URL(url, window.location.origin);

        if (parsed.origin !== window.location.origin) {
            return false;
        }

        return (
            /^\/w\/[^/]+\/campaigns\/[^/]+\/scenes\/[^/]+\/?$/.test(parsed.pathname) ||
            /^\/campaigns\/[^/]+\/scenes\/[^/]+\/?$/.test(parsed.pathname) ||
            /^\/characters\/[^/]+\/?$/.test(parsed.pathname)
        );
    } catch {
        return false;
    }
}

async function triggerQueuedPostSync() {
    if (!('serviceWorker' in navigator)) {
        return false;
    }

    const registration = await getActiveServiceWorkerRegistration();

    if (!registration?.active) {
        return false;
    }

    if ('sync' in registration) {
        try {
            await registration.sync.register(SYNC_TAG_POSTS);

            return true;
        } catch (error) {
            if (
                error instanceof DOMException &&
                (error.name === 'InvalidStateError' || error.name === 'NotAllowedError' || error.name === 'SecurityError')
            ) {
                return false;
            }

            console.error('Background sync registration failed:', error);
        }
    }

    registration.active.postMessage({ type: 'SYNC_POSTS_NOW' });
    return true;
}

async function getActiveServiceWorkerRegistration() {
    if (!('serviceWorker' in navigator)) {
        return null;
    }

    if (swRegistration?.active) {
        return swRegistration;
    }

    const readyRegistration = await navigator.serviceWorker.ready.catch(() => null);

    if (readyRegistration?.active) {
        swRegistration = readyRegistration;
        return readyRegistration;
    }

    return null;
}

async function queuePostSubmission(form) {
    const formData = new FormData(form);
    const entries = [];

    for (const [key, value] of formData.entries()) {
        if (typeof value !== 'string') {
            continue;
        }

        entries.push([key, value]);
    }

    const payload = {
        url: form.action,
        method: (form.method || 'POST').toUpperCase(),
        entries,
        queued_at: new Date().toISOString(),
        source_path: window.location.pathname,
        source_url: `${window.location.pathname}${window.location.search}`,
    };

    const database = await openQueueDatabase();

    await new Promise((resolve, reject) => {
        const transaction = database.transaction(QUEUE_STORE_NAME, 'readwrite');
        const store = transaction.objectStore(QUEUE_STORE_NAME);
        const request = store.add(payload);

        request.onsuccess = () => resolve(undefined);
        request.onerror = () => reject(request.error || new Error('Could not persist offline post.'));

        transaction.oncomplete = () => {
            database.close();
        };
    });
}

async function renderOfflineQueueStatusPanel() {
    const panel = document.getElementById(OFFLINE_QUEUE_STATUS_PANEL_ID);

    if (!(panel instanceof HTMLElement)) {
        return;
    }

    let queuedCount = 0;

    try {
        queuedCount = await getQueuedPostCount();
    } catch (error) {
        console.error('Could not load offline queue count:', error);
        panel.classList.add('hidden');
        panel.innerHTML = '';
        return;
    }

    if (queuedCount <= 0) {
        panel.classList.add('hidden');
        panel.innerHTML = '';
        return;
    }

    panel.classList.remove('hidden');
    panel.innerHTML = '';

    const heading = document.createElement('p');
    heading.className = 'text-xs uppercase tracking-[0.08em] text-amber-300';
    heading.textContent = queuedCount === 1
        ? '1 Brief in Vorbereitung'
        : `${queuedCount} Briefe in Vorbereitung`;
    panel.appendChild(heading);

    const detail = document.createElement('p');
    detail.className = 'mt-2 text-sm text-stone-300';
    detail.textContent = navigator.onLine
        ? 'Die Verbindung steht. Der naechste Sync bringt die Briefe auf den Weg.'
        : 'Keine Verbindung. Die Briefe bleiben sicher im Reisebeutel.';
    panel.appendChild(detail);

    const actionRow = document.createElement('div');
    actionRow.className = 'mt-3 flex flex-wrap items-center gap-2';
    panel.appendChild(actionRow);

    const syncButton = document.createElement('button');
    syncButton.type = 'button';
    syncButton.className = 'rounded-md border border-amber-600/70 bg-amber-900/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.08em] text-amber-100 transition hover:bg-amber-900/35 disabled:cursor-not-allowed disabled:opacity-60';
    syncButton.textContent = navigator.onLine ? 'Jetzt zustellen' : 'Wartet auf Verbindung';
    syncButton.disabled = !navigator.onLine;
    syncButton.addEventListener('click', async () => {
        const syncTriggered = await triggerQueuedPostSync();

        if (syncTriggered) {
            showSyncNotice('Die Boten wurden gerufen. Briefe werden zugestellt.', 'success');
            return;
        }

        showSyncNotice('Zustellung konnte nicht gestartet werden.', 'warning');
    });
    actionRow.appendChild(syncButton);
}

async function getQueuedPostCount() {
    const database = await openQueueDatabase();

    return new Promise((resolve, reject) => {
        const transaction = database.transaction(QUEUE_STORE_NAME, 'readonly');
        const store = transaction.objectStore(QUEUE_STORE_NAME);
        const request = store.count();

        request.onsuccess = () => resolve(Number(request.result || 0));
        request.onerror = () => reject(request.error || new Error('Could not read offline queue count.'));

        transaction.oncomplete = () => {
            database.close();
        };
    });
}

async function renderDeadLetterPanel() {
    const panel = document.getElementById(OFFLINE_DEAD_LETTER_PANEL_ID);

    if (!(panel instanceof HTMLElement)) {
        return;
    }

    let deadLetters = [];

    try {
        deadLetters = await getDeadLetters();
    } catch (error) {
        console.error('Could not load dead-letter entries:', error);
        showSyncNotice('Entwürfe/Fehler konnten nicht geladen werden.', 'warning');
        return;
    }

    if (!deadLetters.length) {
        panel.classList.add('hidden');
        panel.innerHTML = '';
        return;
    }

    panel.classList.remove('hidden');
    panel.innerHTML = '';

    const heading = document.createElement('p');
    heading.className = 'text-xs uppercase tracking-[0.08em] text-amber-300';
    heading.textContent = 'Briefe mit Zustellproblemen';
    panel.appendChild(heading);

    const list = document.createElement('div');
    list.className = 'mt-3 space-y-3';
    panel.appendChild(list);

    deadLetters.forEach((deadLetter) => {
        list.appendChild(buildDeadLetterEntryElement(deadLetter));
    });
}

function buildDeadLetterEntryElement(deadLetter) {
    const element = document.createElement('article');
    element.className = 'rounded-lg border border-amber-700/45 bg-black/30 p-3';

    const errorSummary = String(deadLetter.error_summary || 'Zustellung fehlgeschlagen.');
    const contentPreview = buildDeadLetterPreview(extractQueuedPostContent(deadLetter.entries));

    const summaryNode = document.createElement('p');
    summaryNode.className = 'text-xs uppercase tracking-[0.08em] text-amber-200';
    summaryNode.textContent = `Zustellung gescheitert: ${errorSummary}`;
    element.appendChild(summaryNode);

    const previewNode = document.createElement('p');
    previewNode.className = 'mt-2 text-sm text-stone-300';
    previewNode.textContent = contentPreview;
    element.appendChild(previewNode);

    const actions = document.createElement('div');
    actions.className = 'mt-3 flex flex-wrap items-center gap-2';
    element.appendChild(actions);

    const importButton = document.createElement('button');
    importButton.type = 'button';
    importButton.className = 'rounded-md border border-emerald-600/70 bg-emerald-900/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.08em] text-emerald-100 transition hover:bg-emerald-900/35';
    importButton.textContent = 'In Schreibfeld holen';
    importButton.addEventListener('click', async () => {
        await importDeadLetterIntoEditor(deadLetter);
    });
    actions.appendChild(importButton);

    const removeButton = document.createElement('button');
    removeButton.type = 'button';
    removeButton.className = 'rounded-md border border-stone-600/80 bg-black/35 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.08em] text-stone-200 transition hover:border-stone-400';
    removeButton.textContent = 'Verwerfen';
    removeButton.addEventListener('click', async () => {
        await deleteDeadLetterById(deadLetter.id);
        await renderDeadLetterPanel();
        await renderOfflineQueueStatusPanel();
        showSyncNotice('Brief aus dem Entwurfsfach entfernt.', 'warning');
    });
    actions.appendChild(removeButton);

    return element;
}

async function importDeadLetterIntoEditor(deadLetter) {
    const editor = document.querySelector(OFFLINE_POST_CONTENT_SELECTOR);

    if (!(editor instanceof HTMLTextAreaElement)) {
        showSyncNotice('Kein Schreibfeld gefunden. Oeffne zuerst den Editor.', 'warning');
        return;
    }

    const incomingContent = extractQueuedPostContent(deadLetter.entries);

    if (!hasEditorContent(incomingContent)) {
        showSyncNotice('Dieser Brief enthaelt keinen Textinhalt.', 'warning');
        return;
    }

    let mergeMode = DEAD_LETTER_MERGE_REPLACE;

    if (hasEditorContent(editor.value)) {
        mergeMode = await promptDeadLetterMergeMode();

        if (mergeMode === DEAD_LETTER_MERGE_CANCEL) {
            return;
        }
    }

    editor.value = mergeDeadLetterContent(editor.value, incomingContent, mergeMode);
    editor.dispatchEvent(new Event('input', { bubbles: true }));
    editor.focus();

    await deleteDeadLetterById(deadLetter.id);
    await renderDeadLetterPanel();
    await renderOfflineQueueStatusPanel();

    if (mergeMode === DEAD_LETTER_MERGE_APPEND) {
        showSyncNotice('Brief wurde an den bestehenden Entwurf angehaengt.', 'success');
        return;
    }

    showSyncNotice('Brief wurde in das Schreibfeld uebernommen.', 'success');
}

function promptDeadLetterMergeMode() {
    return new Promise((resolve) => {
        if (!(document.body instanceof HTMLBodyElement)) {
            resolve(DEAD_LETTER_MERGE_CANCEL);
            return;
        }

        const previousFocus = document.activeElement instanceof HTMLElement
            ? document.activeElement
            : null;
        const backdrop = document.createElement('div');
        backdrop.className = 'immersive-modal-backdrop fixed inset-0 z-[70] flex items-center justify-center bg-black/70 px-4';
        backdrop.dataset.overlayModalOpen = '1';

        const dialog = document.createElement('div');
        dialog.className = 'w-full max-w-md rounded-xl border border-stone-600/80 bg-neutral-900 p-4 text-stone-100 shadow-2xl';
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-labelledby', 'dead-letter-merge-title');
        dialog.setAttribute('aria-describedby', 'dead-letter-merge-description');
        backdrop.appendChild(dialog);

        const title = document.createElement('p');
        title.id = 'dead-letter-merge-title';
        title.className = 'font-semibold text-stone-100';
        title.textContent = 'Schreibfeld enthaelt bereits Text';
        dialog.appendChild(title);

        const description = document.createElement('p');
        description.id = 'dead-letter-merge-description';
        description.className = 'mt-2 text-sm text-stone-300';
        description.textContent = 'Wie soll der Brief uebernommen werden?';
        dialog.appendChild(description);

        const actions = document.createElement('div');
        actions.className = 'mt-4 flex flex-wrap gap-2';
        dialog.appendChild(actions);

        const appendButton = document.createElement('button');
        appendButton.type = 'button';
        appendButton.className = 'rounded-md border border-emerald-600/70 bg-emerald-900/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.08em] text-emerald-100';
        appendButton.textContent = 'Anhaengen (Empfohlen)';
        actions.appendChild(appendButton);

        const replaceButton = document.createElement('button');
        replaceButton.type = 'button';
        replaceButton.className = 'rounded-md border border-amber-600/70 bg-amber-900/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.08em] text-amber-100';
        replaceButton.textContent = 'Ersetzen';
        actions.appendChild(replaceButton);

        const cancelButton = document.createElement('button');
        cancelButton.type = 'button';
        cancelButton.className = 'rounded-md border border-stone-600/80 bg-black/35 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.08em] text-stone-200';
        cancelButton.textContent = 'Abbrechen';
        actions.appendChild(cancelButton);

        const cleanup = (mode) => {
            document.removeEventListener('keydown', handleDialogKeydown);
            document.body.classList.remove('has-overlay-modal');
            backdrop.remove();

            if (previousFocus instanceof HTMLElement) {
                previousFocus.focus({ preventScroll: true });
            }

            resolve(mode);
        };

        const handleDialogKeydown = (event) => {
            if (event.key === 'Escape') {
                cleanup(DEAD_LETTER_MERGE_CANCEL);
                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            const focusables = [appendButton, replaceButton, cancelButton];
            const activeElement = document.activeElement;
            const currentIndex = focusables.findIndex((element) => element === activeElement);

            if (event.shiftKey) {
                const previousIndex = currentIndex <= 0 ? focusables.length - 1 : currentIndex - 1;
                focusables[previousIndex].focus();
                event.preventDefault();
                return;
            }

            const nextIndex = currentIndex < 0 || currentIndex >= focusables.length - 1
                ? 0
                : currentIndex + 1;
            focusables[nextIndex].focus();
            event.preventDefault();
        };

        appendButton.addEventListener('click', () => cleanup(DEAD_LETTER_MERGE_APPEND));
        replaceButton.addEventListener('click', () => cleanup(DEAD_LETTER_MERGE_REPLACE));
        cancelButton.addEventListener('click', () => cleanup(DEAD_LETTER_MERGE_CANCEL));
        backdrop.addEventListener('click', (event) => {
            if (event.target === backdrop) {
                cleanup(DEAD_LETTER_MERGE_CANCEL);
            }
        });

        document.body.classList.add('has-overlay-modal');
        document.body.appendChild(backdrop);
        document.addEventListener('keydown', handleDialogKeydown);
        appendButton.focus();
    });
}

function buildDeadLetterPreview(content) {
    const normalized = String(content || '').trim();

    if (normalized === '') {
        return 'Kein Textinhalt verfügbar.';
    }

    if (normalized.length <= 140) {
        return normalized;
    }

    return `${normalized.slice(0, 140)} …`;
}

function extractQueuedPostContent(entries) {
    if (!Array.isArray(entries)) {
        return '';
    }

    for (const entry of entries) {
        if (!Array.isArray(entry) || entry.length !== 2) {
            continue;
        }

        if (entry[0] === 'content') {
            return String(entry[1] ?? '');
        }
    }

    return '';
}

function openQueueDatabase() {
    return new Promise((resolve, reject) => {
        if (!('indexedDB' in window)) {
            reject(new Error('IndexedDB is not supported in this browser.'));
            return;
        }

        const request = indexedDB.open(QUEUE_DB_NAME, 2);

        request.onupgradeneeded = () => {
            const database = request.result;

            if (!database.objectStoreNames.contains(QUEUE_STORE_NAME)) {
                database.createObjectStore(QUEUE_STORE_NAME, {
                    keyPath: 'id',
                    autoIncrement: true,
                });
            }

            if (!database.objectStoreNames.contains(DEAD_LETTER_STORE_NAME)) {
                database.createObjectStore(DEAD_LETTER_STORE_NAME, {
                    keyPath: 'id',
                    autoIncrement: true,
                });
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error || new Error('Could not open offline queue database.'));
    });
}

async function getDeadLetters() {
    const database = await openQueueDatabase();

    return new Promise((resolve, reject) => {
        const transaction = database.transaction(DEAD_LETTER_STORE_NAME, 'readonly');
        const store = transaction.objectStore(DEAD_LETTER_STORE_NAME);
        const request = store.getAll();

        request.onsuccess = () => {
            const result = Array.isArray(request.result) ? request.result : [];
            result.sort((left, right) => {
                const leftTs = Date.parse(String(left.dead_lettered_at || ''));
                const rightTs = Date.parse(String(right.dead_lettered_at || ''));

                if (Number.isFinite(leftTs) && Number.isFinite(rightTs)) {
                    return rightTs - leftTs;
                }

                return Number(right.id || 0) - Number(left.id || 0);
            });
            resolve(result);
        };
        request.onerror = () => reject(request.error || new Error('Could not read dead-letter entries.'));

        transaction.oncomplete = () => {
            database.close();
        };
    });
}

async function deleteDeadLetterById(id) {
    const numericId = Number(id || 0);

    if (!Number.isFinite(numericId) || numericId <= 0) {
        return;
    }

    const database = await openQueueDatabase();

    await new Promise((resolve, reject) => {
        const transaction = database.transaction(DEAD_LETTER_STORE_NAME, 'readwrite');
        const store = transaction.objectStore(DEAD_LETTER_STORE_NAME);
        const request = store.delete(numericId);

        request.onsuccess = () => resolve(undefined);
        request.onerror = () => reject(request.error || new Error('Could not delete dead-letter entry.'));

        transaction.oncomplete = () => {
            database.close();
        };
    });
}

function showSyncNotice(message, level = 'info') {
    const container = getOrCreateSyncNoticeContainer();
    const notice = document.createElement('p');

    notice.className = `rounded border px-3 py-2 text-xs uppercase tracking-[0.08em] ${resolveNoticeClass(level)}`;
    notice.textContent = message;

    container.appendChild(notice);

    window.setTimeout(() => {
        notice.remove();

        if (!container.children.length) {
            container.remove();
        }
    }, 9000);
}

function formatRetryHint(nextRetryAt) {
    const retryTimestampMs = Date.parse(typeof nextRetryAt === 'string' ? nextRetryAt : '');

    if (!Number.isFinite(retryTimestampMs)) {
        return 'später';
    }

    const remainingSeconds = Math.max(1, Math.round((retryTimestampMs - Date.now()) / 1000));

    if (remainingSeconds < 60) {
        return `in ${remainingSeconds}s`;
    }

    const remainingMinutes = Math.ceil(remainingSeconds / 60);
    return `in ${remainingMinutes}min`;
}

function getOrCreateSyncNoticeContainer() {
    const existingContainer = document.getElementById('offline-sync-notices');

    if (existingContainer) {
        return existingContainer;
    }

    const container = document.createElement('div');
    container.id = 'offline-sync-notices';
    container.className = 'fixed bottom-4 right-4 z-50 max-w-sm space-y-2';

    document.body.appendChild(container);

    return container;
}

function resolveNoticeClass(level) {
    if (level === 'success') {
        return 'border-emerald-600/70 bg-emerald-900/30 text-emerald-100';
    }

    if (level === 'warning') {
        return 'border-amber-600/70 bg-amber-900/30 text-amber-100';
    }

    if (level === 'error') {
        return 'border-red-700/70 bg-red-900/30 text-red-100';
    }

    return 'border-stone-600/80 bg-neutral-900/80 text-stone-100';
}
