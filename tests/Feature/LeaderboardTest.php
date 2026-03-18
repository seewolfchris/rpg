<?php

namespace Tests\Feature;

use App\Models\PointEvent;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_ordered_leaderboard(): void
    {
        $viewer = User::factory()->create(['points' => 15]);
        $high = User::factory()->create(['name' => 'High Player', 'points' => 50]);
        $mid = User::factory()->create(['name' => 'Mid Player', 'points' => 30]);
        $low = User::factory()->create(['name' => 'Low Player', 'points' => 10]);

        PointEvent::query()->create([
            'user_id' => $high->id,
            'source_type' => 'post',
            'source_id' => 101,
            'event_key' => 'approved',
            'points' => 10,
            'meta' => null,
            'created_at' => now(),
        ]);
        PointEvent::query()->create([
            'user_id' => $high->id,
            'source_type' => 'post',
            'source_id' => 102,
            'event_key' => 'approved',
            'points' => 10,
            'meta' => null,
            'created_at' => now(),
        ]);
        PointEvent::query()->create([
            'user_id' => $mid->id,
            'source_type' => 'post',
            'source_id' => 103,
            'event_key' => 'approved',
            'points' => 10,
            'meta' => null,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($viewer)->get(route('leaderboard.index'));

        $response->assertOk();
        $response->assertSee('Rangliste der Chronisten');
        $response->assertSeeInOrder(['High Player', 'Mid Player', 'Low Player']);
        $response->assertSee('Dein aktueller Rang:');
        $response->assertSee('#3');
    }

    public function test_guest_cannot_access_leaderboard(): void
    {
        $response = $this->get(route('leaderboard.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_leaderboard_can_show_active_characters_this_week_when_feature_is_enabled(): void
    {
        config(['features.wave4.active_characters_week' => true]);

        $viewer = User::factory()->create(['points' => 1]);
        $gm = User::factory()->gm()->create();
        $playerA = User::factory()->create();
        $playerB = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $characterA = Character::factory()->create([
            'user_id' => $playerA->id,
            'world_id' => $campaign->world_id,
            'name' => 'Ari',
        ]);
        $characterB = Character::factory()->create([
            'user_id' => $playerB->id,
            'world_id' => $campaign->world_id,
            'name' => 'Borin',
        ]);

        Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $playerA->id,
            'character_id' => $characterA->id,
            'post_type' => 'ic',
            'created_at' => now()->subDay(),
        ]);
        Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $playerA->id,
            'character_id' => $characterA->id,
            'post_type' => 'ic',
            'created_at' => now()->subHours(2),
        ]);
        Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $playerB->id,
            'character_id' => $characterB->id,
            'post_type' => 'ic',
            'created_at' => now()->subHours(5),
        ]);

        $response = $this->actingAs($viewer)->get(route('leaderboard.index'));

        $response->assertOk()
            ->assertSeeText('Aktive Charaktere diese Woche')
            ->assertSeeInOrder(['Ari', 'Borin']);
    }
}
