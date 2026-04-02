import { showSyncNotice } from '../immersion/utils';

const PWA_INSTALL_BUTTON_SELECTOR = '[data-pwa-install-button]';

let deferredInstallPrompt = null;

export function setupPwaInstallPrompt() {
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
