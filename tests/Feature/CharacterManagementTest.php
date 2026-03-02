<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_character_index(): void
    {
        $response = $this->get('/characters');

        $response->assertRedirect('/login');
    }

    public function test_user_can_create_character(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/characters', [
            'name' => 'Aldric',
            'epithet' => 'der Graupriester',
            'bio' => str_repeat('Dunkle Geschichte. ', 4),
            'strength' => 12,
            'dexterity' => 11,
            'constitution' => 14,
            'intelligence' => 13,
            'wisdom' => 15,
            'charisma' => 9,
        ]);

        $this->assertDatabaseHas('characters', [
            'user_id' => $user->id,
            'name' => 'Aldric',
            'strength' => 12,
        ]);

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
            'name' => 'Nachher',
            'epithet' => 'der Namegewandelte',
            'bio' => str_repeat('Neue Legende. ', 4),
            'strength' => 16,
            'dexterity' => 10,
            'constitution' => 13,
            'intelligence' => 11,
            'wisdom' => 12,
            'charisma' => 14,
        ]);

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'name' => 'Nachher',
            'strength' => 16,
        ]);

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
