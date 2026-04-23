<?php

namespace Tests\Feature;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\CampaignMembership;
use App\Models\CampaignRoleEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignMembershipManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_membership_role_controls_in_campaign_ui(): void
    {
        [$campaign, $owner, $gmMember, $playerMembership] = $this->seedCampaignWithMemberships();

        $this->actingAs($owner)
            ->get(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertOk()
            ->assertSee('Aktive Teilnehmer')
            ->assertSee('Rolle setzen')
            ->assertSee((string) $playerMembership->user->email);

        $this->actingAs($gmMember)
            ->get(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertOk()
            ->assertDontSee('Rolle setzen');
    }

    public function test_owner_can_change_participant_role_from_player_to_gm(): void
    {
        [$campaign, $owner, , $playerMembership] = $this->seedCampaignWithMemberships();

        $this->actingAs($owner)
            ->patch(route('campaigns.memberships.update', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'membership' => $playerMembership,
            ]), [
                'role' => CampaignMembershipRole::GM->value,
            ])
            ->assertRedirect(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]));

        $this->assertDatabaseHas('campaign_memberships', [
            'id' => $playerMembership->id,
            'role' => CampaignMembershipRole::GM->value,
            'assigned_by' => $owner->id,
        ]);

        $this->assertDatabaseHas('campaign_role_events', [
            'campaign_id' => $campaign->id,
            'actor_user_id' => $owner->id,
            'target_user_id' => $playerMembership->user_id,
            'event_type' => CampaignRoleEvent::EVENT_MEMBERSHIP_ROLE_CHANGED,
            'old_role' => CampaignMembershipRole::PLAYER->value,
            'new_role' => CampaignMembershipRole::GM->value,
            'source' => 'campaign_membership_role_update',
        ]);
    }

    public function test_owner_can_demote_gm_to_trusted_player_or_player(): void
    {
        [$campaign, $owner, , , $gmMembership] = $this->seedCampaignWithMemberships();

        $this->actingAs($owner)
            ->patch(route('campaigns.memberships.update', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'membership' => $gmMembership,
            ]), [
                'role' => CampaignMembershipRole::TRUSTED_PLAYER->value,
            ])
            ->assertRedirect(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]));

        $this->assertDatabaseHas('campaign_memberships', [
            'id' => $gmMembership->id,
            'role' => CampaignMembershipRole::TRUSTED_PLAYER->value,
        ]);

        $this->actingAs($owner)
            ->patch(route('campaigns.memberships.update', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'membership' => $gmMembership,
            ]), [
                'role' => CampaignMembershipRole::PLAYER->value,
            ])
            ->assertRedirect(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]));

        $this->assertDatabaseHas('campaign_memberships', [
            'id' => $gmMembership->id,
            'role' => CampaignMembershipRole::PLAYER->value,
        ]);
    }

    public function test_gm_cannot_change_campaign_roles(): void
    {
        [$campaign, , $gmMember, $playerMembership] = $this->seedCampaignWithMemberships();

        $this->actingAs($gmMember)
            ->patch(route('campaigns.memberships.update', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'membership' => $playerMembership,
            ]), [
                'role' => CampaignMembershipRole::GM->value,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('campaign_memberships', [
            'id' => $playerMembership->id,
            'role' => CampaignMembershipRole::PLAYER->value,
        ]);
    }

    public function test_admin_cannot_change_campaign_roles_when_not_owner(): void
    {
        [$campaign, , , $playerMembership] = $this->seedCampaignWithMemberships();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('campaigns.memberships.update', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'membership' => $playerMembership,
            ]), [
                'role' => CampaignMembershipRole::GM->value,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('campaign_memberships', [
            'id' => $playerMembership->id,
            'role' => CampaignMembershipRole::PLAYER->value,
        ]);
    }

    public function test_pending_invitations_remain_separate_from_active_memberships(): void
    {
        [$campaign, $owner, , $playerMembership] = $this->seedCampaignWithMemberships();
        $pendingInvitee = User::factory()->create();

        CampaignInvitation::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $pendingInvitee->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_PENDING,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'created_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertOk()
            ->assertSee('Aktive Teilnehmer')
            ->assertSee('Offene Einladungen')
            ->assertSee((string) $playerMembership->user->email)
            ->assertSee((string) $pendingInvitee->email);

        $this->assertDatabaseMissing('campaign_memberships', [
            'campaign_id' => $campaign->id,
            'user_id' => $pendingInvitee->id,
        ]);
    }

    public function test_campaign_ui_shows_owner_distinctly_from_membership_roles(): void
    {
        [$campaign, $owner] = $this->seedCampaignWithMemberships();

        $this->actingAs($owner)
            ->get(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertOk()
            ->assertSee('Owner:')
            ->assertSee((string) $owner->name)
            ->assertSee('Owner');
    }

    public function test_membership_role_change_does_not_alter_campaign_owner_id(): void
    {
        [$campaign, $owner, , $playerMembership] = $this->seedCampaignWithMemberships();
        $originalOwnerId = (int) $campaign->owner_id;

        $this->actingAs($owner)
            ->patch(route('campaigns.memberships.update', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'membership' => $playerMembership,
            ]), [
                'role' => CampaignMembershipRole::GM->value,
            ])
            ->assertRedirect(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]));

        $campaign->refresh();

        $this->assertSame($originalOwnerId, (int) $campaign->owner_id);
    }

    /**
     * @return array{0: Campaign, 1: User, 2: User, 3: CampaignMembership, 4: CampaignMembership}
     */
    private function seedCampaignWithMemberships(): array
    {
        $owner = User::factory()->gm()->create();
        $gmMember = User::factory()->create();
        $playerMember = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);

        CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $owner->id,
            'role' => CampaignMembershipRole::GM->value,
            'assigned_by' => $owner->id,
            'assigned_at' => now(),
        ]);

        $gmMembership = CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $gmMember->id,
            'role' => CampaignMembershipRole::GM->value,
            'assigned_by' => $owner->id,
            'assigned_at' => now(),
        ]);

        $playerMembership = CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $playerMember->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => $owner->id,
            'assigned_at' => now(),
        ]);

        return [$campaign, $owner, $gmMember, $playerMembership, $gmMembership];
    }
}
