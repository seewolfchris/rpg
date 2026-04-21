<?php

namespace Tests\Unit\Policies;

use App\Models\Campaign;
use App\Models\CampaignGmContactThread;
use App\Models\CampaignInvitation;
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

        $this->attachInvitation($campaign, $acceptedCoGm, CampaignInvitation::ROLE_CO_GM, CampaignInvitation::STATUS_ACCEPTED, $owner);
        $this->attachInvitation($campaign, $acceptedCreator, CampaignInvitation::ROLE_PLAYER, CampaignInvitation::STATUS_ACCEPTED, $owner);
        $this->attachInvitation($campaign, $acceptedOtherPlayer, CampaignInvitation::ROLE_PLAYER, CampaignInvitation::STATUS_ACCEPTED, $owner);
        $this->attachInvitation($campaign, $pendingCoGm, CampaignInvitation::ROLE_CO_GM, CampaignInvitation::STATUS_PENDING, $owner);

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

    private function attachInvitation(
        Campaign $campaign,
        User $invitee,
        string $role,
        string $status,
        User $inviter
    ): void {
        CampaignInvitation::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $invitee->id,
            'invited_by' => $inviter->id,
            'status' => $status,
            'role' => $role,
            'accepted_at' => $status === CampaignInvitation::STATUS_ACCEPTED ? now() : null,
            'responded_at' => $status === CampaignInvitation::STATUS_ACCEPTED ? now() : null,
            'created_at' => now(),
        ]);
    }
}

