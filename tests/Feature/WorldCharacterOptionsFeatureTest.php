<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorldCharacterOptionsFeatureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function characterPayload(int $worldId, array $overrides = []): array
    {
        $payload = [
            'world_id' => $worldId,
            'name' => 'Testfigur',
            'epithet' => 'die Wandelnde',
            'bio' => str_repeat('Laengere Hintergrundgeschichte. ', 4),
            'status' => 'active',
            'origin' => 'native_vhaltor',
            'species' => 'mensch',
            'calling' => 'abenteurer',
            'concept' => 'Ich ueberlebe durch Anpassung und klare Entscheidungen.',
            'gm_secret' => 'Ich schulde einem Mentor aus alten Zeiten einen Eid.',
            'world_connection' => 'Ich habe einen starken Bezug zur aktuellen Welt.',
            'advantages' => ['Ruhe unter Druck'],
            'disadvantages' => ['Uebervorsichtig'],
            'inventory' => [[
                'name' => 'Notizbuch',
                'quantity' => 1,
                'equipped' => false,
            ]],
            'weapons' => [[
                'name' => 'Dienstwaffe',
                'attack' => 45,
                'parry' => 35,
                'damage' => 10,
            ]],
            'armors' => [[
                'name' => 'Schutzweste',
                'protection' => 3,
                'equipped' => true,
            ]],
            'mu' => 40,
            'kl' => 40,
            'in' => 40,
            'ch' => 40,
            'ff' => 40,
            'ge' => 40,
            'ko' => 40,
            'kk' => 40,
            'mu_note' => 'Mutig in Krisen.',
            'kl_note' => 'Analytisch und schnell.',
            'in_note' => 'Spuert Nuancen frueh.',
            'ch_note' => 'Kann Menschen mitnehmen.',
            'ff_note' => 'Praezise unter Last.',
            'ge_note' => 'Beweglich und reaktiv.',
            'ko_note' => 'Belastbar ueber Zeit.',
            'kk_note' => 'Koerperlich robust.',
        ];

        return array_merge($payload, $overrides);
    }

    public function test_world_specific_character_creation_accepts_and_rejects_expected_combinations(): void
    {
        $user = User::factory()->create();
        $gegenwart = World::query()->where('slug', 'gegenwart')->firstOrFail();
        $scifi = World::query()->where('slug', 'sci-fi')->firstOrFail();

        $this->actingAs($user)
            ->post(route('characters.store'), $this->characterPayload((int) $gegenwart->id, [
                'name' => 'Gegenwart-Valid',
                'species' => 'mensch',
                'calling' => 'polizist',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('characters', [
            'name' => 'Gegenwart-Valid',
            'world_id' => (int) $gegenwart->id,
            'species' => 'mensch',
            'calling' => 'polizist',
        ]);

        $this->actingAs($user)
            ->from(route('characters.create', ['world' => 'gegenwart']))
            ->post(route('characters.store'), $this->characterPayload((int) $gegenwart->id, [
                'name' => 'Gegenwart-Ungueltig',
                'species' => 'elf',
                'calling' => 'polizist',
            ]))
            ->assertRedirect(route('characters.create', ['world' => 'gegenwart']))
            ->assertSessionHasErrors('species');

        $this->actingAs($user)
            ->from(route('characters.create', ['world' => 'sci-fi']))
            ->post(route('characters.store'), $this->characterPayload((int) $scifi->id, [
                'name' => 'Scifi-Ungueltig',
                'species' => 'mensch',
                'calling' => 'kommissar',
            ]))
            ->assertRedirect(route('characters.create', ['world' => 'sci-fi']))
            ->assertSessionHasErrors('calling');
    }

    public function test_legacy_species_remains_updatable_when_world_is_unchanged(): void
    {
        $user = User::factory()->create();
        $gegenwart = World::query()->where('slug', 'gegenwart')->firstOrFail();

        $character = Character::factory()->create([
            'user_id' => $user->id,
            'world_id' => (int) $gegenwart->id,
            'status' => 'active',
            'origin' => 'native_vhaltor',
            'species' => 'elf',
            'calling' => 'polizist',
            'bio' => str_repeat('Bestehender Charakter. ', 4),
            'concept' => 'Altbestand',
            'gm_secret' => 'Altbestand bleibt bestehen.',
            'world_connection' => 'Altbestand Weltbezug.',
            'advantages' => ['Diszipliniert'],
            'disadvantages' => ['Misstrauisch'],
            'mu' => 40,
            'kl' => 40,
            'in' => 40,
            'ch' => 40,
            'ff' => 40,
            'ge' => 40,
            'ko' => 40,
            'kk' => 40,
            'strength' => 40,
            'dexterity' => 40,
            'constitution' => 40,
            'intelligence' => 40,
            'wisdom' => 40,
            'charisma' => 40,
        ]);

        $this->actingAs($user)
            ->from(route('characters.edit', $character))
            ->put(route('characters.update', $character), $this->characterPayload((int) $gegenwart->id, [
                'name' => $character->name,
                'species' => 'elf',
                'calling' => 'polizist',
                'bio' => $character->bio,
            ]))
            ->assertRedirect(route('characters.show', $character));

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'species' => 'elf',
            'calling' => 'polizist',
        ]);
    }

    public function test_admin_can_import_template_and_add_custom_calling_for_new_world(): void
    {
        $admin = User::factory()->admin()->create();
        $player = User::factory()->create();

        $world = World::factory()->create([
            'name' => 'Orbital Delta',
            'slug' => 'orbital-delta',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.worlds.character-options.import-template', $world), [
                'template_key' => 'sci-fi',
            ])
            ->assertRedirect(route('admin.worlds.edit', $world));

        $this->assertDatabaseHas('world_species', [
            'world_id' => (int) $world->id,
            'key' => 'xeno',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.worlds.calling-options.store', $world), [
                'key' => 'signalanalyst',
                'label' => 'Signalanalyst',
                'description' => 'Auswertung von Sensorik und Kommunikationsdaten.',
                'minimums_json' => '{}',
                'bonuses_json' => '{"attributes":{"kl":5}}',
                'position' => 900,
                'is_active' => 1,
                'is_magic_capable' => 0,
                'is_custom' => 1,
                'is_template' => 0,
            ])
            ->assertRedirect(route('admin.worlds.edit', $world));

        $this->assertDatabaseHas('world_callings', [
            'world_id' => (int) $world->id,
            'key' => 'signalanalyst',
            'is_custom' => true,
        ]);

        $this->actingAs($player)
            ->post(route('characters.store'), $this->characterPayload((int) $world->id, [
                'name' => 'Custom Calling Character',
                'species' => 'mensch',
                'calling' => 'signalanalyst',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('characters', [
            'name' => 'Custom Calling Character',
            'world_id' => (int) $world->id,
            'calling' => 'signalanalyst',
        ]);
    }
}
