<?php

namespace Tests\Unit\Models;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_casts_role_and_resolves_relations(): void
    {
        $owner = User::factory()->gm()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
        ]);
        $member = User::factory()->create();
        $assigner = User::factory()->admin()->create();

        $membership = CampaignMembership::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $member->id,
            'role' => CampaignMembershipRole::GM->value,
            'assigned_by' => $assigner->id,
            'assigned_at' => now(),
        ]);

        $fresh = CampaignMembership::query()
            ->with(['campaign', 'user', 'assigner'])
            ->findOrFail($membership->id);

        $this->assertSame(CampaignMembershipRole::GM, $fresh->role);
        $this->assertTrue($fresh->hasRole(CampaignMembershipRole::GM));
        $this->assertSame((int) $campaign->id, (int) $fresh->campaign->id);
        $this->assertSame((int) $member->id, (int) $fresh->user->id);
        $this->assertSame((int) $assigner->id, (int) $fresh->assigner?->id);
    }

    public function test_campaign_and_user_relations_expose_memberships(): void
    {
        $campaign = Campaign::factory()->create();
        $member = User::factory()->create();
        $assigner = User::factory()->create();

        $membership = CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $member->id,
            'assigned_by' => $assigner->id,
            'role' => CampaignMembershipRole::TRUSTED_PLAYER->value,
        ]);

        $campaign->load('memberships');
        $member->load('campaignMemberships');

        $this->assertCount(1, $campaign->memberships);
        $this->assertCount(1, $member->campaignMemberships);
        $this->assertSame((int) $membership->id, (int) $campaign->memberships->first()?->id);
        $this->assertSame((int) $membership->id, (int) $member->campaignMemberships->first()?->id);
    }

    public function test_it_enforces_unique_campaign_and_user_constraint(): void
    {
        $campaign = Campaign::factory()->create();
        $member = User::factory()->create();

        CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $member->id,
            'role' => CampaignMembershipRole::PLAYER->value,
        ]);

        $this->expectException(QueryException::class);

        CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $member->id,
            'role' => CampaignMembershipRole::GM->value,
        ]);
    }
}
