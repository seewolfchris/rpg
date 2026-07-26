import { debounce, readLocalStorageValue, removeLocalStorageValue, showSyncNotice, writeLocalStorageValue } from '../immersion/utils';
import {
    createPostDraftStorageKey,
    hasPostDraftContent,
    normalizePostDraftForRestore,
    parsePostDraftPayload,
} from '../post-editor-draft';
import { getCsrfToken } from './csrf';
import { hasValidProbeToken, probeSubmissionNeedsPreview } from './post-probe-policy';

const POST_EDITOR_SELECTOR = 'form[data-post-editor], form[data-probe-preview-editor]';
const POST_PREVIEW_DEBOUNCE_MS = 450;
const POST_DRAFT_DEBOUNCE_MS = 350;
const PREVIEW_BLOCKED_TAGS = new Set([
    'script',
    'iframe',
    'object',
    'embed',
    'template',
    'link',
    'meta',
    'base',
    'form',
    'svg',
    'math',
]);
const PREVIEW_URL_ATTRIBUTES = new Set(['href', 'src', 'xlink:href', 'formaction', 'action', 'poster']);
const PREVIEW_UNSAFE_URL_PATTERN = /^\s*(?:javascript|vbscript|data:text\/html)/i;

function isOfflineStorageEnabled() {
    const preferenceNode = document.querySelector('meta[name="offline-queue-enabled"]');

    if (!(preferenceNode instanceof HTMLMetaElement)) {
        return false;
    }

    return ['1', 'true', 'on', 'yes'].includes(String(preferenceNode.content || '').trim().toLowerCase());
}

function sanitizePreviewHtml(rawHtml) {
    if (typeof rawHtml !== 'string' || rawHtml.trim() === '') {
        return '';
    }

    const template = document.createElement('template');
    template.innerHTML = rawHtml;

    const blockedSelector = Array.from(PREVIEW_BLOCKED_TAGS).join(',');

    if (blockedSelector !== '') {
        template.content.querySelectorAll(blockedSelector).forEach((node) => {
            node.remove();
        });
    }

    template.content.querySelectorAll('*').forEach((node) => {
        if (!(node instanceof Element)) {
            return;
        }

        Array.from(node.attributes).forEach((attribute) => {
            const name = attribute.name.toLowerCase();
            const value = String(attribute.value || '');

            if (name.startsWith('on')) {
                node.removeAttribute(attribute.name);
                return;
            }

            if (name === 'style') {
                node.removeAttribute(attribute.name);
                return;
            }

            if (PREVIEW_URL_ATTRIBUTES.has(name) && PREVIEW_UNSAFE_URL_PATTERN.test(value)) {
                node.removeAttribute(attribute.name);
            }
        });
    });

    return template.innerHTML;
}

