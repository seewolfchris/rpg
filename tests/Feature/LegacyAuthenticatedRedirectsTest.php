<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyAuthenticatedRedirectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaigns_legacy_redirect_uses_session_world_slug(): void
    {
        $user = User::factory()->create();
        $targetWorld = World::factory()->create([
            'slug' => 'session-zielwelt',
            'is_active' => true,
            'position' => 9000,
        ]);

        $this->actingAs($user)
            ->withSession(['world_slug' => $targetWorld->slug])
            ->get('/campaigns')
            ->assertStatus(301)
            ->assertRedirect(route('campaigns.index', ['world' => $targetWorld]));
    }

    public function test_campaigns_legacy_redirect_falls_back_to_default_world_slug(): void
    {
        $user = User::factory()->create();
        $defaultWorld = World::query()
            ->where('slug', World::defaultSlug())
            ->firstOrFail();

        $this->actingAs($user)
            ->get('/campaigns')
            ->assertStatus(301)
            ->assertRedirect(route('campaigns.index', ['world' => $defaultWorld]));
    }

    public function test_campaign_scene_legacy_redirect_preserves_target_route_and_status_code(): void
    {
        $owner = User::factory()->gm()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $this->actingAs($owner)
            ->get('/campaigns/'.$campaign->id.'/scenes/'.$scene->id)
            ->assertStatus(301)
            ->assertRedirect(route('campaigns.scenes.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]));
    }

    public function test_campaign_scene_legacy_redirect_keeps_scope_guard_on_scene_campaign_mismatch(): void
    {
        $owner = User::factory()->gm()->create();
        $campaignA = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $campaignB = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $foreignScene = Scene::factory()->create([
            'campaign_id' => $campaignB->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $this->actingAs($owner)
            ->get('/campaigns/'.$campaignA->id.'/scenes/'.$foreignScene->id)
            ->assertNotFound();
    }

    public function test_posts_edit_legacy_redirect_preserves_target_route_and_status_code(): void
    {
        $owner = User::factory()->gm()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $owner->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'legacy edit redirect target',
            'moderation_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get('/posts/'.$post->id.'/edit')
            ->assertStatus(301)
            ->assertRedirect(route('posts.edit', [
                'world' => $campaign->world,
                'post' => $post,
            ]));
    }

    public function test_gm_moderation_legacy_redirect_uses_session_world_slug(): void
    {
        $gm = User::factory()->gm()->create();
        $targetWorld = World::factory()->create([
            'slug' => 'moderation-zielwelt',
            'is_active' => true,
            'position' => 9050,
        ]);

        $this->actingAs($gm)
            ->withSession(['world_slug' => $targetWorld->slug])
            ->get('/gm/moderation')
            ->assertStatus(301)
            ->assertRedirect(route('gm.moderation.index', ['world' => $targetWorld]));
    }
}
