import assert from 'node:assert/strict';
import test from 'node:test';
import {
    DEAD_LETTER_MERGE_APPEND,
    DEAD_LETTER_MERGE_CANCEL,
    DEAD_LETTER_MERGE_REPLACE,
    mergeDeadLetterContent,
} from '../../resources/js/offline-dead-letter.mjs';

test('mergeDeadLetterContent fills empty editor content', () => {
    const merged = mergeDeadLetterContent('', 'Offline-Text', DEAD_LETTER_MERGE_REPLACE);

    assert.equal(merged, 'Offline-Text');
});

test('mergeDeadLetterContent appends when editor already has content', () => {
    const merged = mergeDeadLetterContent('Bestehender Text', 'Offline-Text', DEAD_LETTER_MERGE_APPEND);

    assert.equal(merged, 'Bestehender Text\n\nOffline-Text');
});

test('mergeDeadLetterContent replaces when requested', () => {
    const merged = mergeDeadLetterContent('Bestehender Text', 'Offline-Text', DEAD_LETTER_MERGE_REPLACE);

    assert.equal(merged, 'Offline-Text');
});

test('mergeDeadLetterContent keeps content unchanged when canceled', () => {
    const merged = mergeDeadLetterContent('Bestehender Text', 'Offline-Text', DEAD_LETTER_MERGE_CANCEL);

    assert.equal(merged, 'Bestehender Text');
});
