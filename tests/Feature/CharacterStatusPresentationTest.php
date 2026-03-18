<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterStatusPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_filters_characters_by_selected_status(): void
    {
        $user = User::factory()->create();
        $world = World::resolveDefault();

        $pausedCharacter = Character::factory()->create([
            'user_id' => $user->id,
            'world_id' => $world->id,
            'name' => 'Mara',
            'status' => 'pause',
        ]);
        $activeCharacter = Character::factory()->create([
            'user_id' => $user->id,
            'world_id' => $world->id,
            'name' => 'Tarin',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get(route('characters.index', [
            'world' => $world->slug,
            'status' => 'pause',
        ]));

        $response->assertOk()
            ->assertSeeText($pausedCharacter->name)
            ->assertDontSeeText($activeCharacter->name)
            ->assertSeeText('Pause');
    }

    public function test_character_profile_shows_status_banner_and_attribute_descriptions(): void
    {
        $user = User::factory()->create();
        $character = Character::factory()->create([
            'user_id' => $user->id,
            'status' => 'deceased',
            'name' => 'Edrin',
        ]);

        $response = $this->actingAs($user)->get(route('characters.show', $character));

        $response->assertOk()
            ->assertSeeText('Status: Verstorben')
            ->assertSeeText('Willenskraft, Standhaftigkeit');
    }
}
