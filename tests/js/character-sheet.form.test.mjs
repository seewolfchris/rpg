import assert from 'node:assert/strict';
import test from 'node:test';

import { characterSheetForm } from '../../resources/js/character-sheet.js';

const baseConfig = {
    origins: {
        real_world_beginner: 'Real-World Anfänger',
        native_vhaltor: 'Aus dieser Welt',
    },
    origin_species_constraints: {
        real_world_beginner: ['mensch'],
    },
    species: {
        mensch: {
            label: 'Mensch',
        },
        elf: {
            label: 'Elf',
        },
    },
    callings: {
        barde: {
            label: 'Barde',
            real_world_only: false,
        },
        realworld_tech: {
            label: 'Technik / IT',
            real_world_only: true,
        },
        eigene: {
            label: 'Eigene',
            custom: true,
        },
    },
    attributes: {
        mu: {
            label: 'Mut',
            min: 30,
            max: 60,
        },
    },
    traits: {
        min: 1,
        max: 3,
    },
};

test('characterSheetForm filters callings by origin and resets invalid selection', () => {
    const component = characterSheetForm({
        config: baseConfig,
        worldConfigs: {
            1: baseConfig,
        },
        attributeKeys: ['mu'],
        initial: {
            worldId: '1',
            origin: 'real_world_beginner',
            species: 'elf',
            calling: 'barde',
            attributes: { mu: 40 },
            attributeNotes: {},
            advantages: ['Diszipliniert'],
            disadvantages: ['Misstrauisch'],
            inventory: [{ name: 'Notizbuch', quantity: 1, equipped: false }],
            weapons: [{ name: 'Dolch', attack: 35, parry: 30, damage: 8 }],
            armors: [{ name: 'Leder', protection: 1, equipped: false }],
        },
    });

    component.init();

    assert.equal(component.species, 'mensch');
    assert.deepEqual(
        component.visibleCallingEntries.map((entry) => entry.key),
        ['realworld_tech', 'eigene']
    );
    assert.equal(component.calling, 'realworld_tech');

    component.origin = 'native_vhaltor';
    component.enforceOriginSelections();

    assert.deepEqual(
        component.visibleCallingEntries.map((entry) => entry.key),
        ['barde', 'eigene']
    );
    assert.equal(component.calling, 'barde');
});
