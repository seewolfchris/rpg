import test from 'node:test';
import assert from 'node:assert/strict';

import {
    createPostDraftStorageKey,
    hasPostDraftContent,
    normalizePostDraftForRestore,
    parsePostDraftPayload,
} from '../../resources/js/post-editor-draft.js';

test('createPostDraftStorageKey builds stable key from seed', () => {
    assert.equal(createPostDraftStorageKey(' scene-12-new '), 'c76:post-draft:scene-12-new');
    assert.equal(createPostDraftStorageKey(''), '');
});

test('parsePostDraftPayload returns null for invalid payloads', () => {
    assert.equal(parsePostDraftPayload(''), null);
    assert.equal(parsePostDraftPayload('{invalid-json'), null);
    assert.equal(parsePostDraftPayload('"plain-string"'), null);
});

test('parsePostDraftPayload returns object for valid JSON object', () => {
    const parsed = parsePostDraftPayload('{"content":"Text","ic_quote":""}');

    assert.deepEqual(parsed, {
        content: 'Text',
        ic_quote: '',
    });
});

test('hasPostDraftContent detects content and quote edge cases', () => {
    assert.equal(hasPostDraftContent(null), false);
    assert.equal(hasPostDraftContent({}), false);
    assert.equal(hasPostDraftContent({ content: '   ', ic_quote: '   ' }), false);
    assert.equal(hasPostDraftContent({ content: 'Inhalt', ic_quote: '' }), true);
    assert.equal(hasPostDraftContent({ content: '', ic_quote: 'Zitat' }), true);
});

test('normalizePostDraftForRestore returns null for empty draft payload', () => {
    const normalized = normalizePostDraftForRestore({
        content: '   ',
        ic_quote: '',
        content_format: 'markdown',
    });

    assert.equal(normalized, null);
});

test('normalizePostDraftForRestore keeps content but clears incompatible options', () => {
    const normalized = normalizePostDraftForRestore(
        {
            content: 'Nebel im Torbogen',
            content_format: 'bbcode',
            post_type: 'ic',
            character_id: '9',
            ic_quote: '',
        },
        {
            allowedFormats: ['markdown', 'plain'],
            allowedPostTypes: ['ic', 'ooc'],
            allowedCharacterIds: ['2', '3'],
        },
    );

    assert.deepEqual(normalized, {
        content: 'Nebel im Torbogen',
        content_format: '',
        post_type: 'ic',
        character_id: '',
        ic_quote: '',
    });
});
