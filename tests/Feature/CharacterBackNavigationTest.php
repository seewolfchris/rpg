<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterBackNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_character_show_uses_index_fallback_back_link(): void
    {
        $user = User::factory()->create();
        $character = Character::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('characters.show', ['character' => $character]));

        $response->assertOk();
        $response->assertSee('href="/characters"', false);
    }

    public function test_character_edit_uses_explicit_return_to_for_back_link_and_hidden_field(): void
    {
        $user = User::factory()->create();
        $character = Character::factory()->create([
            'user_id' => $user->id,
        ]);

        $returnTo = '/notifications?page=2';

        $response = $this->actingAs($user)->get(route('characters.edit', [
            'character' => $character,
            'return_to' => $returnTo,
        ]));

        $response->assertOk();
        $response->assertSee('href="'.$returnTo.'"', false);
        $response->assertSee('name="return_to"', false);
        $response->assertSee('value="'.$returnTo.'"', false);
    }
}
