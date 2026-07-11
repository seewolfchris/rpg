<?php

namespace Tests\Feature;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\CampaignMembership;
use App\Models\User;
use App\Models\World;
use App\Notifications\CampaignInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class CampaignInvitationRegisteredUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_co_gm_see_registered_invite_candidates_but_player_does_not(): void
    {
        [$campaign, $owner, $coGm, $actorPlayer] = $this->seedCampaignContext();

        $candidate = User::factory()->create([
            'name' => 'Kandidat Eins',
            'email' => 'candidate-one@example.test',
        ]);
        $alreadyMember = User::factory()->create([
            'name' => 'Bereits Mitglied',
            'email' => 'member@example.test',
        ]);
        $pendingInvitee = User::factory()->create([
            'name' => 'Bereits Offen',
            'email' => 'pending@example.test',
        ]);

        $this->grantMembership($campaign, $alreadyMember, CampaignMembershipRole::PLAYER, $owner);
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
            ->assertSeeText('Registrierte Spieler einladen')
            ->assertSeeText('Ausgewählte einladen')
            ->assertSeeText($candidate->name)
            ->assertSee('value="'.$candidate->id.'"', false)
            ->assertDontSee('value="'.$alreadyMember->id.'"', false)
            ->assertDontSee('value="'.$pendingInvitee->id.'"', false)
            ->assertDontSee('value="'.$owner->id.'"', false);

        $this->actingAs($coGm)
            ->get(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertOk()
            ->assertSeeText('Registrierte Spieler einladen')
            ->assertSee('value="'.$candidate->id.'"', false)
            ->assertDontSee('<option value="trusted_player"', false)
            ->assertDontSee('<option value="co_gm"', false);

        $this->actingAs($actorPlayer)
            ->get(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertOk()
            ->assertDontSeeText('Registrierte Spieler einladen');
    }

    public function test_co_gm_can_invite_multiple_registered_users_as_players(): void
    {
        [$campaign, $owner, $coGm] = $this->seedCampaignContext();
        $candidateA = User::factory()->create(['email' => 'bulk-a@example.test']);
        $candidateB = User::factory()->create(['email' => 'bulk-b@example.test']);

        $this->actingAs($coGm)
            ->post(route('campaigns.invitations.store', ['world' => $campaign->world, 'campaign' => $campaign]), [
                'user_ids' => [(int) $candidateA->id, (int) $candidateB->id],
                'role' => CampaignInvitation::ROLE_PLAYER,
            ])
            ->assertRedirect(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]));

        $this->assertDatabaseHas('campaign_invitations', [
            'campaign_id' => $campaign->id,
            'user_id' => $candidateA->id,
            'invited_by' => $coGm->id,
            'status' => CampaignInvitation::STATUS_PENDING,
            'role' => CampaignInvitation::ROLE_PLAYER,
        ]);
        $this->assertDatabaseHas('campaign_invitations', [
            'campaign_id' => $campaign->id,
            'user_id' => $candidateB->id,
            'invited_by' => $coGm->id,
            'status' => CampaignInvitation::STATUS_PENDING,
            'role' => CampaignInvitation::ROLE_PLAYER,
        ]);

        $this->assertSame(
            2,
            CampaignInvitation::query()
                ->where('campaign_id', (int) $campaign->id)
                ->whereIn('user_id', [(int) $candidateA->id, (int) $candidateB->id])
                ->where('status', CampaignInvitation::STATUS_PENDING)
                ->count()
        );
    }

    public function test_co_gm_cannot_assign_privileged_invitation_roles(): void
    {
        [$campaign, , $coGm] = $this->seedCampaignContext();

        foreach ([CampaignInvitation::ROLE_TRUSTED_PLAYER, CampaignInvitation::ROLE_CO_GM] as $role) {
            $candidate = User::factory()->create();

            $this->actingAs($coGm)
                ->post(route('campaigns.invitations.store', ['world' => $campaign->world, 'campaign' => $campaign]), [
                    'user_ids' => [(int) $candidate->id],
                    'role' => $role,
                ])
                ->assertForbidden();

            $this->assertDatabaseMissing('campaign_invitations', [
                'campaign_id' => (int) $campaign->id,
                'user_id' => (int) $candidate->id,
            ]);
        }
    }

    public function test_bulk_invitation_keeps_db_writes_when_notification_fails(): void
    {
        [$campaign, $owner, $coGm] = $this->seedCampaignContext();
        $candidateA = User::factory()->create(['email' => 'bulk-failure-a@example.test']);
        $candidateB = User::factory()->create(['email' => 'bulk-failure-b@example.test']);

        Exceptions::fake();
        Notification::shouldReceive('send')
            ->twice()
            ->andThrowExceptions([
                new RuntimeException('Forced bulk invitation notification failure A.'),
                new RuntimeException('Forced bulk invitation notification failure B.'),
            ]);

        $this->actingAs($coGm)
            ->post(route('campaigns.invitations.store', ['world' => $campaign->world, 'campaign' => $campaign]), [
                'user_ids' => [(int) $candidateA->id, (int) $candidateB->id],
                'role' => CampaignInvitation::ROLE_PLAYER,
            ])
            ->assertRedirect(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]));

        foreach ([$candidateA, $candidateB] as $candidate) {
            $this->assertDatabaseHas('campaign_invitations', [
                'campaign_id' => $campaign->id,
                'user_id' => $candidate->id,
                'invited_by' => $coGm->id,
                'status' => CampaignInvitation::STATUS_PENDING,
                'role' => CampaignInvitation::ROLE_PLAYER,
            ]);
        }

        Exceptions::assertReported(
            fn (RuntimeException $exception): bool => str_starts_with(
                $exception->getMessage(),
                'Forced bulk invitation notification failure'
            )
        );
    }

    public function test_email_fallback_invitation_still_works(): void
    {
        [$campaign, $owner] = $this->seedCampaignContext();
        $invitee = User::factory()->create(['email' => 'fallback@example.test']);

        $this->actingAs($owner)
            ->post(route('campaigns.invitations.store', ['world' => $campaign->world, 'campaign' => $campaign]), [
                'email' => $invitee->email,
                'role' => CampaignInvitation::ROLE_PLAYER,
            ])
            ->assertRedirect(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]));

        $this->assertDatabaseHas('campaign_invitations', [
            'campaign_id' => $campaign->id,
            'user_id' => $invitee->id,
            'status' => CampaignInvitation::STATUS_PENDING,
            'role' => CampaignInvitation::ROLE_PLAYER,
        ]);
    }

    public function test_email_invitation_keeps_db_write_when_notification_fails(): void
    {
        [$campaign, $owner] = $this->seedCampaignContext();
        $invitee = User::factory()->create(['email' => 'fallback-failure@example.test']);

        Exceptions::fake();
        Notification::shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('Forced invitation notification failure.'));

        $this->actingAs($owner)
            ->post(route('campaigns.invitations.store', ['world' => $campaign->world, 'campaign' => $campaign]), [
                'email' => $invitee->email,
                'role' => CampaignInvitation::ROLE_PLAYER,
            ])
            ->assertRedirect(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]));

        $this->assertDatabaseHas('campaign_invitations', [
            'campaign_id' => $campaign->id,
            'user_id' => $invitee->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_PENDING,
            'role' => CampaignInvitation::ROLE_PLAYER,
        ]);

        Exceptions::assertReported(
            fn (RuntimeException $exception): bool => $exception->getMessage() === 'Forced invitation notification failure.'
        );
    }

    public function test_repeated_identical_email_invitation_does_not_send_duplicate_notification(): void
    {
        [$campaign, $owner] = $this->seedCampaignContext();
        $invitee = User::factory()->create(['email' => 'fallback-repeat@example.test']);

        Notification::fake();

        $payload = [
            'email' => $invitee->email,
            'role' => CampaignInvitation::ROLE_PLAYER,
        ];

        $this->actingAs($owner)
            ->post(route('campaigns.invitations.store', ['world' => $campaign->world, 'campaign' => $campaign]), $payload)
            ->assertRedirect(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]));

        $this->actingAs($owner)
            ->post(route('campaigns.invitations.store', ['world' => $campaign->world, 'campaign' => $campaign]), $payload)
            ->assertRedirect(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]));

        Notification::assertSentToTimes($invitee, CampaignInvitationNotification::class, 1);
        $this->assertSame(
            1,
            CampaignInvitation::query()
                ->where('campaign_id', (int) $campaign->id)
                ->where('user_id', (int) $invitee->id)
                ->count()
        );
    }

    public function test_server_blocks_manipulated_user_ids_for_existing_members(): void
    {
        [$campaign, $owner] = $this->seedCampaignContext();
        $alreadyMember = User::factory()->create(['email' => 'already-member@example.test']);
        $this->grantMembership($campaign, $alreadyMember, CampaignMembershipRole::PLAYER, $owner);

        $this->actingAs($owner)
            ->from(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->post(route('campaigns.invitations.store', ['world' => $campaign->world, 'campaign' => $campaign]), [
                'user_ids' => [(int) $alreadyMember->id],
                'role' => CampaignInvitation::ROLE_PLAYER,
            ])
            ->assertRedirect(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertSessionHasErrors('user_ids');

        $this->assertDatabaseMissing('campaign_invitations', [
            'campaign_id' => $campaign->id,
            'user_id' => $alreadyMember->id,
        ]);
    }

    public function test_server_blocks_manipulated_user_ids_for_existing_pending_invitations(): void
    {
        [$campaign, $owner] = $this->seedCampaignContext();
        $pendingInvitee = User::factory()->create(['email' => 'already-pending@example.test']);

        CampaignInvitation::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $pendingInvitee->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_PENDING,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'created_at' => now(),
        ]);

        $this->actingAs($owner)
            ->from(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->post(route('campaigns.invitations.store', ['world' => $campaign->world, 'campaign' => $campaign]), [
                'user_ids' => [(int) $pendingInvitee->id],
                'role' => CampaignInvitation::ROLE_PLAYER,
            ])
            ->assertRedirect(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertSessionHasErrors('user_ids');
    }

    public function test_mixed_valid_and_invalid_user_ids_do_not_create_partial_bulk_invitations(): void
    {
        [$campaign, $owner] = $this->seedCampaignContext();
        $candidateA = User::factory()->create(['email' => 'mixed-a@example.test']);
        $candidateB = User::factory()->create(['email' => 'mixed-b@example.test']);
        $alreadyMember = User::factory()->create(['email' => 'mixed-member@example.test']);
        $this->grantMembership($campaign, $alreadyMember, CampaignMembershipRole::PLAYER, $owner);

        $this->actingAs($owner)
            ->from(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->post(route('campaigns.invitations.store', ['world' => $campaign->world, 'campaign' => $campaign]), [
                'user_ids' => [(int) $candidateA->id, (int) $alreadyMember->id, (int) $candidateB->id],
                'role' => CampaignInvitation::ROLE_PLAYER,
            ])
            ->assertRedirect(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertSessionHasErrors('user_ids');

        $this->assertDatabaseMissing('campaign_invitations', [
            'campaign_id' => $campaign->id,
            'user_id' => $candidateA->id,
        ]);
        $this->assertDatabaseMissing('campaign_invitations', [
            'campaign_id' => $campaign->id,
            'user_id' => $candidateB->id,
        ]);
        $this->assertDatabaseMissing('campaign_invitations', [
            'campaign_id' => $campaign->id,
            'user_id' => $alreadyMember->id,
        ]);
    }

    public function test_registered_user_invitation_store_respects_world_campaign_guard(): void
    {
        [$campaign, $owner] = $this->seedCampaignContext();
        $foreignWorld = World::factory()->create([
            'slug' => 'registered-invite-foreign-world',
            'is_active' => true,
            'position' => -410,
        ]);
        $candidate = User::factory()->create(['email' => 'foreign-guard@example.test']);

        $this->actingAs($owner)
            ->post(route('campaigns.invitations.store', [
                'world' => $foreignWorld,
                'campaign' => $campaign,
            ]), [
                'user_ids' => [(int) $candidate->id],
                'role' => CampaignInvitation::ROLE_PLAYER,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('campaign_invitations', [
            'campaign_id' => $campaign->id,
            'user_id' => $candidate->id,
        ]);
    }

    /**
     * @return array{0: Campaign, 1: User, 2: User, 3: User}
     */
    private function seedCampaignContext(): array
    {
        $owner = User::factory()->gm()->create();
        $coGm = User::factory()->create();
        $actorPlayer = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);

        $this->grantMembership($campaign, $coGm, CampaignMembershipRole::GM, $owner);
        $this->grantMembership($campaign, $actorPlayer, CampaignMembershipRole::PLAYER, $owner);

        return [$campaign, $owner, $coGm, $actorPlayer];
    }

    private function grantMembership(Campaign $campaign, User $member, CampaignMembershipRole $role, User $assigner): void
    {
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
