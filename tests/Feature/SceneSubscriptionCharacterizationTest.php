<?php

namespace Tests\Feature;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SceneSubscriptionCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_campaign_member_can_subscribe_and_unsubscribe_scene(): void
    {
        [$campaign, $scene, $owner, $member] = $this->seedPrivateCampaignSceneContext();
        $latestPost = Post::factory()->approved()->create([
            'scene_id' => $scene->id,
            'user_id' => $owner->id,
        ]);

        $this->actingAs($member)
            ->post(route('campaigns.scenes.subscribe', $this->routeParams($campaign, $scene)))
            ->assertRedirect();

        $this->assertDatabaseHas('scene_subscriptions', [
            'scene_id' => $scene->id,
            'user_id' => $member->id,
            'is_muted' => false,
            'last_read_post_id' => $latestPost->id,
        ]);

        $this->actingAs($member)
            ->delete(route('campaigns.scenes.unsubscribe', $this->routeParams($campaign, $scene)))
            ->assertRedirect();

        $this->assertDatabaseMissing('scene_subscriptions', [
            'scene_id' => $scene->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_authorized_campaign_member_can_mark_subscription_read_and_unread(): void
    {
        [$campaign, $scene, $owner, $member] = $this->seedPrivateCampaignSceneContext();

        Post::factory()->approved()->create([
            'scene_id' => $scene->id,
            'user_id' => $owner->id,
        ]);
        $latestPost = Post::factory()->approved()->create([
            'scene_id' => $scene->id,
            'user_id' => $owner->id,
        ]);

        $this->actingAs($member)
            ->patch(route('campaigns.scenes.subscription.read', $this->routeParams($campaign, $scene)))
            ->assertRedirect();

        $this->assertDatabaseHas('scene_subscriptions', [
            'scene_id' => $scene->id,
            'user_id' => $member->id,
            'last_read_post_id' => $latestPost->id,
        ]);

        $this->actingAs($member)
            ->patch(route('campaigns.scenes.subscription.unread', $this->routeParams($campaign, $scene)))
            ->assertRedirect();

        $this->assertDatabaseHas('scene_subscriptions', [
            'scene_id' => $scene->id,
            'user_id' => $member->id,
            'last_read_post_id' => null,
            'last_read_at' => null,
        ]);
    }

    public function test_authorized_campaign_member_can_toggle_scene_subscription_mute_state(): void
    {
        [$campaign, $scene, , $member] = $this->seedPrivateCampaignSceneContext();

        SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $member->id,
            'is_muted' => false,
            'last_read_post_id' => null,
            'last_read_at' => null,
        ]);

        $this->actingAs($member)
            ->patch(route('campaigns.scenes.subscription.mute', $this->routeParams($campaign, $scene)))
            ->assertRedirect();

        $this->assertDatabaseHas('scene_subscriptions', [
            'scene_id' => $scene->id,
            'user_id' => $member->id,
            'is_muted' => true,
        ]);

        $this->actingAs($member)
            ->patch(route('campaigns.scenes.subscription.mute', $this->routeParams($campaign, $scene)))
            ->assertRedirect();

        $this->assertDatabaseHas('scene_subscriptions', [
            'scene_id' => $scene->id,
            'user_id' => $member->id,
            'is_muted' => false,
        ]);
    }

    public function test_scene_subscription_mutations_redirect_back_to_referer_for_non_hx_requests(): void
    {
        [$campaign, $scene, $owner, $member] = $this->seedPrivateCampaignSceneContext();
        $sceneUrl = route('campaigns.scenes.show', $this->routeParams($campaign, $scene));

        Post::factory()->approved()->create([
            'scene_id' => $scene->id,
            'user_id' => $owner->id,
        ]);

        $this->actingAs($member)
            ->from($sceneUrl)
            ->post(route('campaigns.scenes.subscribe', $this->routeParams($campaign, $scene)))
            ->assertRedirect($sceneUrl);

        $this->actingAs($member)
            ->from($sceneUrl)
            ->patch(route('campaigns.scenes.subscription.mute', $this->routeParams($campaign, $scene)))
            ->assertRedirect($sceneUrl);

        $this->actingAs($member)
            ->from($sceneUrl)
            ->patch(route('campaigns.scenes.subscription.read', $this->routeParams($campaign, $scene)))
            ->assertRedirect($sceneUrl);

        $this->actingAs($member)
            ->from($sceneUrl)
            ->patch(route('campaigns.scenes.subscription.unread', $this->routeParams($campaign, $scene)))
            ->assertRedirect($sceneUrl);

        $this->actingAs($member)
            ->from($sceneUrl)
            ->delete(route('campaigns.scenes.unsubscribe', $this->routeParams($campaign, $scene)))
            ->assertRedirect($sceneUrl);
    }

    public function test_scene_subscription_mutations_return_thread_fragment_for_hx_read_and_unread(): void
    {
        [$campaign, $scene, $owner, $member] = $this->seedPrivateCampaignSceneContext();

        Post::factory()->approved()->create([
            'scene_id' => $scene->id,
            'user_id' => $owner->id,
        ]);

        $markReadResponse = $this->actingAs($member)
            ->withHeaders([
                'HX-Request' => 'true',
                'HX-Target' => 'scene-thread-feed',
            ])
            ->patch(route('campaigns.scenes.subscription.read', $this->routeParams($campaign, $scene)));

        $markReadResponse->assertOk();
        $markReadResponse->assertViewIs('scenes.partials.thread-page');

        $markUnreadResponse = $this->actingAs($member)
            ->withHeaders([
                'HX-Request' => 'true',
                'HX-Target' => 'scene-thread-feed',
            ])
            ->patch(route('campaigns.scenes.subscription.unread', $this->routeParams($campaign, $scene)));

        $markUnreadResponse->assertOk();
        $markUnreadResponse->assertViewIs('scenes.partials.thread-page');
    }

    public function test_outsider_cannot_run_scene_subscription_mutations_in_private_campaign(): void
    {
        [$campaign, $scene] = $this->seedPrivateCampaignSceneContext();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('campaigns.scenes.subscribe', $this->routeParams($campaign, $scene)))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->delete(route('campaigns.scenes.unsubscribe', $this->routeParams($campaign, $scene)))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->patch(route('campaigns.scenes.subscription.mute', $this->routeParams($campaign, $scene)))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->patch(route('campaigns.scenes.subscription.read', $this->routeParams($campaign, $scene)))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->patch(route('campaigns.scenes.subscription.unread', $this->routeParams($campaign, $scene)))
            ->assertForbidden();
    }

    public function test_scene_subscription_mutations_reject_cross_campaign_scene_context_with_404(): void
    {
        [$campaignA, , $owner] = $this->seedPrivateCampaignSceneContext();

        $campaignB = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'world_id' => $campaignA->world_id,
            'status' => 'active',
            'is_public' => false,
        ]);
        $sceneB = Scene::factory()->create([
            'campaign_id' => $campaignB->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.subscribe', $this->routeParams($campaignA, $sceneB)))
            ->assertNotFound();

        $this->actingAs($owner)
            ->delete(route('campaigns.scenes.unsubscribe', $this->routeParams($campaignA, $sceneB)))
            ->assertNotFound();

        $this->actingAs($owner)
            ->patch(route('campaigns.scenes.subscription.mute', $this->routeParams($campaignA, $sceneB)))
            ->assertNotFound();

        $this->actingAs($owner)
            ->patch(route('campaigns.scenes.subscription.read', $this->routeParams($campaignA, $sceneB)))
            ->assertNotFound();

        $this->actingAs($owner)
            ->patch(route('campaigns.scenes.subscription.unread', $this->routeParams($campaignA, $sceneB)))
            ->assertNotFound();
    }

    public function test_scene_subscription_mutations_reject_cross_world_context_with_404(): void
    {
        [$campaign, $scene, $owner] = $this->seedPrivateCampaignSceneContext();
        $foreignWorld = World::factory()->create([
            'slug' => 'fremde-subscription-welt',
            'is_active' => true,
            'position' => -910,
        ]);

        $params = [
            'world' => $foreignWorld,
            'campaign' => $campaign,
            'scene' => $scene,
        ];

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.subscribe', $params))
            ->assertNotFound();

        $this->actingAs($owner)
            ->delete(route('campaigns.scenes.unsubscribe', $params))
            ->assertNotFound();

        $this->actingAs($owner)
            ->patch(route('campaigns.scenes.subscription.mute', $params))
            ->assertNotFound();

        $this->actingAs($owner)
            ->patch(route('campaigns.scenes.subscription.read', $params))
            ->assertNotFound();

        $this->actingAs($owner)
            ->patch(route('campaigns.scenes.subscription.unread', $params))
            ->assertNotFound();
    }

    /**
     * @return array{0: Campaign, 1: Scene, 2: User, 3: User}
     */
    private function seedPrivateCampaignSceneContext(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);

        CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $owner->id,
            'role' => CampaignMembershipRole::GM->value,
            'assigned_by' => $owner->id,
        ]);
        CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $member->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => $owner->id,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        return [$campaign, $scene, $owner, $member];
    }

    /**
     * @return array{world: World, campaign: Campaign, scene: Scene}
     */
    private function routeParams(Campaign $campaign, Scene $scene): array
    {
        return [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ];
    }
}
