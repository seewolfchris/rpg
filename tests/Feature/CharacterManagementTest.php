<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterManagementTest extends TestCase
{
    use RefreshDatabase;

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
            'gm_note' => 'Vorteil/Nachteil fuer Kampagne freigegeben.',
            'mu' => 40,
            'kl' => 45,
            'in' => 40,
            'ch' => 35,
            'ff' => 40,
            'ge' => 40,
            'ko' => 45,
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

    public function test_guest_cannot_access_character_index(): void
    {
        $response = $this->get('/characters');

        $response->assertRedirect('/login');
    }

    public function test_character_create_page_loads_character_sheet_bootstrap_script(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('characters.create'));

        $response
            ->assertOk()
            ->assertSee('character-sheet.global.js', false)
            ->assertSee('window.characterSheetForm', false);
    }

    public function test_user_can_create_character(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/characters', $this->characterPayload());

        $character = Character::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'origin' => 'native_vhaltor',
            'species' => 'mensch',
            'calling' => 'abenteurer',
            'concept' => 'Ich jage Wahrheiten durch Asche und Nebel.',
            'strength' => 40,
            'mu' => 40,
            'kl' => 45,
            'in' => 40,
            'ch' => 35,
            'ff' => 40,
            'ge' => 40,
            'ko' => 45,
            'kk' => 40,
            'mu_note' => 'Haelt auch in Finsternis den Blick gerade.',
            'kk_note' => 'Schultert Lasten ohne Klage.',
            'le_max' => 42,
            'le_current' => 42,
            'ae_max' => 40,
            'ae_current' => 40,
        ]);

        $this->assertSame(['Blutpforten-Sinn'], $character->advantages);
        $this->assertSame(['Aschesucht'], $character->disadvantages);

        $response->assertRedirect();
    }

    public function test_real_world_beginner_must_use_mensch_species(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('characters.create'))
            ->post(route('characters.store'), $this->characterPayload([
                'origin' => 'real_world_beginner',
                'species' => 'elf',
            ]));

        $response->assertRedirect(route('characters.create'));
        $response->assertSessionHasErrors('species');
        $this->assertDatabaseCount('characters', 0);
    }

    public function test_character_sheet_min_validation_shows_readable_messages_instead_of_translation_keys(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('characters.create'))
            ->post(route('characters.store'), $this->characterPayload([
                'concept' => 'Kurz',
                'gm_secret' => 'zu kurz',
                'world_connection' => 'kurz',
            ]));

        $response->assertRedirect(route('characters.create'));
        $response->assertSessionHasErrors(['concept', 'gm_secret', 'world_connection']);

        /** @var \Illuminate\Support\ViewErrorBag|null $errors */
        $errors = session('errors');
        $this->assertNotNull($errors);

        $messages = $errors->getBag('default')->all();

        $this->assertFalse(in_array('validation.min.string', $messages, true));
        $this->assertTrue(
            collect($messages)->contains(
                fn (string $message): bool => str_contains($message, 'mindestens')
            )
        );
    }

    public function test_character_sheet_shows_specific_required_messages_for_traits(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('characters.create'))
            ->post(route('characters.store'), $this->characterPayload([
                'advantages' => [],
                'disadvantages' => [],
            ]));

        $response->assertRedirect(route('characters.create'));
        $response->assertSessionHasErrors(['advantages', 'disadvantages']);

        /** @var \Illuminate\Support\ViewErrorBag|null $errors */
        $errors = session('errors');
        $this->assertNotNull($errors);

        $messages = $errors->getBag('default')->all();

        $this->assertContains('Bitte mindestens einen Vorteil eintragen.', $messages);
        $this->assertContains('Bitte mindestens einen Nachteil eintragen.', $messages);
    }

    public function test_user_cannot_view_other_users_character(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $character = Character::factory()->create([
            'user_id' => $owner->id,
        ]);

        $response = $this->actingAs($intruder)->get(route('characters.show', $character));

        $response->assertForbidden();
    }

    public function test_character_edit_form_prefills_existing_sheet_values(): void
    {
        $user = User::factory()->create();

        $character = Character::factory()->create([
            'user_id' => $user->id,
            'origin' => 'native_vhaltor',
            'species' => 'elf',
            'calling' => 'gelehrter',
            'concept' => 'Reliktjaeger aus den verbrannten Archiven.',
            'world_connection' => 'Verbindung zur Glutpforte von Erest.',
            'gm_secret' => 'Schwur im schwarzen Archiv.',
            'advantages' => ['Klingenfokus'],
            'disadvantages' => ['Blutschuld'],
        ]);

        $response = $this->actingAs($user)->get(route('characters.edit', $character));

        $response->assertOk()
            ->assertSeeText('Reliktjaeger aus den verbrannten Archiven.')
            ->assertSeeText('Verbindung zur Glutpforte von Erest.')
            ->assertSeeText('Schwur im schwarzen Archiv.');

        $content = $response->getContent();

        $this->assertMatchesRegularExpression('/name="origin" value="native_vhaltor"[^>]*checked/', $content);
        $this->assertMatchesRegularExpression('/name="species" value="elf"[^>]*checked/', $content);
        $this->assertMatchesRegularExpression('/name="calling" value="gelehrter"[^>]*checked/', $content);
    }

    public function test_user_can_update_own_character(): void
    {
        $user = User::factory()->create();

        $character = Character::factory()->create([
            'user_id' => $user->id,
            'name' => 'Vorher',
        ]);

        $response = $this->actingAs($user)->put(route('characters.update', $character), [
            ...$this->characterPayload([
                'name' => 'Nachher',
                'epithet' => 'der Namegewandelte',
                'bio' => str_repeat('Neue Legende. ', 4),
                'calling' => 'ritter',
                'mu' => 45,
                'kl' => 42,
                'in' => 40,
                'ch' => 36,
                'ff' => 38,
                'ge' => 37,
                'ko' => 44,
                'kk' => 46,
            ]),
        ]);

        $character->refresh();

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'name' => 'Nachher',
            'calling' => 'ritter',
            'strength' => 46,
            'mu' => 45,
            'kl' => 42,
            'in' => 40,
            'ch' => 36,
            'ff' => 38,
            'ge' => 37,
            'ko' => 44,
            'kk' => 46,
            'le_max' => 50,
            'le_current' => 50,
            'ae_max' => 39,
            'ae_current' => 39,
        ]);

        $this->assertSame(['Blutpforten-Sinn'], $character->advantages);
        $this->assertSame(['Aschesucht'], $character->disadvantages);

        $response->assertRedirect(route('characters.show', $character));
    }

    public function test_updating_character_keeps_current_pools_and_ignores_injected_pool_values(): void
    {
        $user = User::factory()->create();

        $character = Character::factory()->create([
            'user_id' => $user->id,
            'le_max' => 42,
            'le_current' => 17,
            'ae_max' => 40,
            'ae_current' => 12,
        ]);

        $response = $this->actingAs($user)->put(route('characters.update', $character), [
            ...$this->characterPayload([
                'name' => 'Pool bleibt',
                'calling' => 'abenteurer',
            ]),
            // Manipulationsversuch: diese Werte duerfen nicht uebernommen werden.
            'le_current' => 999,
            'ae_current' => 999,
        ]);

        $character->refresh();

        $this->assertSame(17, (int) $character->le_current);
        $this->assertSame(12, (int) $character->ae_current);
        $this->assertSame(42, (int) $character->le_max);
        $this->assertSame(40, (int) $character->ae_max);

        $response->assertRedirect(route('characters.show', $character));
    }

    public function test_effective_attributes_match_form_logic_species_modifiers_only(): void
    {
        $user = User::factory()->create();

        $character = Character::factory()->create([
            'user_id' => $user->id,
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
        ]);

        $effective = (array) $character->effective_attributes;

        $this->assertSame(40, (int) ($effective['kl'] ?? 0)); // Berufungsbonus KL+5 darf hier nicht eingerechnet werden.
        $this->assertSame(50, (int) ($effective['in'] ?? 0)); // Speziesbonus Elf +10 wird eingerechnet.
        $this->assertSame(50, (int) ($effective['ch'] ?? 0)); // Speziesbonus Elf +10 wird eingerechnet.
        $this->assertSame(35, (int) ($effective['kk'] ?? 0)); // Speziesmalus Elf -5 wird eingerechnet.
    }

    public function test_user_can_delete_own_character(): void
    {
        $user = User::factory()->create();

        $character = Character::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->delete(route('characters.destroy', $character));

        $response->assertRedirect(route('characters.index'));
        $this->assertDatabaseMissing('characters', ['id' => $character->id]);
    }
}
