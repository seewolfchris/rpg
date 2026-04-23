<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Campaign;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignParticipantResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_moderate_campaign_allows_owner_and_campaign_gm_but_not_admin_or_global_gm(): void
    {
        $resolver = app(CampaignParticipantResolver::class);
        $owner = User::factory()->gm()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
        ]);

        $admin = User::factory()->admin()->create();
        $globalGm = User::factory()->gm()->create();
        $membershipGm = User::factory()->create();
        $acceptedCoGm = User::factory()->create();
        $acceptedPlayer = User::factory()->create();
        $pendingCoGm = User::factory()->create();

        $campaign->memberships()->create([
            'user_id' => $membershipGm->id,
            'role' => CampaignMembershipRole::GM->value,
            'assigned_by' => $owner->id,
            'assigned_at' => now(),
        ]);

        $this->createInvitation(
            campaign: $campaign,
            user: $acceptedCoGm,
            invitedBy: $owner,
            status: CampaignInvitation::STATUS_ACCEPTED,
            role: CampaignInvitation::ROLE_CO_GM,
        );
        $this->createInvitation(
            campaign: $campaign,
            user: $acceptedPlayer,
            invitedBy: $owner,
            status: CampaignInvitation::STATUS_ACCEPTED,
            role: CampaignInvitation::ROLE_PLAYER,
        );
        $this->createInvitation(
            campaign: $campaign,
            user: $pendingCoGm,
            invitedBy: $owner,
            status: CampaignInvitation::STATUS_PENDING,
            role: CampaignInvitation::ROLE_CO_GM,
        );

        $this->assertTrue($resolver->canModerateCampaign($owner, $campaign));
        $this->assertTrue($resolver->canModerateCampaign($membershipGm, $campaign));
        $this->assertTrue($resolver->canModerateCampaign($acceptedCoGm, $campaign));
        $this->assertFalse($resolver->canModerateCampaign($admin, $campaign));
        $this->assertFalse($resolver->canModerateCampaign($globalGm, $campaign));
        $this->assertFalse($resolver->canModerateCampaign($acceptedPlayer, $campaign));
        $this->assertFalse($resolver->canModerateCampaign($pendingCoGm, $campaign));
        $this->assertFalse($resolver->canModerateCampaign(null, $campaign));
    }

    public function test_is_participant_user_id_accepts_owner_and_accepted_invites_and_rejects_others(): void
    {
        $resolver = app(CampaignParticipantResolver::class);
        $owner = User::factory()->gm()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
        ]);

        $acceptedParticipant = User::factory()->create();
        $pendingParticipant = User::factory()->create();
        $declinedParticipant = User::factory()->create();
        $outsider = User::factory()->create();

        $this->createInvitation(
            campaign: $campaign,
            user: $acceptedParticipant,
            invitedBy: $owner,
            status: CampaignInvitation::STATUS_ACCEPTED,
            role: CampaignInvitation::ROLE_PLAYER,
        );
        $this->createInvitation(
            campaign: $campaign,
            user: $pendingParticipant,
            invitedBy: $owner,
            status: CampaignInvitation::STATUS_PENDING,
            role: CampaignInvitation::ROLE_PLAYER,
        );
        $this->createInvitation(
            campaign: $campaign,
            user: $declinedParticipant,
            invitedBy: $owner,
            status: CampaignInvitation::STATUS_DECLINED,
            role: CampaignInvitation::ROLE_PLAYER,
        );

        $participantUserIds = $resolver->participantUserIds($campaign);

        $this->assertTrue($resolver->isParticipantUserId($campaign, (int) $owner->id, $participantUserIds));
        $this->assertTrue($resolver->isParticipantUserId($campaign, (int) $acceptedParticipant->id, $participantUserIds));
        $this->assertFalse($resolver->isParticipantUserId($campaign, (int) $pendingParticipant->id, $participantUserIds));
        $this->assertFalse($resolver->isParticipantUserId($campaign, (int) $declinedParticipant->id, $participantUserIds));
        $this->assertFalse($resolver->isParticipantUserId($campaign, (int) $outsider->id, $participantUserIds));
        $this->assertFalse($resolver->isParticipantUserId($campaign, 0, $participantUserIds));

        $this->assertTrue($resolver->isParticipantUserId($campaign, (int) $acceptedParticipant->id));
        $this->assertFalse($resolver->isParticipantUserId($campaign, (int) $outsider->id));
    }

    private function createInvitation(
        Campaign $campaign,
        User $user,
        User $invitedBy,
        string $status,
        string $role,
    ): CampaignInvitation {
        return $campaign->invitations()->create([
            'user_id' => (int) $user->id,
            'invited_by' => (int) $invitedBy->id,
            'status' => $status,
            'role' => $role,
            'accepted_at' => $status === CampaignInvitation::STATUS_ACCEPTED ? now() : null,
            'responded_at' => $status === CampaignInvitation::STATUS_PENDING ? null : now(),
            'created_at' => now(),
        ]);
    }
}
