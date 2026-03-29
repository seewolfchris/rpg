/**
 * Shared immersion utilities for overlays, focus handling, local storage and sync notices.
 */

export function hasOpenOverlayDialog() {
    return document.body.classList.contains('has-overlay-modal')
        || document.querySelector('[data-overlay-modal-open="1"]') !== null;
}

export function hasInteractiveTypingTarget(target) {
    if (!(target instanceof HTMLElement)) {
        return false;
    }

    if (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)) {
        return true;
    }

    return Boolean(target.closest('button, a[href], summary, [role="button"], [role="link"]'));
}

export function trapFocusInElements(event, focusables) {
    if (event.key !== 'Tab') {
        return false;
    }

    if (!Array.isArray(focusables) || focusables.length === 0) {
        return false;
    }

    const activeElement = document.activeElement;
    const currentIndex = focusables.findIndex((element) => element === activeElement);

    if (event.shiftKey) {
        const previousIndex = currentIndex <= 0 ? focusables.length - 1 : currentIndex - 1;
        focusables[previousIndex]?.focus();
        event.preventDefault();
        return true;
    }

    const nextIndex = currentIndex < 0 || currentIndex >= focusables.length - 1
        ? 0
        : currentIndex + 1;
    focusables[nextIndex]?.focus();
    event.preventDefault();
    return true;
}

export function readLocalStorageValue(key) {
    if (!key) {
        return null;
    }

    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

export function writeLocalStorageValue(key, value) {
    if (!key) {
        return;
    }

    try {
        window.localStorage.setItem(key, value);
    } catch {
        // Ignore storage write errors.
    }
}

export function removeLocalStorageValue(key) {
    if (!key) {
        return;
    }

    try {
        window.localStorage.removeItem(key);
    } catch {
        // Ignore storage remove errors.
    }
}

export function debounce(callback, waitMs) {
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

export function showSyncNotice(message, level = 'info') {
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
