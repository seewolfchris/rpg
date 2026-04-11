/**
 * Offline queue immersion behaviors (queue panel, dead-letter handling, merge prompt and service-worker sync feedback).
 */

import {
    DEAD_LETTER_MERGE_APPEND,
    DEAD_LETTER_MERGE_CANCEL,
    DEAD_LETTER_MERGE_REPLACE,
    hasEditorContent,
    mergeDeadLetterContent,
} from '../offline-dead-letter.mjs';
import { getCsrfToken } from '../app/csrf';
import { showSyncNotice, trapFocusInElements } from './utils';

const QUEUE_DB_NAME = 'chroniken-pbp';
const QUEUE_STORE_NAME = 'postQueue';
const DEAD_LETTER_STORE_NAME = 'postDeadLetters';
const SYNC_TAG_POSTS = 'pbp-sync-posts';
const OFFLINE_POST_FORM_SELECTOR = 'form[data-offline-post-form]';
const OFFLINE_POST_CONTENT_SELECTOR = `${OFFLINE_POST_FORM_SELECTOR} textarea[name="content"]`;
const OFFLINE_DEAD_LETTER_PANEL_ID = 'offline-dead-letter-panel';
const OFFLINE_QUEUE_STATUS_PANEL_ID = 'offline-queue-status-panel';
const OFFLINE_QUEUE_PREFERENCE_FORM_SELECTOR = 'form[data-notification-preferences-form]';
const OFFLINE_QUEUE_PREFERENCE_TOGGLE_SELECTOR = 'input[data-offline-queue-toggle]';
const OFFLINE_QUEUE_ENABLED_META_SELECTOR = 'meta[name="offline-queue-enabled"]';
const SENSITIVE_QUEUE_KEYS = new Set([
    '_token',
    '_method',
    'password',
    'password_confirmation',
    'current_password',
    'new_password',
    'remember_token',
]);

function isSensitiveQueueKey(rawKey) {
    if (typeof rawKey !== 'string') {
        return true;
    }

    const key = rawKey.trim().toLowerCase();

    if (key === '') {
        return true;
    }

    if (SENSITIVE_QUEUE_KEYS.has(key)) {
        return true;
    }

    return key.includes('csrf') || key.includes('password') || key.endsWith('_token');
}

function resolveSameOriginQueueUrl(rawUrl) {
    if (typeof rawUrl !== 'string' || rawUrl.trim() === '') {
        return null;
    }

    try {
        const resolvedUrl = new URL(rawUrl, window.location.origin);

        if (resolvedUrl.origin !== window.location.origin) {
            return null;
        }

        return resolvedUrl.toString();
    } catch {
        return null;
    }
}

function resolveOfflineQueueEnabledFromDocument() {
    const preferenceNode = document.querySelector(OFFLINE_QUEUE_ENABLED_META_SELECTOR);

    if (!(preferenceNode instanceof HTMLMetaElement)) {
        return true;
    }

    const rawValue = String(preferenceNode.content || '').trim().toLowerCase();

    if (rawValue === '0' || rawValue === 'false' || rawValue === 'off' || rawValue === 'no') {
        return false;
    }

    return true;
}

function setOfflineQueueEnabledMetaContent(enabled) {
    const preferenceNode = document.querySelector(OFFLINE_QUEUE_ENABLED_META_SELECTOR);

    if (!(preferenceNode instanceof HTMLMetaElement)) {
        return;
    }

    preferenceNode.content = enabled ? '1' : '0';
}

