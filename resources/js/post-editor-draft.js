export function createPostDraftStorageKey(seed) {
    const normalizedSeed = String(seed ?? '').trim();

    if (normalizedSeed === '') {
        return '';
    }

    return `c76:post-draft:${normalizedSeed}`;
}

export function parsePostDraftPayload(rawValue) {
    if (typeof rawValue !== 'string' || rawValue.trim() === '') {
        return null;
    }

    try {
        const parsed = JSON.parse(rawValue);

        if (!parsed || typeof parsed !== 'object') {
            return null;
        }

        return parsed;
    } catch {
        return null;
    }
}

export function hasPostDraftContent(draft) {
    if (!draft || typeof draft !== 'object') {
        return false;
    }

    const content = String(draft.content ?? '').trim();
    const icQuote = String(draft.ic_quote ?? '').trim();

    return content !== '' || icQuote !== '';
}

function normalizeOptionSet(values) {
    if (!Array.isArray(values)) {
        return new Set();
    }

    return new Set(values.map((value) => String(value ?? '').trim()).filter((value) => value !== ''));
}

export function normalizePostDraftForRestore(draft, options = {}) {
    if (!hasPostDraftContent(draft)) {
        return null;
    }

    const allowedFormats = normalizeOptionSet(options.allowedFormats ?? []);
    const allowedPostTypes = normalizeOptionSet(options.allowedPostTypes ?? []);
    const allowedCharacterIds = normalizeOptionSet(options.allowedCharacterIds ?? []);

    const normalized = {
        content: String(draft.content ?? ''),
        content_format: String(draft.content_format ?? '').trim(),
        post_type: String(draft.post_type ?? '').trim(),
        character_id: String(draft.character_id ?? '').trim(),
        ic_quote: String(draft.ic_quote ?? ''),
    };

    if (allowedFormats.size > 0 && !allowedFormats.has(normalized.content_format)) {
        normalized.content_format = '';
    }

    if (allowedPostTypes.size > 0 && !allowedPostTypes.has(normalized.post_type)) {
        normalized.post_type = '';
    }

    if (allowedCharacterIds.size > 0 && !allowedCharacterIds.has(normalized.character_id)) {
        normalized.character_id = '';
    }

    return normalized;
}
