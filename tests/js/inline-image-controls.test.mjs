import test from 'node:test';
import assert from 'node:assert/strict';

import {
    insertInlineImageMarker,
    projectInlineImageSlots,
} from '../../resources/js/app/inline-image-controls.js';

test('insertInlineImageMarker replaces selected text and places cursor after marker', () => {
    const result = insertInlineImageMarker('Vorher Auswahl Nachher', 7, 14, '[bild:2]');

    assert.deepEqual(result, {
        value: 'Vorher [bild:2] Nachher',
        selectionStart: 15,
        selectionEnd: 15,
    });
});

test('projectInlineImageSlots keeps first valid existing slot and treats duplicates as unslotted', () => {
    const projection = projectInlineImageSlots([
        { id: 10, slot: 2, removed: false },
        { id: 11, slot: 2, removed: false },
        { id: 12, slot: 9, removed: false },
    ], [], 4);

    assert.equal(projection.existing[0].projectedSlot, 2);
    assert.equal(projection.existing[1].projectedSlot, 1);
    assert.equal(projection.existing[2].projectedSlot, 3);
    assert.deepEqual(projection.freeSlots, [4]);
});

test('projectInlineImageSlots frees removed slots for newly selected files', () => {
    const projection = projectInlineImageSlots([
        { id: 10, slot: 1, removed: true },
        { id: 11, slot: 2, removed: false },
    ], [
        { name: 'new-a.jpg' },
        { name: 'new-b.jpg' },
    ], 4);

    assert.equal(projection.existing[0].projectedSlot, null);
    assert.equal(projection.existing[1].projectedSlot, 2);
    assert.equal(projection.files[0].projectedSlot, 1);
    assert.equal(projection.files[1].projectedSlot, 3);
});

test('projectInlineImageSlots restores existing slot when removal is undone', () => {
    const removedProjection = projectInlineImageSlots([
        { id: 10, slot: 1, removed: true },
        { id: 11, slot: 2, removed: false },
    ], [
        { name: 'new-a.jpg' },
    ], 4);

    const restoredProjection = projectInlineImageSlots([
        { id: 10, slot: 1, removed: false },
        { id: 11, slot: 2, removed: false },
    ], [
        { name: 'new-a.jpg' },
    ], 4);

    assert.equal(removedProjection.files[0].projectedSlot, 1);
    assert.equal(restoredProjection.existing[0].projectedSlot, 1);
    assert.equal(restoredProjection.files[0].projectedSlot, 3);
});

test('projectInlineImageSlots reports files beyond the four stored image limit without markers', () => {
    const projection = projectInlineImageSlots([
        { id: 10, slot: 1, removed: false },
        { id: 11, slot: 2, removed: false },
        { id: 12, slot: 3, removed: false },
    ], [
        { name: 'new-a.jpg' },
        { name: 'new-b.jpg' },
    ], 4);

    assert.equal(projection.files[0].projectedSlot, 4);
    assert.equal(projection.files[0].marker, '[bild:4]');
    assert.equal(projection.files[1].projectedSlot, null);
    assert.equal(projection.files[1].marker, null);
    assert.equal(projection.activeStoredCount, 5);
});
