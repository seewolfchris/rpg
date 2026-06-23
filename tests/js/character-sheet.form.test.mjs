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

test('characterSheetForm exposes progressive wizard state without changing form payload rules', () => {
    const component = characterSheetForm({
        config: baseConfig,
        worldConfigs: {
            1: baseConfig,
        },
        attributeKeys: ['mu'],
        initial: {
            worldId: '1',
            origin: 'native_vhaltor',
            species: 'mensch',
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

    assert.equal(component.currentWizardStep, 'basics');
    assert.equal(component.wizardSteps.at(-1).label, 'Übersicht & Speichern');
    assert.equal(component.wizardProgressLabel, '1 / 6');
    assert.equal(component.wizardShowsStep('basics'), true);
    assert.equal(component.wizardShowsStep('summary'), false);
    assert.equal(component.previousWizardButtonLabel, 'Zurück im Assistenten');
    assert.equal(component.nextWizardButtonLabel, 'Weiter zu Herkunft & Berufung');
    assert.equal(component.wizardStepForField('origin'), 'options');
    assert.equal(component.wizardStepForField('in'), 'attributes');
    assert.equal(component.wizardStepForField('in_note'), 'attributes');
    assert.equal(component.wizardStepForField('bio'), 'story');
    assert.equal(component.wizardStepForField('inventory.0.name'), 'gear');

    component.nextWizardStep();
    assert.equal(component.currentWizardStep, 'options');
    assert.equal(component.selectedOriginLabel, 'Aus dieser Welt');
    assert.equal(component.selectedSpeciesLabel, 'Mensch');
    assert.equal(component.selectedCallingLabel, 'Barde');

    component.setWizardStep('summary');
    assert.equal(component.currentWizardStep, 'summary');
    assert.equal(component.wizardProgressLabel, '6 / 6');
    assert.equal(component.previousWizardButtonLabel, 'Zurück zu Ausrüstung & Avatar');
    assert.equal(component.nextWizardButtonLabel, 'Speichern prüfen');

    component.previousWizardStep();
    assert.equal(component.currentWizardStep, 'gear');

    component.setWizardStep('unknown');
    assert.equal(component.currentWizardStep, 'gear');
});

test('characterSheetForm blocks wizard progress on domain validation failures', () => {
    const component = characterSheetForm({
        config: {
            ...baseConfig,
            average_max: 50,
            callings: {
                ...baseConfig.callings,
                barde: {
                    ...baseConfig.callings.barde,
                    minimums: { mu: 50 },
                },
            },
        },
        worldConfigs: {
            1: {
                ...baseConfig,
                average_max: 50,
                callings: {
                    ...baseConfig.callings,
                    barde: {
                        ...baseConfig.callings.barde,
                        minimums: { mu: 50 },
                    },
                },
            },
        },
        attributeKeys: ['mu'],
        initial: {
            worldId: '1',
            origin: 'native_vhaltor',
            species: 'mensch',
            calling: 'barde',
            attributes: { mu: 40 },
            attributeNotes: {},
            advantages: ['Diszipliniert'],
            disadvantages: ['Misstrauisch'],
            inventory: [],
            weapons: [],
            armors: [],
        },
    });

    component.init();
    component.setWizardStep('options');

    assert.equal(component.wizardDomainValidation('options'), null);
    assert.equal(component.validateCurrentWizardStep(), true);
    assert.equal(component.currentWizardStep, 'attributes');

    assert.deepEqual(component.wizardDomainValidation('attributes'), {
        field: 'mu',
        message: 'Mut muss für die gewählte Berufung mindestens 50 % erreichen.',
    });

    component.attributes.mu = 60;
    assert.deepEqual(component.wizardDomainValidation('attributes'), {
        field: 'mu',
        message: 'Der Attributdurchschnitt darf höchstens 50 % betragen.',
    });

    component.attributes.mu = 40;
    component.advantages = ['Doppelt', 'Doppelt'];
    component.disadvantages = ['Eins', 'Zwei'];
    assert.equal(component.traitsValid, false);
    assert.equal(component.wizardDomainValidation('story')?.field, 'advantages');
});
