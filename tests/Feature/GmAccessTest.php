<?php

namespace Tests\Feature;

use App\Enums\CampaignMembershipRole;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GmAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_cannot_access_gm_hub(): void
    {
        $player = User::factory()->create([
            'role' => UserRole::PLAYER->value,
        ]);

        $response = $this->actingAs($player)->get(route('gm.index'));

        $response->assertForbidden();
    }

    public function test_campaign_owner_can_access_gm_hub(): void
    {
        $gm = User::factory()->gm()->create();
        Campaign::factory()->create([
            'owner_id' => $gm->id,
        ]);

        $response = $this->actingAs($gm)->get(route('gm.index'));

        $response->assertOk();
    }

    public function test_admin_can_access_gm_hub(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('gm.index'));

        $response->assertOk();
    }

    public function test_gm_hub_links_to_world_scoped_tools_without_empty_world_query(): void
    {
        $admin = User::factory()->admin()->create();
        $world = World::factory()->create([
            'slug' => 'gm-link-clean-world',
            'is_active' => true,
            'position' => -100,
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['world_slug' => $world->slug])
            ->get('/gm?world=');

        $queueUrl = url('/w/'.$world->slug.'/gm/moderation');
        $progressionUrl = url('/w/'.$world->slug.'/gm/progression');

        $response->assertOk();
        $response->assertSee('href="'.$queueUrl.'"', false);
        $response->assertSee('href="'.$progressionUrl.'"', false);
        $response->assertDontSee('/gm/moderation?world=', false);
        $response->assertDontSee('/gm/progression?world=', false);
    }

    public function test_co_gm_with_membership_can_access_gm_hub(): void
    {
        $owner = User::factory()->gm()->create();
        $coGm = User::factory()->create([
            'role' => UserRole::PLAYER->value,
        ]);
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        CampaignMembership::query()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $coGm->id,
            'role' => CampaignMembershipRole::GM->value,
            'assigned_by' => (int) $owner->id,
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($coGm)->get(route('gm.index'));

        $response->assertOk();
    }

    public function test_admin_can_open_empty_world_moderation_queue(): void
    {
        $admin = User::factory()->admin()->create();
        $world = World::factory()->create([
            'slug' => 'admin-empty-moderation-world',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('gm.moderation.index', [
            'world' => $world,
        ]));

        $response->assertOk()
            ->assertViewIs('gm.moderation')
            ->assertViewHas('totalCount', 0)
            ->assertViewHas('pendingCount', 0)
            ->assertViewHas('approvedCount', 0)
            ->assertViewHas('rejectedCount', 0)
            ->assertSee('Keine Posts für den gewählten Filter.');
    }

    public function test_admin_can_open_world_moderation_queue_with_posts(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->gm()->create();
        $author = User::factory()->create();
        $world = World::factory()->create([
            'slug' => 'admin-populated-moderation-world',
            'is_active' => true,
        ]);
        $campaign = Campaign::factory()->create([
            'world_id' => $world->id,
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
        Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'ADMIN-QUEUE-VISIBLE',
            'moderation_status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->get(route('gm.moderation.index', [
            'world' => $world,
        ]));

        $response->assertOk()
            ->assertViewIs('gm.moderation')
            ->assertViewHas('totalCount', 1)
            ->assertViewHas('pendingCount', 1)
            ->assertViewHas('approvedCount', 0)
            ->assertViewHas('rejectedCount', 0)
            ->assertSee('ADMIN-QUEUE-VISIBLE');
    }

    public function test_non_admin_without_moderatable_campaign_cannot_open_world_moderation_queue(): void
    {
        $player = User::factory()->create([
            'role' => UserRole::PLAYER->value,
        ]);
        $world = World::factory()->create([
            'slug' => 'player-empty-moderation-world',
            'is_active' => true,
        ]);

        $response = $this->actingAs($player)->get(route('gm.moderation.index', [
            'world' => $world,
        ]));

        $response->assertForbidden();
    }

    public function test_gm_from_other_world_cannot_open_world_moderation_queue(): void
    {
        $gm = User::factory()->gm()->create();
        $ownedWorld = World::factory()->create([
            'slug' => 'gm-owned-moderation-world',
            'is_active' => true,
        ]);
        $targetWorld = World::factory()->create([
            'slug' => 'gm-foreign-moderation-world',
            'is_active' => true,
        ]);
        Campaign::factory()->create([
            'world_id' => $ownedWorld->id,
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        $response = $this->actingAs($gm)->get(route('gm.moderation.index', [
            'world' => $targetWorld,
        ]));

        $response->assertForbidden();
    }
}
