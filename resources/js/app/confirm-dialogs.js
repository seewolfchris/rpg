let confirmDialogsInitialized = false;

export function setupFormSubmitConfirmDialogs() {
    if (confirmDialogsInitialized) {
        return;
    }

    document.addEventListener('submit', (event) => {
        const target = event.target;

        if (!(target instanceof HTMLFormElement)) {
            return;
        }

        const message = String(target.dataset.confirm || '').trim();

        if (message === '') {
            return;
        }

        if (!window.confirm(message)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);

    confirmDialogsInitialized = true;
}
