<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SceneReadTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_scene_view_marks_existing_subscription_as_read(): void
    {
        $user = User::factory()->create();
        [$campaign, $scene, $gm] = $this->seedCampaignAndScene();

        $firstPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
        ]);
        $latestPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
        ]);

        SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'is_muted' => false,
            'last_read_post_id' => $firstPost->id,
            'last_read_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->get(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->assertOk();

        $subscription = SceneSubscription::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertSame($latestPost->id, $subscription->last_read_post_id);
        $this->assertNotNull($subscription->last_read_at);
    }

    public function test_thread_page_render_does_not_mark_scene_subscription_as_read(): void
    {
        $user = User::factory()->create();
        [$campaign, $scene, $gm] = $this->seedCampaignAndScene();

        $firstPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
        ]);
        Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
        ]);

        SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'is_muted' => false,
            'last_read_post_id' => $firstPost->id,
            'last_read_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('campaigns.scenes.thread', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]));

        $response->assertOk();
        $response->assertViewIs('scenes.partials.thread-page');
        $response->assertSee('Ungelesen: 1');

        $this->assertDatabaseHas('scene_subscriptions', [
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'last_read_post_id' => $firstPost->id,
        ]);
    }

    public function test_campaign_overview_shows_unread_flag_until_scene_is_opened(): void
    {
        $user = User::factory()->create();
        [$campaign, $scene, $gm] = $this->seedCampaignAndScene();

        SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'is_muted' => false,
            'last_read_post_id' => null,
            'last_read_at' => null,
        ]);

        Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
        ]);

        $this->actingAs($user)
            ->get(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertOk()
            ->assertSee('Neu seit deinem letzten Besuch');

        $this->actingAs($user)
            ->get(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertOk()
            ->assertDontSee('Neu seit deinem letzten Besuch');
    }

    public function test_user_can_mark_scene_read_and_unread_from_subscription_actions(): void
    {
        $user = User::factory()->create();
        [$campaign, $scene, $gm] = $this->seedCampaignAndScene();

        Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
        ]);
        $latestPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
        ]);

        SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'is_muted' => false,
            'last_read_post_id' => null,
            'last_read_at' => null,
        ]);

        $this->actingAs($user)
            ->patch(route('campaigns.scenes.subscription.read', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->assertRedirect();

        $this->assertDatabaseHas('scene_subscriptions', [
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'last_read_post_id' => $latestPost->id,
        ]);

        $this->actingAs($user)
            ->patch(route('campaigns.scenes.subscription.unread', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->assertRedirect();

        $this->assertDatabaseHas('scene_subscriptions', [
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'last_read_post_id' => null,
            'last_read_at' => null,
        ]);
    }

    public function test_hx_mark_read_returns_thread_fragment_with_unread_count_zero(): void
    {
        $user = User::factory()->create();
        [$campaign, $scene, $gm] = $this->seedCampaignAndScene();

        Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
        ]);
        $latestPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
        ]);

        SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'is_muted' => false,
            'last_read_post_id' => null,
            'last_read_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->withHeaders([
                'HX-Request' => 'true',
                'HX-Target' => 'scene-thread-feed',
            ])
            ->patch(route('campaigns.scenes.subscription.read', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]));

        $response->assertOk();
        $response->assertViewIs('scenes.partials.thread-page');
        $response->assertSee('Ungelesen: 0');
        $response->assertSee('Du bist auf dem aktuellen Stand dieser Szene.');
        $response->assertDontSee('Nächster ungelesener Post');

        $this->assertDatabaseHas('scene_subscriptions', [
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'last_read_post_id' => $latestPost->id,
        ]);
    }

    public function test_hx_mark_unread_returns_thread_fragment_with_recomputed_unread_count(): void
    {
        $user = User::factory()->create();
        [$campaign, $scene, $gm] = $this->seedCampaignAndScene();

        Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
        ]);
        $latestPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
        ]);

        SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'is_muted' => false,
            'last_read_post_id' => $latestPost->id,
            'last_read_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->withHeaders([
                'HX-Request' => 'true',
                'HX-Target' => 'scene-thread-feed',
            ])
            ->patch(route('campaigns.scenes.subscription.unread', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]));

        $response->assertOk();
        $response->assertViewIs('scenes.partials.thread-page');
        $response->assertSee('Ungelesen: 2');
        $response->assertSee('Nächster ungelesener Post');

        $this->assertDatabaseHas('scene_subscriptions', [
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'last_read_post_id' => null,
            'last_read_at' => null,
        ]);
    }

    public function test_show_render_and_thread_render_share_consistent_unread_contract_after_mark_read(): void
    {
        $user = User::factory()->create();
        [$campaign, $scene, $gm] = $this->seedCampaignAndScene();

        Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
        ]);
        $latestPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
        ]);

        SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'is_muted' => false,
            'last_read_post_id' => null,
            'last_read_at' => null,
        ]);

        $showResponse = $this->actingAs($user)
            ->get(route('campaigns.scenes.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]));

        $showResponse->assertOk();
        $showResponse->assertSee('2 neue Beitr');
        $showResponse->assertSee('Thread gelesen');

        $threadResponse = $this->actingAs($user)
            ->get(route('campaigns.scenes.thread', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]));

        $threadResponse->assertOk();
        $threadResponse->assertViewIs('scenes.partials.thread-page');
        $threadResponse->assertSee('Ungelesen: 0');
        $threadResponse->assertSee('Du bist auf dem aktuellen Stand dieser Szene.');

        $this->assertDatabaseHas('scene_subscriptions', [
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'last_read_post_id' => $latestPost->id,
        ]);
    }

    public function test_scene_view_exposes_jump_link_to_last_read_page_anchor(): void
    {
        $user = User::factory()->create();
        [$campaign, $scene, $gm] = $this->seedCampaignAndScene();

        $posts = Post::factory()
            ->count(25)
            ->sequence(fn () => [
                'scene_id' => $scene->id,
                'user_id' => $gm->id,
            ])
            ->create();

        $readCheckpointPost = $posts->get(4);

        SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'is_muted' => false,
            'last_read_post_id' => $readCheckpointPost->id,
            'last_read_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]));

        $response->assertOk();
        $response->assertSee('Zum letzten Lesepunkt');
        $response->assertSee('page=2#post-'.$readCheckpointPost->id, false);
    }

    public function test_author_post_updates_existing_subscription_read_pointer_without_changing_mute_state(): void
    {
        [$campaign, $scene, $owner] = $this->seedCampaignAndScene();

        $existingPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $owner->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'Bereits vorhandener OOC-Post.',
        ]);

        SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $owner->id,
            'is_muted' => true,
            'last_read_post_id' => $existingPost->id,
            'last_read_at' => now()->subHour(),
        ]);

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.posts.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]), [
                'post_type' => 'ooc',
                'character_id' => null,
                'content_format' => 'plain',
                'content' => 'Neuer eigener OOC-Post aktualisiert den Lesestand.',
            ])
            ->assertRedirect();

        $newestPostId = (int) Post::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', $owner->id)
            ->max('id');

        $subscription = SceneSubscription::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', $owner->id)
            ->firstOrFail();

        $this->assertSame($newestPostId, (int) $subscription->last_read_post_id);
        $this->assertTrue((bool) $subscription->is_muted);
        $this->assertNotNull($subscription->last_read_at);
    }

    /**
     * @return array{0: Campaign, 1: Scene, 2: User}
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

        return [$campaign, $scene, $gm];
    }
}
