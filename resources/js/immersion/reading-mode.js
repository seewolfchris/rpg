/**
 * Scene thread reading mode behaviors (toggle, keyboard navigation, focus, reveal and bookmark progress).
 */

import {
    hasInteractiveTypingTarget,
    hasOpenOverlayDialog,
    readLocalStorageValue,
    writeLocalStorageValue,
} from './utils';

const SCENE_THREAD_READING_MODE_SELECTOR = '[data-scene-thread-reading-mode]';
const READING_MODE_TOGGLE_SELECTOR = '[data-reading-mode-toggle]';
const READING_MODE_FULLSCREEN_SELECTOR = '[data-reading-mode-fullscreen]';
const READING_POST_SELECTOR = '[data-reading-post-anchor]';
const READING_PROGRESS_BOOKMARK_SELECTOR = '[data-reading-progress-bookmark]';
const READING_PROGRESS_VALUE_SELECTOR = '[data-reading-progress-value]';
const READING_PROGRESS_PERCENT_SELECTOR = '[data-reading-progress-percent]';
const READING_PROGRESS_BAR_SELECTOR = '[data-reading-progress-bar]';

export function setupSceneThreadReadingMode() {
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
