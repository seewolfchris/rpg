<?php

namespace Tests\Feature;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardModerationScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_pending_moderation_count_is_scoped_to_selected_world_for_gm(): void
    {
        $gm = User::factory()->gm()->create();
        $primaryWorld = World::factory()->create([
            'slug' => 'scope-primary',
            'is_active' => true,
            'position' => -100,
        ]);
        $secondaryWorld = World::factory()->create([
            'slug' => 'scope-secondary',
            'is_active' => true,
            'position' => -90,
        ]);

        $primaryCampaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'world_id' => $primaryWorld->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $secondaryCampaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'world_id' => $secondaryWorld->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        $this->createPendingPost($primaryCampaign, $gm);
        $this->createPendingPost($secondaryCampaign, $gm);

        $response = $this->actingAs($gm)
            ->withSession(['world_slug' => $primaryWorld->slug])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Ausstehende Moderation: 1');
    }

    public function test_dashboard_shows_moderation_badge_for_co_gm_only_in_accessible_worlds(): void
    {
        $owner = User::factory()->gm()->create();
        $coGm = User::factory()->create();
        $primaryWorld = World::factory()->create([
            'slug' => 'co-gm-primary',
            'is_active' => true,
            'position' => -80,
        ]);
        $secondaryWorld = World::factory()->create([
            'slug' => 'co-gm-secondary',
            'is_active' => true,
            'position' => -70,
        ]);

        $primaryCampaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'world_id' => $primaryWorld->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $secondaryCampaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'world_id' => $secondaryWorld->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        CampaignMembership::query()->create([
            'campaign_id' => (int) $primaryCampaign->id,
            'user_id' => (int) $coGm->id,
            'role' => CampaignMembershipRole::GM->value,
            'assigned_by' => (int) $owner->id,
            'assigned_at' => now(),
        ]);

        $this->createPendingPost($primaryCampaign, $owner);
        $this->createPendingPost($secondaryCampaign, $owner);

        $primaryResponse = $this->actingAs($coGm)
            ->withSession(['world_slug' => $primaryWorld->slug])
            ->get(route('dashboard'));
        $primaryResponse->assertOk();
        $primaryResponse->assertSee('Ausstehende Moderation: 1');

        $secondaryResponse = $this->actingAs($coGm)
            ->withSession(['world_slug' => $secondaryWorld->slug])
            ->get(route('dashboard'));
        $secondaryResponse->assertOk();
        $secondaryResponse->assertDontSee('Ausstehende Moderation:');
    }

    private function createPendingPost(Campaign $campaign, User $author): void
    {
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $author->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'Moderationsbeitrag',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);
    }
}
