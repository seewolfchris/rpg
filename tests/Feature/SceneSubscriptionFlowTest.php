<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SceneSubscriptionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_follow_and_unfollow_scene(): void
    {
        $user = User::factory()->create();
        [$campaign, $scene] = $this->seedCampaignAndScene();

        $this->actingAs($user)
            ->post(route('campaigns.scenes.subscribe', [$campaign, $scene]))
            ->assertRedirect();

        $this->assertDatabaseHas('scene_subscriptions', [
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'is_muted' => false,
        ]);

        $this->actingAs($user)
            ->delete(route('campaigns.scenes.unsubscribe', [$campaign, $scene]))
            ->assertRedirect();

        $this->assertDatabaseMissing('scene_subscriptions', [
            'scene_id' => $scene->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_user_can_toggle_muted_state(): void
    {
        $user = User::factory()->create();
        [$campaign, $scene] = $this->seedCampaignAndScene();

        SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'is_muted' => false,
        ]);

        $this->actingAs($user)
            ->patch(route('campaigns.scenes.subscription.mute', [$campaign, $scene]))
            ->assertRedirect();

        $this->assertDatabaseHas('scene_subscriptions', [
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'is_muted' => true,
        ]);

        $this->actingAs($user)
            ->patch(route('campaigns.scenes.subscription.mute', [$campaign, $scene]))
            ->assertRedirect();

        $this->assertDatabaseHas('scene_subscriptions', [
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'is_muted' => false,
        ]);
    }

    public function test_author_is_auto_subscribed_when_posting(): void
    {
        $author = User::factory()->create();
        [$campaign, $scene] = $this->seedCampaignAndScene();

        $this->actingAs($author)->post(route('campaigns.scenes.posts.store', [$campaign, $scene]), [
            'post_type' => 'ooc',
            'content_format' => 'markdown',
            'content' => 'Ein Ruf durch die Hallen.',
        ])->assertRedirect();

        $this->assertDatabaseHas('scene_subscriptions', [
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'is_muted' => false,
        ]);
    }

    /**
     * @return array{0: Campaign, 1: Scene}
     */
    private function seedCampaignAndScene(): array
    {
        $gm = User::factory()->gm()->create();

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

        return [$campaign, $scene];
    }
}
