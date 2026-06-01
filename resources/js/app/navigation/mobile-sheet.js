const MOBILE_BREAKPOINT_QUERY = '(min-width: 640px)';
const OPEN_CLASS = 'has-mobile-nav-open';

function isFocusableElement(element) {
    if (!(element instanceof HTMLElement)) {
        return false;
    }

    if (element.hasAttribute('disabled')) {
        return false;
    }

    if (element.getAttribute('aria-hidden') === 'true') {
        return false;
    }

    return true;
}

function findFirstFocusable(panel) {
    const candidates = panel.querySelectorAll(
        'a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])',
    );

    for (const candidate of candidates) {
        if (isFocusableElement(candidate)) {
            return candidate;
        }
    }

    return null;
}

export function setupMobileSheetNavigation() {
    const trigger = document.querySelector('[data-mobile-nav-trigger]');
    const panel = document.querySelector('[data-mobile-sheet-panel]');
    const backdrop = document.querySelector('[data-mobile-nav-backdrop]');

    if (!(trigger instanceof HTMLButtonElement) || !(panel instanceof HTMLElement) || !(backdrop instanceof HTMLElement)) {
        return;
    }

    const mediaQuery = window.matchMedia(MOBILE_BREAKPOINT_QUERY);
    const isDesktop = () => mediaQuery.matches;
    const isOpen = () => document.body.classList.contains(OPEN_CLASS);

    let focusReturnTarget = null;

    const syncState = () => {
        if (isDesktop()) {
            document.body.classList.remove(OPEN_CLASS);
            trigger.setAttribute('aria-expanded', 'false');
            panel.setAttribute('aria-hidden', 'false');
            backdrop.hidden = true;
            backdrop.setAttribute('aria-hidden', 'true');

            return;
        }

        if (isOpen()) {
            trigger.setAttribute('aria-expanded', 'true');
            panel.setAttribute('aria-hidden', 'false');
            backdrop.hidden = false;
            backdrop.setAttribute('aria-hidden', 'false');

            return;
        }

        trigger.setAttribute('aria-expanded', 'false');
        panel.setAttribute('aria-hidden', 'true');
        backdrop.hidden = true;
        backdrop.setAttribute('aria-hidden', 'true');
    };

    const closePanel = ({ restoreFocus = true } = {}) => {
        if (!isOpen() && !isDesktop()) {
            return;
        }

        document.body.classList.remove(OPEN_CLASS);
        trigger.setAttribute('aria-expanded', 'false');

        if (!isDesktop()) {
            panel.setAttribute('aria-hidden', 'true');
            backdrop.hidden = true;
            backdrop.setAttribute('aria-hidden', 'true');
        }

        if (restoreFocus) {
            const target = focusReturnTarget instanceof HTMLElement ? focusReturnTarget : trigger;
            target.focus();
        }

        focusReturnTarget = null;
    };

    const openPanel = () => {
        if (isDesktop()) {
            return;
        }

        focusReturnTarget = document.activeElement instanceof HTMLElement ? document.activeElement : trigger;
        document.body.classList.add(OPEN_CLASS);
        trigger.setAttribute('aria-expanded', 'true');
        panel.setAttribute('aria-hidden', 'false');
        backdrop.hidden = false;
        backdrop.setAttribute('aria-hidden', 'false');

        const firstFocusable = findFirstFocusable(panel);
        if (firstFocusable instanceof HTMLElement) {
            firstFocusable.focus();
        }
    };

    if (trigger.dataset.mobileSheetBound === 'true') {
        syncState();
        return;
    }

    trigger.addEventListener('click', () => {
        if (isOpen()) {
            closePanel();
            return;
        }

        openPanel();
    });

    backdrop.addEventListener('click', () => {
        closePanel();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !isOpen()) {
            return;
        }

        event.preventDefault();
        closePanel();
    });

    panel.addEventListener('click', (event) => {
        if (!isOpen() || isDesktop()) {
            return;
        }

        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        const actionableElement = target.closest('a, button');
        if (!(actionableElement instanceof HTMLElement)) {
            return;
        }

        if (actionableElement === trigger) {
            return;
        }

        if (actionableElement.tagName === 'A') {
            closePanel({ restoreFocus: false });
        }
    });

    const onMediaQueryChange = () => {
        syncState();
    };

    if (typeof mediaQuery.addEventListener === 'function') {
        mediaQuery.addEventListener('change', onMediaQueryChange);
    } else if (typeof mediaQuery.addListener === 'function') {
        mediaQuery.addListener(onMediaQueryChange);
    }

    trigger.dataset.mobileSheetBound = 'true';
    syncState();
}