export function setupPostEditorEnhancements() {
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
        const probeToggleField = formNode.querySelector('[data-probe-toggle]');
        const probeParamFields = formNode.querySelectorAll('[data-probe-param-field]');
        const probePreviewTrigger = formNode.querySelector('[data-probe-preview-trigger]');
        const probePreviewResultNode = formNode.querySelector('[data-probe-preview-result]');
        const probePreviewTokenStatusNode = formNode.querySelector('[data-probe-preview-token-status]');
        const previewRoot = formNode.querySelector('[data-post-preview]');
        const previewStatusNode = formNode.querySelector('[data-post-preview-status]');
        const previewOutputNode = formNode.querySelector('[data-post-preview-output]');
        const previewUrl = String(formNode.dataset.previewUrl || '').trim();
        const draftKeySeed = String(formNode.dataset.draftKey || '').trim();
        const storageKey = createPostDraftStorageKey(draftKeySeed);

        if (!(contentField instanceof HTMLTextAreaElement) || !(formatField instanceof HTMLSelectElement)) {
            return;
        }

        const hasActiveProbeToken = () => {
            return hasValidProbeToken(formNode);
        };

        const clearProbePreviewResult = (statusMessage = '') => {
            if (!hasActiveProbeToken()) {
                return;
            }

            if (probePreviewResultNode instanceof HTMLElement) {
                probePreviewResultNode.innerHTML = '';
            }

            if (probePreviewTokenStatusNode instanceof HTMLElement) {
                probePreviewTokenStatusNode.textContent = statusMessage;
                probePreviewTokenStatusNode.classList.toggle('hidden', statusMessage.trim() === '');
            }
        };

        const setProbePreviewStatus = (statusMessage) => {
            if (!(probePreviewTokenStatusNode instanceof HTMLElement)) {
                return;
            }

            probePreviewTokenStatusNode.textContent = statusMessage;
            probePreviewTokenStatusNode.classList.toggle('hidden', statusMessage.trim() === '');
        };

        const restoreDraftIfNeeded = () => {
            if (!storageKey || !isOfflineStorageEnabled() || contentField.value.trim() !== '') {
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

            if (!isOfflineStorageEnabled()) {
                removeLocalStorageValue(storageKey);
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

                previewOutputNode.innerHTML = sanitizePreviewHtml(html);
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

        if (probeParamFields.length > 0) {
            const invalidateProbeToken = () => {
                clearProbePreviewResult('Probeparameter geändert – bitte neu würfeln.');
            };

            probeParamFields.forEach((probeParamField) => {
                if (!(probeParamField instanceof HTMLInputElement || probeParamField instanceof HTMLSelectElement || probeParamField instanceof HTMLTextAreaElement)) {
                    return;
                }

                const eventName = probeParamField instanceof HTMLInputElement
                    && (probeParamField.type === 'text' || probeParamField.type === 'number')
                    ? 'input'
                    : 'change';

                probeParamField.addEventListener(eventName, invalidateProbeToken);
            });
        }

        if (probeToggleField instanceof HTMLInputElement) {
            probeToggleField.addEventListener('change', () => {
                if (!probeToggleField.checked) {
                    clearProbePreviewResult('');
                    setProbePreviewStatus('');
                    return;
                }

                if (hasActiveProbeToken()) {
                    setProbePreviewStatus('Vorabwurf aktiv. Das Ergebnis wird beim Speichern unverändert übernommen.');
                }
            });
        }

        if (probePreviewTrigger instanceof HTMLElement) {
            probePreviewTrigger.addEventListener('click', () => {
                setProbePreviewStatus('Probe wird serverseitig gewürfelt ...');
            });
        }

        formNode.addEventListener('htmx:afterSwap', (event) => {
            const target = event.detail?.target;

            if (!(target instanceof HTMLElement) || target !== probePreviewResultNode) {
                return;
            }

            const tokenField = target.querySelector('input[name="probe_roll_token"]');
            if (tokenField instanceof HTMLInputElement && tokenField.value.trim() !== '') {
                setProbePreviewStatus('Vorabwurf aktiv. Das Ergebnis wird beim Speichern unverändert übernommen.');
            } else {
                setProbePreviewStatus('');
            }
        });

        formNode.addEventListener('htmx:responseError', (event) => {
            const target = event.detail?.target;

            if (target instanceof HTMLElement && target === probePreviewResultNode) {
                setProbePreviewStatus('Probe konnte nicht gewürfelt werden. Bitte Eingaben prüfen.');
            }
        });

        if (hasActiveProbeToken()) {
            setProbePreviewStatus('Vorabwurf aktiv. Das Ergebnis wird beim Speichern unverändert übernommen.');
        }

        formNode.addEventListener('submit', (event) => {
            if (probeSubmissionNeedsPreview(formNode)) {
                event.preventDefault();
                setProbePreviewStatus('Bitte würfle die aktivierte Probe vor dem Veröffentlichen.');

                if (probePreviewTrigger instanceof HTMLElement) {
                    probePreviewTrigger.focus();
                }

                return;
            }

            if (event.defaultPrevented) {
                return;
            }

            if (storageKey) {
                removeLocalStorageValue(storageKey);
            }
        });
    });
}
