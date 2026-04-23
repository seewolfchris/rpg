<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Campaign;

use App\Actions\Campaign\RespondToCampaignInvitationAction;
use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\CampaignRoleEvent;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RespondToCampaignInvitationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_accept_creates_membership_and_audit_event(): void
    {
        $owner = User::factory()->gm()->create();
        $invitee = User::factory()->create();
        $world = World::factory()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'world_id' => $world->id,
            'is_public' => false,
            'status' => 'active',
        ]);
        $invitation = CampaignInvitation::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $invitee->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_PENDING,
            'role' => CampaignInvitation::ROLE_CO_GM,
            'accepted_at' => null,
            'responded_at' => null,
            'created_at' => now(),
        ]);

        $result = app(RespondToCampaignInvitationAction::class)->execute(
            invitationId: (int) $invitation->id,
            userId: (int) $invitee->id,
            worldId: (int) $world->id,
            decision: CampaignInvitation::STATUS_ACCEPTED,
        );

        $this->assertFalse($result->alreadyClosed);
        $this->assertTrue($result->isAccepted);
        $this->assertDatabaseHas('campaign_invitations', [
            'id' => $invitation->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
        ]);
        $this->assertDatabaseHas('campaign_memberships', [
            'campaign_id' => $campaign->id,
            'user_id' => $invitee->id,
            'role' => CampaignMembershipRole::GM->value,
            'assigned_by' => $invitee->id,
        ]);
        $this->assertDatabaseHas('campaign_role_events', [
            'campaign_id' => $campaign->id,
            'actor_user_id' => $invitee->id,
            'target_user_id' => $invitee->id,
            'event_type' => CampaignRoleEvent::EVENT_MEMBERSHIP_GRANTED,
            'old_role' => null,
            'new_role' => CampaignMembershipRole::GM->value,
            'source' => 'invitation_accept',
        ]);
    }

    public function test_decline_does_not_create_membership(): void
    {
        $owner = User::factory()->gm()->create();
        $invitee = User::factory()->create();
        $world = World::factory()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'world_id' => $world->id,
            'is_public' => false,
            'status' => 'active',
        ]);
        $invitation = CampaignInvitation::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $invitee->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_PENDING,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'accepted_at' => null,
            'responded_at' => null,
            'created_at' => now(),
        ]);

        $result = app(RespondToCampaignInvitationAction::class)->execute(
            invitationId: (int) $invitation->id,
            userId: (int) $invitee->id,
            worldId: (int) $world->id,
            decision: CampaignInvitation::STATUS_DECLINED,
        );

        $this->assertFalse($result->alreadyClosed);
        $this->assertFalse($result->isAccepted);
        $this->assertDatabaseHas('campaign_invitations', [
            'id' => $invitation->id,
            'status' => CampaignInvitation::STATUS_DECLINED,
        ]);
        $this->assertDatabaseMissing('campaign_memberships', [
            'campaign_id' => $campaign->id,
            'user_id' => $invitee->id,
        ]);
        $this->assertDatabaseCount('campaign_role_events', 0);
    }
}
