<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\CampaignMembership;
use App\Models\CampaignRoleEvent;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignInvitationMembershipLifecycleCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitee_can_accept_pending_invitation_with_co_gm_role_mapping(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $campaign = $this->createPrivateCampaign($owner);

        $invitation = $this->createInvitation(
            campaign: $campaign,
            invitee: $invitee,
            inviter: $owner,
            status: CampaignInvitation::STATUS_PENDING,
            role: CampaignInvitation::ROLE_CO_GM,
        );

        $this->actingAs($invitee)
            ->patch(route('campaign-invitations.accept', ['world' => $campaign->world, 'invitation' => $invitation]))
            ->assertRedirect(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]));

        $invitation->refresh();

        $this->assertSame(CampaignInvitation::STATUS_ACCEPTED, $invitation->status);
        $this->assertNotNull($invitation->accepted_at);
        $this->assertNotNull($invitation->responded_at);

        $this->assertDatabaseHas('campaign_memberships', [
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $invitee->id,
            'role' => CampaignMembershipRole::GM->value,
            'assigned_by' => (int) $invitee->id,
        ]);

        $this->assertDatabaseHas('campaign_role_events', [
            'campaign_id' => (int) $campaign->id,
            'actor_user_id' => (int) $invitee->id,
            'target_user_id' => (int) $invitee->id,
            'event_type' => CampaignRoleEvent::EVENT_MEMBERSHIP_GRANTED,
            'old_role' => null,
            'new_role' => CampaignMembershipRole::GM->value,
            'source' => 'invitation_accept',
        ]);
    }

    public function test_invitee_can_decline_pending_invitation_without_membership_creation(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $campaign = $this->createPrivateCampaign($owner);

        $invitation = $this->createInvitation(
            campaign: $campaign,
            invitee: $invitee,
            inviter: $owner,
            status: CampaignInvitation::STATUS_PENDING,
            role: CampaignInvitation::ROLE_PLAYER,
        );

        $this->actingAs($invitee)
            ->patch(route('campaign-invitations.decline', ['world' => $campaign->world, 'invitation' => $invitation]))
            ->assertRedirect(route('campaign-invitations.index'))
            ->assertSessionHas('status', 'Einladung abgelehnt.');

        $invitation->refresh();

        $this->assertSame(CampaignInvitation::STATUS_DECLINED, $invitation->status);
        $this->assertNull($invitation->accepted_at);
        $this->assertNotNull($invitation->responded_at);

        $this->assertDatabaseMissing('campaign_memberships', [
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $invitee->id,
        ]);
        $this->assertDatabaseCount('campaign_role_events', 0);
    }

    public function test_owner_can_upsert_accepted_invitation_and_role_syncs_to_membership(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $campaign = $this->createPrivateCampaign($owner);

        $acceptedAt = now()->subDay()->startOfSecond();
        $respondedAt = now()->subHours(12)->startOfSecond();

        $invitation = $this->createInvitation(
            campaign: $campaign,
            invitee: $invitee,
            inviter: $owner,
            status: CampaignInvitation::STATUS_ACCEPTED,
            role: CampaignInvitation::ROLE_PLAYER,
            acceptedAt: $acceptedAt,
            respondedAt: $respondedAt,
        );

        CampaignMembership::factory()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $invitee->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => (int) $owner->id,
            'assigned_at' => now()->subDays(2),
        ]);

        $this->actingAs($owner)
            ->post(route('campaigns.invitations.store', ['world' => $campaign->world, 'campaign' => $campaign]), [
                'email' => (string) $invitee->email,
                'role' => CampaignInvitation::ROLE_TRUSTED_PLAYER,
            ])
            ->assertRedirect(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertSessionHas('status', 'Falls ein passender Account existiert, wurde die Einladung verarbeitet.');

        $invitation->refresh();

        $this->assertSame(CampaignInvitation::STATUS_ACCEPTED, (string) $invitation->status);
        $this->assertSame(CampaignInvitation::ROLE_TRUSTED_PLAYER, (string) $invitation->role);
        $this->assertSame($acceptedAt->toDateTimeString(), optional($invitation->accepted_at)?->toDateTimeString());
        $this->assertSame($respondedAt->toDateTimeString(), optional($invitation->responded_at)?->toDateTimeString());

        $this->assertDatabaseHas('campaign_memberships', [
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $invitee->id,
            'role' => CampaignMembershipRole::TRUSTED_PLAYER->value,
            'assigned_by' => (int) $owner->id,
        ]);

        $this->assertDatabaseHas('campaign_role_events', [
            'campaign_id' => (int) $campaign->id,
            'actor_user_id' => (int) $owner->id,
            'target_user_id' => (int) $invitee->id,
            'event_type' => CampaignRoleEvent::EVENT_MEMBERSHIP_ROLE_CHANGED,
            'old_role' => CampaignMembershipRole::PLAYER->value,
            'new_role' => CampaignMembershipRole::TRUSTED_PLAYER->value,
            'source' => 'invitation_upsert_accepted',
        ]);
    }

    public function test_trusted_player_cannot_update_membership_role(): void
    {
        $owner = User::factory()->create();
        $trustedPlayer = User::factory()->create();
        $targetMember = User::factory()->create();
        $campaign = $this->createPrivateCampaign($owner);

        CampaignMembership::factory()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $trustedPlayer->id,
            'role' => CampaignMembershipRole::TRUSTED_PLAYER->value,
            'assigned_by' => (int) $owner->id,
            'assigned_at' => now(),
        ]);

        $targetMembership = CampaignMembership::factory()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $targetMember->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => (int) $owner->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($trustedPlayer)
            ->patch(route('campaigns.memberships.update', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'membership' => $targetMembership,
            ]), [
                'role' => CampaignMembershipRole::GM->value,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('campaign_memberships', [
            'id' => (int) $targetMembership->id,
            'role' => CampaignMembershipRole::PLAYER->value,
        ]);
    }

    public function test_membership_update_rejects_cross_campaign_membership_context_with_404(): void
    {
        $owner = User::factory()->create();
        $foreignOwner = User::factory()->create();
        $member = User::factory()->create();

        $campaign = $this->createPrivateCampaign($owner);
        $foreignCampaign = $this->createPrivateCampaign($foreignOwner);

        $foreignMembership = CampaignMembership::factory()->create([
            'campaign_id' => (int) $foreignCampaign->id,
            'user_id' => (int) $member->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => (int) $foreignOwner->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($owner)
            ->patch(route('campaigns.memberships.update', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'membership' => $foreignMembership,
            ]), [
                'role' => CampaignMembershipRole::GM->value,
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('campaign_memberships', [
            'id' => (int) $foreignMembership->id,
            'campaign_id' => (int) $foreignCampaign->id,
            'role' => CampaignMembershipRole::PLAYER->value,
        ]);
    }

    public function test_co_gm_cannot_delete_accepted_invitation_or_revoke_membership(): void
    {
        $owner = User::factory()->create();
        $coGm = User::factory()->create();
        $invitee = User::factory()->create();
        $campaign = $this->createPrivateCampaign($owner);

        CampaignMembership::factory()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $coGm->id,
            'role' => CampaignMembershipRole::GM->value,
            'assigned_by' => (int) $owner->id,
            'assigned_at' => now(),
        ]);

        CampaignMembership::factory()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $invitee->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => (int) $owner->id,
            'assigned_at' => now(),
        ]);

        $invitation = $this->createInvitation(
            campaign: $campaign,
            invitee: $invitee,
            inviter: $owner,
            status: CampaignInvitation::STATUS_ACCEPTED,
            role: CampaignInvitation::ROLE_PLAYER,
            acceptedAt: now()->subHour(),
            respondedAt: now()->subHour(),
        );

        $this->actingAs($coGm)
            ->delete(route('campaigns.invitations.destroy', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'invitation' => $invitation,
            ]))
            ->assertForbidden();

        $this->assertDatabaseHas('campaign_invitations', [
            'id' => (int) $invitation->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
        ]);
        $this->assertDatabaseHas('campaign_memberships', [
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $invitee->id,
            'role' => CampaignMembershipRole::PLAYER->value,
        ]);
        $this->assertDatabaseCount('campaign_role_events', 0);
    }

    public function test_co_gm_cannot_change_role_through_accepted_invitation_upsert(): void
    {
        $owner = User::factory()->create();
        $coGm = User::factory()->create();
        $invitee = User::factory()->create();
        $campaign = $this->createPrivateCampaign($owner);

        CampaignMembership::factory()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $coGm->id,
            'role' => CampaignMembershipRole::GM->value,
            'assigned_by' => (int) $owner->id,
            'assigned_at' => now(),
        ]);
        CampaignMembership::factory()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $invitee->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => (int) $owner->id,
            'assigned_at' => now(),
        ]);
        $invitation = $this->createInvitation(
            campaign: $campaign,
            invitee: $invitee,
            inviter: $owner,
            status: CampaignInvitation::STATUS_ACCEPTED,
            role: CampaignInvitation::ROLE_PLAYER,
            acceptedAt: now()->subHour(),
            respondedAt: now()->subHour(),
        );

        $this->actingAs($coGm)
            ->post(route('campaigns.invitations.store', ['world' => $campaign->world, 'campaign' => $campaign]), [
                'email' => (string) $invitee->email,
                'role' => CampaignInvitation::ROLE_CO_GM,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('campaign_invitations', [
            'id' => (int) $invitation->id,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
        ]);
        $this->assertDatabaseHas('campaign_memberships', [
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $invitee->id,
            'role' => CampaignMembershipRole::PLAYER->value,
        ]);
        $this->assertDatabaseCount('campaign_role_events', 0);
    }

    public function test_owner_deleting_declined_invitation_keeps_existing_membership_row(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $campaign = $this->createPrivateCampaign($owner);

        CampaignMembership::factory()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $invitee->id,
            'role' => CampaignMembershipRole::TRUSTED_PLAYER->value,
            'assigned_by' => (int) $owner->id,
            'assigned_at' => now(),
        ]);

        $invitation = $this->createInvitation(
            campaign: $campaign,
            invitee: $invitee,
            inviter: $owner,
            status: CampaignInvitation::STATUS_DECLINED,
            role: CampaignInvitation::ROLE_TRUSTED_PLAYER,
            acceptedAt: null,
            respondedAt: now()->subHour(),
        );

        $this->actingAs($owner)
            ->delete(route('campaigns.invitations.destroy', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'invitation' => $invitation,
            ]))
            ->assertRedirect(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]));

        $this->assertDatabaseMissing('campaign_invitations', [
            'id' => (int) $invitation->id,
        ]);

        $this->assertDatabaseHas('campaign_memberships', [
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $invitee->id,
            'role' => CampaignMembershipRole::TRUSTED_PLAYER->value,
        ]);
    }

    public function test_accept_invitation_rejects_foreign_world_context_with_404(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $campaign = $this->createPrivateCampaign($owner);
        $foreignWorld = World::factory()->create([
            'slug' => 'campaign-invitation-lifecycle-foreign-world',
            'is_active' => true,
            'position' => -510,
        ]);

        $invitation = $this->createInvitation(
            campaign: $campaign,
            invitee: $invitee,
            inviter: $owner,
            status: CampaignInvitation::STATUS_PENDING,
            role: CampaignInvitation::ROLE_PLAYER,
        );

        $this->actingAs($invitee)
            ->patch(route('campaign-invitations.accept', ['world' => $foreignWorld, 'invitation' => $invitation]))
            ->assertNotFound();

        $this->assertDatabaseHas('campaign_invitations', [
            'id' => (int) $invitation->id,
            'status' => CampaignInvitation::STATUS_PENDING,
        ]);
        $this->assertDatabaseMissing('campaign_memberships', [
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $invitee->id,
        ]);
    }

    public function test_outsider_cannot_respond_to_other_users_invitation(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $outsider = User::factory()->create();
        $campaign = $this->createPrivateCampaign($owner);

        $invitation = $this->createInvitation(
            campaign: $campaign,
            invitee: $invitee,
            inviter: $owner,
            status: CampaignInvitation::STATUS_PENDING,
            role: CampaignInvitation::ROLE_PLAYER,
        );

        $this->actingAs($outsider)
            ->patch(route('campaign-invitations.accept', ['world' => $campaign->world, 'invitation' => $invitation]))
            ->assertForbidden();

        $this->assertDatabaseHas('campaign_invitations', [
            'id' => (int) $invitation->id,
            'status' => CampaignInvitation::STATUS_PENDING,
        ]);
    }

    private function createPrivateCampaign(User $owner): Campaign
    {
        return Campaign::factory()->create([
            'owner_id' => (int) $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);
    }

    private function createInvitation(
        Campaign $campaign,
        User $invitee,
        User $inviter,
        string $status,
        string $role,
        mixed $acceptedAt = null,
        mixed $respondedAt = null,
    ): CampaignInvitation {
        return CampaignInvitation::query()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $invitee->id,
            'invited_by' => (int) $inviter->id,
            'status' => $status,
            'role' => $role,
            'accepted_at' => $acceptedAt,
            'responded_at' => $respondedAt,
            'created_at' => now()->subDays(2),
        ]);
    }
}
