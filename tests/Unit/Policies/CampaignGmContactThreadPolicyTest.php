<?php

namespace Tests\Unit\Policies;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignGmContactThread;
use App\Models\CampaignInvitation;
use App\Models\CampaignMembership;
use App\Models\User;
use App\Policies\CampaignGmContactThreadPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignGmContactThreadPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_matrix_enforces_campaign_scoped_roles_and_excludes_global_gm(): void
    {
        $owner = User::factory()->gm()->create();
        $acceptedCoGm = User::factory()->create();
        $acceptedCreator = User::factory()->create();
        $acceptedOtherPlayer = User::factory()->create();
        $pendingCoGm = User::factory()->create();
        $globalGm = User::factory()->gm()->create();
        $admin = User::factory()->admin()->create();
        $outsider = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'is_public' => false,
            'status' => 'active',
        ]);

        $this->grantMembership($campaign, $acceptedCoGm, CampaignMembershipRole::GM, $owner);
        $this->grantMembership($campaign, $acceptedCreator, CampaignMembershipRole::PLAYER, $owner);
        $this->grantMembership($campaign, $acceptedOtherPlayer, CampaignMembershipRole::PLAYER, $owner);

        $thread = CampaignGmContactThread::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $acceptedCreator->id,
            'status' => CampaignGmContactThread::STATUS_WAITING_FOR_GM,
        ]);

        $policy = app(CampaignGmContactThreadPolicy::class);

        $this->assertTrue($policy->create($owner, $campaign));
        $this->assertTrue($policy->create($acceptedCoGm, $campaign));
        $this->assertTrue($policy->create($acceptedCreator, $campaign));
        $this->assertTrue($policy->create($admin, $campaign));
        $this->assertFalse($policy->create($globalGm, $campaign));
        $this->assertFalse($policy->create($pendingCoGm, $campaign));
        $this->assertFalse($policy->create($outsider, $campaign));

        $this->assertTrue($policy->view($owner, $thread));
        $this->assertTrue($policy->view($acceptedCoGm, $thread));
        $this->assertTrue($policy->view($acceptedCreator, $thread));
        $this->assertTrue($policy->view($admin, $thread));
        $this->assertFalse($policy->view($acceptedOtherPlayer, $thread));
        $this->assertFalse($policy->view($globalGm, $thread));
        $this->assertFalse($policy->view($outsider, $thread));

        $this->assertTrue($policy->reply($owner, $thread));
        $this->assertTrue($policy->reply($acceptedCoGm, $thread));
        $this->assertTrue($policy->reply($acceptedCreator, $thread));
        $this->assertFalse($policy->reply($acceptedOtherPlayer, $thread));

        $thread->forceFill(['status' => CampaignGmContactThread::STATUS_CLOSED])->save();
        $this->assertFalse($policy->reply($owner, $thread));
        $this->assertFalse($policy->reply($acceptedCreator, $thread));

        $this->assertTrue($policy->updateStatus($owner, $thread));
        $this->assertTrue($policy->updateStatus($acceptedCoGm, $thread));
        $this->assertTrue($policy->updateStatus($admin, $thread));
        $this->assertFalse($policy->updateStatus($acceptedCreator, $thread));
        $this->assertFalse($policy->updateStatus($globalGm, $thread));
        $this->assertFalse($policy->updateStatus($acceptedOtherPlayer, $thread));
    }

    public function test_accepted_invitation_without_membership_does_not_grant_gm_contact_access(): void
    {
        $owner = User::factory()->gm()->create();
        $legacyInvitee = User::factory()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'is_public' => false,
            'status' => 'active',
        ]);

        CampaignInvitation::query()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $legacyInvitee->id,
            'invited_by' => (int) $owner->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_CO_GM,
            'accepted_at' => now(),
            'responded_at' => now(),
            'created_at' => now(),
        ]);

        $thread = CampaignGmContactThread::factory()->create([
            'campaign_id' => (int) $campaign->id,
            'created_by' => (int) $owner->id,
        ]);

        $policy = app(CampaignGmContactThreadPolicy::class);

        $this->assertFalse($policy->create($legacyInvitee, $campaign));
        $this->assertFalse($policy->view($legacyInvitee, $thread));
        $this->assertFalse($policy->reply($legacyInvitee, $thread));
        $this->assertFalse($policy->updateStatus($legacyInvitee, $thread));
    }

    private function grantMembership(
        Campaign $campaign,
        User $member,
        CampaignMembershipRole $role,
        User $assigner
    ): void {
        CampaignMembership::query()->updateOrCreate(
            [
                'campaign_id' => (int) $campaign->id,
                'user_id' => (int) $member->id,
            ],
            [
                'role' => $role->value,
                'assigned_by' => (int) $assigner->id,
                'assigned_at' => now(),
            ],
        );
    }
}
