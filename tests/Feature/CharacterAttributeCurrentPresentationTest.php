<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Character;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterAttributeCurrentPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_character_show_displays_attributes_as_max_and_current_with_reduced_marker(): void
    {
        $user = User::factory()->create();
        $character = Character::factory()->create([
            'user_id' => $user->id,
            'world_id' => World::resolveDefaultId(),
            'species' => 'elf',
            'calling' => 'magier',
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
            'in_current' => 45,
            'in_note' => 'Vertraut dem Druecken der Stille.',
        ]);

        $response = $this->actingAs($user)->get(route('characters.show', $character));

        $response->assertOk()
            ->assertSeeText('Anzeige: Max / Aktuell')
            ->assertSeeText('50 % / 45 %')
            ->assertSeeText('Aktuell reduziert')
            ->assertSeeText('Basis 40 %, effektiv 50 %.')
            ->assertSeeText('Bauchgefühl')
            ->assertSeeText('Vertraut dem Druecken der Stille.');
    }

    public function test_character_update_keeps_current_values_and_clamps_when_effective_max_drops(): void
    {
        $user = User::factory()->create();
        $character = Character::factory()->create([
            'user_id' => $user->id,
            'world_id' => World::resolveDefaultId(),
            'origin' => 'native_vhaltor',
            'species' => 'mensch',
            'calling' => 'abenteurer',
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
            'mu_current' => 33,
            'kk_current' => 38,
        ]);

        $response = $this->actingAs($user)->put(
            route('characters.update', $character),
            $this->characterPayload([
                'name' => $character->name,
                'species' => 'elf',
                'calling' => 'abenteurer',
                'mu' => 40,
                'kl' => 40,
                'in' => 40,
                'ch' => 40,
                'ff' => 40,
                'ge' => 40,
                'ko' => 40,
                'kk' => 40,
            ]),
        );

        $response->assertRedirect(route('characters.show', $character));

        $character->refresh();

        $this->assertSame(33, (int) $character->mu_current);
        $this->assertSame(35, (int) $character->kk_current);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function characterPayload(array $overrides = []): array
    {
        $payload = [
            'name' => 'Aldric',
            'epithet' => 'der Graupriester',
            'bio' => str_repeat('Dunkle Geschichte. ', 4),
            'origin' => 'native_vhaltor',
            'species' => 'mensch',
            'calling' => 'abenteurer',
            'concept' => 'Ich jage Wahrheiten durch Asche und Nebel.',
            'gm_secret' => 'Ich schulde der Schattenbank von Nerez einen Eid.',
            'world_connection' => 'Meine Schwester dient den Glutrichtern als Schreiberin.',
            'advantages' => ['Blutpforten-Sinn'],
            'disadvantages' => ['Aschesucht'],
            'inventory' => [[
                'name' => 'Seil 10m lang',
                'quantity' => 1,
                'equipped' => false,
            ]],
            'weapons' => [[
                'name' => 'Kurzschwert',
                'attack' => 48,
                'parry' => 41,
                'damage' => 12,
            ]],
            'armors' => [[
                'name' => 'Lederruestung',
                'protection' => 5,
                'equipped' => true,
            ]],
            'gm_note' => 'Vorteil/Nachteil fuer Kampagne freigegeben.',
            'mu' => 40,
            'kl' => 40,
            'in' => 40,
            'ch' => 40,
            'ff' => 40,
            'ge' => 40,
            'ko' => 40,
            'kk' => 40,
            'mu_note' => 'Haelt auch in Finsternis den Blick gerade.',
            'kl_note' => 'Liest Archive schneller als andere Gesichter.',
            'in_note' => 'Vertraut dem Druecken der Stille.',
            'ch_note' => 'Wirkt warm, bleibt aber unnahbar.',
            'ff_note' => 'Feine Hand bei Siegeln und Schlossnadeln.',
            'ge_note' => 'Leichtfussig trotz schwerem Mantel.',
            'ko_note' => 'Zaeh wie alter Lederpanzer.',
            'kk_note' => 'Schultert Lasten ohne Klage.',
        ];

        return array_merge($payload, $overrides);
    }
}
