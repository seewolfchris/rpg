import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

import { Parser, Tokenizer } from '../../node_modules/@alpinejs/csp/src/parser.js';
import { postFormState } from '../../resources/js/app/post-form-state.js';

test('post form state keeps mode invariants and clears character in GM mode', () => {
    const state = postFormState({
        postType: 'ic',
        postMode: 'gm',
        contentFormat: 'markdown',
        probeEnabled: true,
    });
    const characterField = { value: '42' };
    state.$refs = { characterIdField: characterField };

    state.syncPostModeState();

    assert.equal(state.isGmMode(), true);
    assert.equal(characterField.value, '');
    assert.match(state.formatHint(), /Markdown aktiv/);

    state.postType = 'ooc';
    state.syncPostModeState();

    assert.equal(state.postMode, 'character');
    assert.equal(state.isGmMode(), false);
});

test('post form uses an Alpine CSP-compatible registered component expression', async () => {
    const expression = 'postFormState({postType: "ic", postMode: "character", contentFormat: "plain", probeEnabled: false})';

    assert.doesNotThrow(() => new Parser(new Tokenizer(expression).tokenize()).parse());

    const template = await readFile(new URL('../../resources/views/posts/_form.blade.php', import.meta.url), 'utf8');

    assert.match(template, /x-data="postFormState\(/);
    assert.doesNotMatch(template, /x-init=/);
    assert.doesNotMatch(template, /isGmMode\(\)\s*\{/);
});
