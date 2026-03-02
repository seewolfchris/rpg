<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SceneSubscriptionDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_filters_by_status_and_search_query(): void
    {
        $user = User::factory()->create();
        $gm = User::factory()->gm()->create();

        $campaignA = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'title' => 'Asche von Doran',
            'status' => 'active',
            'is_public' => true,
        ]);
        $campaignB = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'title' => 'Nebel von Khar',
            'status' => 'active',
            'is_public' => true,
        ]);

        $sceneActive = Scene::factory()->create([
            'campaign_id' => $campaignA->id,
            'created_by' => $gm->id,
            'title' => 'Tor der Klingen',
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $sceneMuted = Scene::factory()->create([
            'campaign_id' => $campaignB->id,
            'created_by' => $gm->id,
            'title' => 'Schattenfall',
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        SceneSubscription::query()->create([
            'scene_id' => $sceneActive->id,
            'user_id' => $user->id,
            'is_muted' => false,
        ]);
        SceneSubscription::query()->create([
            'scene_id' => $sceneMuted->id,
            'user_id' => $user->id,
            'is_muted' => true,
        ]);

        $mutedResponse = $this->actingAs($user)->get(route('scene-subscriptions.index', [
            'status' => 'muted',
        ]));

        $mutedResponse->assertOk();
        $mutedResponse->assertSee('Schattenfall');
        $mutedResponse->assertDontSee('Tor der Klingen');

        $searchResponse = $this->actingAs($user)->get(route('scene-subscriptions.index', [
            'status' => 'all',
            'q' => 'Doran',
        ]));

        $searchResponse->assertOk();
        $searchResponse->assertSee('Tor der Klingen');
        $searchResponse->assertDontSee('Schattenfall');
    }

    public function test_bulk_actions_update_filtered_scene_subscriptions(): void
    {
        $user = User::factory()->create();
        $gm = User::factory()->gm()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        $activeScene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'title' => 'Aktive Halle',
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $mutedScene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'title' => 'Stumme Halle',
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        SceneSubscription::query()->create([
            'scene_id' => $activeScene->id,
            'user_id' => $user->id,
            'is_muted' => false,
        ]);
        SceneSubscription::query()->create([
            'scene_id' => $mutedScene->id,
            'user_id' => $user->id,
            'is_muted' => true,
        ]);

        $this->actingAs($user)->patch(route('scene-subscriptions.bulk-update'), [
            'bulk_action' => 'mute_filtered',
            'status' => 'active',
            'q' => '',
        ])->assertRedirect();

        $this->assertDatabaseHas('scene_subscriptions', [
            'scene_id' => $activeScene->id,
            'user_id' => $user->id,
            'is_muted' => true,
        ]);

        $this->actingAs($user)->patch(route('scene-subscriptions.bulk-update'), [
            'bulk_action' => 'unfollow_filtered',
            'status' => 'muted',
            'q' => 'Stumme',
        ])->assertRedirect();

        $this->assertDatabaseMissing('scene_subscriptions', [
            'scene_id' => $mutedScene->id,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('scene_subscriptions', [
            'scene_id' => $activeScene->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_dashboard_hides_subscriptions_to_inaccessible_private_campaigns(): void
    {
        $user = User::factory()->create();
        $gm = User::factory()->gm()->create();

        $privateCampaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'title' => 'Verborgene Krone',
            'status' => 'active',
            'is_public' => false,
        ]);

        $hiddenScene = Scene::factory()->create([
            'campaign_id' => $privateCampaign->id,
            'created_by' => $gm->id,
            'title' => 'Verbotene Halle',
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        SceneSubscription::query()->create([
            'scene_id' => $hiddenScene->id,
            'user_id' => $user->id,
            'is_muted' => false,
            'last_read_post_id' => null,
            'last_read_at' => null,
        ]);

        $response = $this->actingAs($user)->get(route('scene-subscriptions.index'));

        $response->assertOk();
        $response->assertDontSee('Verbotene Halle');
        $response->assertDontSee('Verborgene Krone');
    }
}