export function createQueueModule({
    getActiveServiceWorkerRegistration,
    postMessageToActiveServiceWorker,
    resolveOfflineQueueEnabled,
}) {
    const resolveActiveServiceWorkerRegistration = typeof getActiveServiceWorkerRegistration === 'function'
        ? getActiveServiceWorkerRegistration
        : async () => null;
    const postMessageToServiceWorker = typeof postMessageToActiveServiceWorker === 'function'
        ? postMessageToActiveServiceWorker
        : async () => undefined;
    const resolveOfflineQueueEnabledFn = typeof resolveOfflineQueueEnabled === 'function'
        ? resolveOfflineQueueEnabled
        : resolveOfflineQueueEnabledFromDocument;
    let offlineQueueEnabled = resolveOfflineQueueEnabledFn();

    function isOfflineQueueEnabled() {
        return offlineQueueEnabled;
    }

    async function setOfflineQueueEnabled(enabled, { clearPrivateData = false } = {}) {
        const normalizedEnabled = Boolean(enabled);
        offlineQueueEnabled = normalizedEnabled;
        setOfflineQueueEnabledMetaContent(normalizedEnabled);

        await postMessageToServiceWorker({
            type: 'SET_OFFLINE_QUEUE_PREFERENCE',
            enabled: normalizedEnabled,
        });

        if (!normalizedEnabled || clearPrivateData) {
            await Promise.all([
                clearOfflineQueueStorage(),
                postMessageToServiceWorker({
                    type: 'CLEAR_PRIVATE_DATA',
                }),
            ]);
        }
    }

    async function triggerQueuedPostSync() {
        if (!isOfflineQueueEnabled()) {
            return false;
        }

        if (!('serviceWorker' in navigator)) {
            return false;
        }

        const registration = await resolveActiveServiceWorkerRegistration();

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

    function setupOfflinePostQueue() {
        offlineQueueEnabled = resolveOfflineQueueEnabledFn();

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
                if (!isOfflineQueueEnabled()) {
                    return;
                }

                if (navigator.onLine) {
                    return;
                }

                event.preventDefault();

                try {
                    await queuePostSubmission(form);
                    form.reset();
                    await renderOfflineQueueStatusPanel();

                    showSyncNotice('Brief in Vorbereitung: Der Beitrag wartet auf den nächsten Online-Moment.', 'success');

                    const syncTriggered = await triggerQueuedPostSync();

                    if (!syncTriggered) {
                        showSyncNotice('Kein Background Sync verfügbar. Der Brief wird beim nächsten Besuch versendet.', 'warning');
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
            if (!isOfflineQueueEnabled()) {
                await renderOfflineQueueStatusPanel();
                return;
            }

            const syncTriggered = await triggerQueuedPostSync();

            if (syncTriggered) {
                showSyncNotice('Die Wege sind wieder offen: vorgemerkte Briefe werden zugestellt.', 'success');
            }

            await renderOfflineQueueStatusPanel();
        });
    }

    function setupOfflineQueuePreferenceToggle() {
        const preferencesForm = document.querySelector(OFFLINE_QUEUE_PREFERENCE_FORM_SELECTOR);
        const optOutToggle = document.querySelector(OFFLINE_QUEUE_PREFERENCE_TOGGLE_SELECTOR);

        if (!(preferencesForm instanceof HTMLFormElement) || !(optOutToggle instanceof HTMLInputElement)) {
            return;
        }

        if (optOutToggle.dataset.offlineQueuePreferenceBound === '1') {
            return;
        }

        optOutToggle.dataset.offlineQueuePreferenceBound = '1';

        optOutToggle.addEventListener('change', async () => {
            const previousEnabled = isOfflineQueueEnabled();
            const shouldDisableQueue = optOutToggle.checked;
            const desiredEnabled = !shouldDisableQueue;

            optOutToggle.disabled = true;

            try {
                const formData = new FormData(preferencesForm);
                const response = await fetch(preferencesForm.action, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                    },
                    body: formData,
                });

                if (!response.ok) {
                    throw new Error(`Failed to persist offline queue preference (${response.status}).`);
                }

                let persistedEnabled = desiredEnabled;

                try {
                    const payload = await response.clone().json();
                    persistedEnabled = Boolean(payload?.offline_queue_enabled ?? desiredEnabled);
                } catch {
                    persistedEnabled = desiredEnabled;
                }

                await setOfflineQueueEnabled(persistedEnabled, {
                    clearPrivateData: !persistedEnabled,
                });
                await renderDeadLetterPanel();
                await renderOfflineQueueStatusPanel();

                if (persistedEnabled) {
                    showSyncNotice('Offline-Queue ist aktiv. Ungesendete Briefe können lokal zwischengespeichert werden.', 'success');
                    return;
                }

                showSyncNotice('Offline-Queue deaktiviert. Lokale Queue-Daten wurden gelöscht.', 'warning');
            } catch (error) {
                console.error('Offline queue preference update failed:', error);
                optOutToggle.checked = !previousEnabled;
                setOfflineQueueEnabledMetaContent(previousEnabled);
                showSyncNotice('Offline-Queue-Einstellung konnte nicht gespeichert werden.', 'error');
            } finally {
                offlineQueueEnabled = resolveOfflineQueueEnabledFn();
                optOutToggle.disabled = false;
            }
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

            if (data.type === 'PRIVATE_DATA_CLEARED') {
                void renderDeadLetterPanel();
                void renderOfflineQueueStatusPanel();
                return;
            }

            if (data.type === 'POST_SYNC_STARTED' || data.type === 'POST_SYNC_SUCCESS') {
                if (!isOfflineQueueEnabled()) {
                    return;
                }

                void renderOfflineQueueStatusPanel();
                return;
            }

            if (data.type === 'POST_SYNC_DEAD_LETTERED') {
                if (!isOfflineQueueEnabled()) {
                    return;
                }

                const summary = String(data.payload?.errorSummary || 'Synchronisierung fehlgeschlagen.');
                showSyncNotice(`Brief mit Zustellfehler im Entwurfsfach abgelegt: ${summary}`, 'warning');
                void renderDeadLetterPanel();
                void renderOfflineQueueStatusPanel();
                return;
            }

            if (data.type === 'POST_SYNC_AUTH_RETRY') {
                if (!isOfflineQueueEnabled()) {
                    return;
                }

                showSyncNotice('Siegel erneuert. Der Brief wird erneut auf den Weg gebracht.', 'warning');
                return;
            }

            if (data.type === 'POST_SYNC_AUTH_REQUIRED') {
                if (!isOfflineQueueEnabled()) {
                    return;
                }

                const retryHint = formatRetryHint(data.payload?.nextRetryAt);
                showSyncNotice(`Briefzustellung pausiert (Anmeldung/CSRF). Nächster Versuch ${retryHint}.`, 'warning');
                void renderOfflineQueueStatusPanel();
                return;
            }

            if (data.type === 'POST_SYNC_RETRY_SCHEDULED') {
                if (!isOfflineQueueEnabled()) {
                    return;
                }

                const status = Number(data.payload?.status || 0);

                if (status === 401 || status === 419) {
                    return;
                }

                const retryHint = formatRetryHint(data.payload?.nextRetryAt);

                if (status === 429) {
                    showSyncNotice(`Botengrenze erreicht. Nächster Zustellversuch ${retryHint}.`, 'warning');
                    return;
                }

                showSyncNotice(`Briefzustellung pausiert. Nächster Versuch ${retryHint}.`, 'warning');
                void renderOfflineQueueStatusPanel();
                return;
            }

            if (data.type === 'POST_SYNC_FINISHED') {
                if (!isOfflineQueueEnabled()) {
                    return;
                }

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

    async function queuePostSubmission(form) {
        const method = String(form.method || 'POST').toUpperCase();

        if (method !== 'POST') {
            throw new Error('Offline queue only supports POST requests.');
        }

        const resolvedUrl = resolveSameOriginQueueUrl(form.action);

        if (!resolvedUrl) {
            throw new Error('Offline queue blocked a non same-origin action URL.');
        }

        const formData = new FormData(form);
        const entries = [];

        for (const [key, value] of formData.entries()) {
            if (typeof key !== 'string' || typeof value !== 'string') {
                continue;
            }

            if (isSensitiveQueueKey(key)) {
                continue;
            }

            entries.push([key, value]);
        }

        const payload = {
            url: resolvedUrl,
            method,
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

        if (!isOfflineQueueEnabled()) {
            panel.classList.add('hidden');
            panel.innerHTML = '';
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
            ? 'Die Verbindung steht. Der nächste Sync bringt die Briefe auf den Weg.'
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

        if (!isOfflineQueueEnabled()) {
            panel.classList.add('hidden');
            panel.innerHTML = '';
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
            showSyncNotice('Kein Schreibfeld gefunden. Öffne zuerst den Editor.', 'warning');
            return;
        }

        const incomingContent = extractQueuedPostContent(deadLetter.entries);

        if (!hasEditorContent(incomingContent)) {
            showSyncNotice('Dieser Brief enthält keinen Textinhalt.', 'warning');
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
            showSyncNotice('Brief wurde an den bestehenden Entwurf angehängt.', 'success');
            return;
        }

        showSyncNotice('Brief wurde in das Schreibfeld übernommen.', 'success');
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
            title.textContent = 'Schreibfeld enthält bereits Text';
            dialog.appendChild(title);

            const description = document.createElement('p');
            description.id = 'dead-letter-merge-description';
            description.className = 'mt-2 text-sm text-stone-300';
            description.textContent = 'Wie soll der Brief übernommen werden?';
            dialog.appendChild(description);

            const actions = document.createElement('div');
            actions.className = 'mt-4 flex flex-wrap gap-2';
            dialog.appendChild(actions);

            const appendButton = document.createElement('button');
            appendButton.type = 'button';
            appendButton.className = 'rounded-md border border-emerald-600/70 bg-emerald-900/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.08em] text-emerald-100';
            appendButton.textContent = 'Anhängen (Empfohlen)';
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

            const focusables = [appendButton, replaceButton, cancelButton];
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

                trapFocusInElements(event, focusables);
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

    async function clearOfflineQueueStorage() {
        if (!('indexedDB' in window) || typeof indexedDB.deleteDatabase !== 'function') {
            return;
        }

        await new Promise((resolve) => {
            let request;

            try {
                request = indexedDB.deleteDatabase(QUEUE_DB_NAME);
            } catch {
                resolve(undefined);
                return;
            }

            request.onsuccess = () => resolve(undefined);
            request.onerror = () => resolve(undefined);
            request.onblocked = () => resolve(undefined);
        });
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

    return {
        setupOfflinePostQueue,
        setupOnlineSyncTrigger,
        setupServiceWorkerMessageHandling,
        setupOfflineQueuePreferenceToggle,
        renderDeadLetterPanel,
        renderOfflineQueueStatusPanel,
        triggerQueuedPostSync,
        setOfflineQueueEnabled,
    };
}
