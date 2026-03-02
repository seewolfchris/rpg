<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Character;
use App\Models\DiceRoll;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneBookmark;
use App\Models\SceneSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTutorialTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_tutorial_progress_for_new_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSeeText('Erste Schritte');
        $response->assertSeeText('0 / 5 abgeschlossen');
        $response->assertSeeText('Ich-Perspektive');
    }

    public function test_dashboard_marks_tutorial_steps_as_completed(): void
    {
        $user = User::factory()->create();
        $gm = User::factory()->gm()->create();

        Character::factory()->create([
            'user_id' => $user->id,
        ]);

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'is_public' => true,
            'status' => 'active',
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
        ]);

        SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'is_muted' => false,
        ]);

        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'post_type' => 'ic',
            'moderation_status' => 'approved',
        ]);

        DiceRoll::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'character_id' => null,
            'roll_mode' => DiceRoll::MODE_NORMAL,
            'modifier' => 0,
            'label' => 'Tutorial Roll',
            'rolls' => [15],
            'kept_roll' => 15,
            'total' => 15,
            'is_critical_success' => false,
            'is_critical_failure' => false,
            'created_at' => now(),
        ]);

        SceneBookmark::query()->create([
            'user_id' => $user->id,
            'scene_id' => $scene->id,
            'post_id' => $post->id,
            'label' => 'Startpunkt',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSeeText('5 / 5 abgeschlossen');
    }
}
