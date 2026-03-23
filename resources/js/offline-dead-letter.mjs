export const DEAD_LETTER_MERGE_APPEND = 'append';
export const DEAD_LETTER_MERGE_REPLACE = 'replace';
export const DEAD_LETTER_MERGE_CANCEL = 'cancel';

export function hasEditorContent(value) {
    return String(value ?? '').trim() !== '';
}

export function mergeDeadLetterContent(currentContent, incomingContent, mode) {
    const current = String(currentContent ?? '');
    const incoming = String(incomingContent ?? '');

    if (mode === DEAD_LETTER_MERGE_REPLACE) {
        return incoming;
    }

    if (mode !== DEAD_LETTER_MERGE_APPEND) {
        return current;
    }

    if (!hasEditorContent(current)) {
        return incoming;
    }

    if (!hasEditorContent(incoming)) {
        return current;
    }

    return `${current}\n\n${incoming}`;
}
