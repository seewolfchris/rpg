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
            ->assertSee('Registrierte Spieler einladen')
            ->assertSee((string) $playerMembership->user->email);

        $this->actingAs($gmMember)
            ->get(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertOk()
            ->assertSee('Registrierte Spieler einladen')
            ->assertDontSee('Rolle setzen');
    }

    public function test_admin_sees_membership_role_controls_and_platform_hint_in_visible_campaign_ui(): void
    {
        [$campaign, , , $playerMembership] = $this->seedCampaignWithMemberships();
        $admin = User::factory()->admin()->create();

        $campaign->forceFill([
            'is_public' => true,
        ])->save();

        $this->actingAs($admin)
            ->get(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertOk()
            ->assertSee('Rolle setzen')
            ->assertSee('Admins können Kampagnenrollen plattformseitig verwalten; die Kampagnenleitung bleibt unverändert.')
            ->assertSee((string) $playerMembership->user->email);
    }

    public function test_player_sees_active_participant_names_and_roles_but_not_emails(): void
    {
        [$campaign, $owner, $gmMember, $playerMembership, , $trustedMembership] = $this->seedCampaignWithMemberships();

        $this->actingAs($playerMembership->user)
            ->get(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertOk()
            ->assertSee('Aktive Teilnehmer')
            ->assertSee((string) $owner->name)
            ->assertSee((string) $gmMember->name)
            ->assertSee((string) $playerMembership->user->name)
            ->assertSee((string) $trustedMembership->user->name)
            ->assertSeeText('Kampagnenleitung')
            ->assertSeeText('SL')
            ->assertSeeText('Vertrauensspieler')
            ->assertSeeText('Spieler')
            ->assertDontSeeText('Owner')
            ->assertDontSeeText('GM')
            ->assertDontSeeText('Trusted Player')
            ->assertDontSeeText('Player')
            ->assertDontSee('Rolle setzen')
            ->assertDontSee((string) $owner->email, false)
            ->assertDontSee((string) $gmMember->email, false)
            ->assertDontSee((string) $playerMembership->user->email, false)
            ->assertDontSee((string) $trustedMembership->user->email, false);
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

    public function test_normal_user_cannot_change_campaign_roles(): void
    {
        [$campaign, , , $playerMembership] = $this->seedCampaignWithMemberships();

        $this->actingAs($playerMembership->user)
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

    public function test_admin_can_change_campaign_roles_when_not_owner_without_changing_owner(): void
    {
        [$campaign, , , $playerMembership] = $this->seedCampaignWithMemberships();
        $admin = User::factory()->admin()->create();
        $originalOwnerId = (int) $campaign->owner_id;

        $this->actingAs($admin)
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
            'assigned_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('campaign_role_events', [
            'campaign_id' => $campaign->id,
            'actor_user_id' => $admin->id,
            'target_user_id' => $playerMembership->user_id,
            'event_type' => CampaignRoleEvent::EVENT_MEMBERSHIP_ROLE_CHANGED,
            'old_role' => CampaignMembershipRole::PLAYER->value,
            'new_role' => CampaignMembershipRole::GM->value,
            'source' => 'campaign_membership_role_update',
        ]);

        $campaign->refresh();

        $this->assertSame($originalOwnerId, (int) $campaign->owner_id);
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
            ->assertSee('Per E-Mail einladen')
            ->assertSee('Registrierte Spieler einladen')
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
            ->assertSee('Kampagnenleitung:')
            ->assertSee((string) $owner->name)
            ->assertSee('Kampagnenleitung');
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
     * @return array{0: Campaign, 1: User, 2: User, 3: CampaignMembership, 4: CampaignMembership, 5: CampaignMembership}
     */
    private function seedCampaignWithMemberships(): array
    {
        $owner = User::factory()->gm()->create([
            'name' => 'Alraune Leitstern',
            'email' => 'alraune-leitstern@example.test',
        ]);
        $gmMember = User::factory()->create([
            'name' => 'Sina Erzählerin',
            'email' => 'sina-erzaehlerin@example.test',
        ]);
        $playerMember = User::factory()->create([
            'name' => 'Mira Chronistin',
            'email' => 'mira-chronistin@example.test',
        ]);
        $trustedMember = User::factory()->create([
            'name' => 'Taro Vertrauensperson',
            'email' => 'taro-vertrauen@example.test',
        ]);

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

        $trustedMembership = CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $trustedMember->id,
            'role' => CampaignMembershipRole::TRUSTED_PLAYER->value,
            'assigned_by' => $owner->id,
            'assigned_at' => now(),
        ]);

        return [$campaign, $owner, $gmMember, $playerMembership, $gmMembership, $trustedMembership];
    }
}
