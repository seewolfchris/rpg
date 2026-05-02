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
        $membershipPlayer = User::factory()->create();
        $acceptedInvitationOnlyCoGm = User::factory()->create();

        $campaign->memberships()->create([
            'user_id' => $membershipGm->id,
            'role' => CampaignMembershipRole::GM->value,
            'assigned_by' => $owner->id,
            'assigned_at' => now(),
        ]);
        $campaign->memberships()->create([
            'user_id' => $membershipPlayer->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => $owner->id,
            'assigned_at' => now(),
        ]);

        $this->createInvitation(
            campaign: $campaign,
            user: $acceptedInvitationOnlyCoGm,
            invitedBy: $owner,
            status: CampaignInvitation::STATUS_ACCEPTED,
            role: CampaignInvitation::ROLE_CO_GM,
        );

        $this->assertTrue($resolver->canModerateCampaign($owner, $campaign));
        $this->assertTrue($resolver->canModerateCampaign($membershipGm, $campaign));
        $this->assertFalse($resolver->canModerateCampaign($membershipPlayer, $campaign));
        $this->assertFalse($resolver->canModerateCampaign($acceptedInvitationOnlyCoGm, $campaign));
        $this->assertFalse($resolver->canModerateCampaign($admin, $campaign));
        $this->assertFalse($resolver->canModerateCampaign($globalGm, $campaign));
        $this->assertFalse($resolver->canModerateCampaign(null, $campaign));
    }

    public function test_is_participant_user_id_accepts_owner_and_memberships_and_rejects_others(): void
    {
        $resolver = app(CampaignParticipantResolver::class);
        $owner = User::factory()->gm()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
        ]);

        $memberParticipant = User::factory()->create();
        $acceptedInvitationOnlyParticipant = User::factory()->create();
        $outsider = User::factory()->create();

        $campaign->memberships()->create([
            'user_id' => $memberParticipant->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => $owner->id,
            'assigned_at' => now(),
        ]);

        $this->createInvitation(
            campaign: $campaign,
            user: $acceptedInvitationOnlyParticipant,
            invitedBy: $owner,
            status: CampaignInvitation::STATUS_ACCEPTED,
            role: CampaignInvitation::ROLE_PLAYER,
        );

        $participantUserIds = $resolver->participantUserIds($campaign);

        $this->assertTrue($resolver->isParticipantUserId($campaign, (int) $owner->id, $participantUserIds));
        $this->assertTrue($resolver->isParticipantUserId($campaign, (int) $memberParticipant->id, $participantUserIds));
        $this->assertFalse($resolver->isParticipantUserId($campaign, (int) $acceptedInvitationOnlyParticipant->id, $participantUserIds));
        $this->assertFalse($resolver->isParticipantUserId($campaign, (int) $outsider->id, $participantUserIds));
        $this->assertFalse($resolver->isParticipantUserId($campaign, 0, $participantUserIds));

        $this->assertTrue($resolver->isParticipantUserId($campaign, (int) $memberParticipant->id));
        $this->assertFalse($resolver->isParticipantUserId($campaign, (int) $outsider->id));
        $this->assertFalse($resolver->isParticipantUserId($campaign, (int) $acceptedInvitationOnlyParticipant->id));
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
