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
            'le_max' => 42,
            'le_current' => 42,
            'ae_max' => 40,
            'ae_current' => 40,
        ]);

        $this->assertSame(['Blutpforten-Sinn'], $character->advantages);
        $this->assertSame(['Aschesucht'], $character->disadvantages);

        $response->assertRedirect();
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
