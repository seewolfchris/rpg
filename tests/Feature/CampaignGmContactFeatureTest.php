<?php

namespace Tests\Feature;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignGmContactMessage;
use App\Models\CampaignGmContactThread;
use App\Models\CampaignMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignGmContactFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_thread_creator_sees_only_own_threads(): void
    {
        $owner = User::factory()->gm()->create();
        $playerA = User::factory()->create();
        $playerB = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'is_public' => false,
            'status' => 'active',
        ]);

        $this->grantMembership($campaign, $playerA, CampaignMembershipRole::PLAYER, $owner);
        $this->grantMembership($campaign, $playerB, CampaignMembershipRole::PLAYER, $owner);

        CampaignGmContactThread::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $playerA->id,
            'subject' => 'P1-Eigen-Thread-A',
        ]);
        CampaignGmContactThread::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $playerA->id,
            'subject' => 'P1-Eigen-Thread-B',
        ]);
        CampaignGmContactThread::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $playerB->id,
            'subject' => 'P2-Fremd-Thread',
        ]);

        $this->actingAs($playerA)
            ->get(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertOk()
            ->assertSee('P1-Eigen-Thread-A')
            ->assertSee('P1-Eigen-Thread-B')
            ->assertDontSee('P2-Fremd-Thread');
    }

    public function test_other_players_cannot_view_foreign_threads(): void
    {
        $owner = User::factory()->gm()->create();
        $playerA = User::factory()->create();
        $playerB = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'is_public' => false,
            'status' => 'active',
        ]);

        $this->grantMembership($campaign, $playerA, CampaignMembershipRole::PLAYER, $owner);
        $this->grantMembership($campaign, $playerB, CampaignMembershipRole::PLAYER, $owner);

        $thread = CampaignGmContactThread::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $playerA->id,
            'subject' => 'Nur-Spieler-A',
        ]);

        $this->actingAs($playerB)
            ->get(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertOk()
            ->assertDontSee('Nur-Spieler-A');

        $this->actingAs($playerB)
            ->get(route('campaigns.gm-contacts.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'gmContactThread' => $thread,
            ]))
            ->assertForbidden();
    }

    public function test_owner_and_accepted_co_gm_see_all_threads_of_campaign(): void
    {
        $owner = User::factory()->gm()->create();
        $coGm = User::factory()->create();
        $playerA = User::factory()->create();
        $playerB = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'is_public' => false,
            'status' => 'active',
        ]);

        $this->grantMembership($campaign, $coGm, CampaignMembershipRole::GM, $owner);
        $this->grantMembership($campaign, $playerA, CampaignMembershipRole::PLAYER, $owner);
        $this->grantMembership($campaign, $playerB, CampaignMembershipRole::PLAYER, $owner);

        CampaignGmContactThread::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $playerA->id,
            'subject' => 'OwnerCoGmThread-A',
        ]);
        CampaignGmContactThread::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $playerB->id,
            'subject' => 'OwnerCoGmThread-B',
        ]);

        $this->actingAs($owner)
            ->get(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertOk()
            ->assertSee('OwnerCoGmThread-A')
            ->assertSee('OwnerCoGmThread-B');

        $this->actingAs($coGm)
            ->get(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertOk()
            ->assertSee('OwnerCoGmThread-A')
            ->assertSee('OwnerCoGmThread-B');
    }

    public function test_notifications_are_sent_to_exact_recipients_for_both_directions(): void
    {
        $owner = User::factory()->gm()->create();
        $coGm = User::factory()->create();
        $player = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'is_public' => false,
            'status' => 'active',
        ]);

        $this->grantMembership($campaign, $coGm, CampaignMembershipRole::GM, $owner);
        $this->grantMembership($campaign, $player, CampaignMembershipRole::PLAYER, $owner);

        $this->actingAs($player)
            ->post(route('campaigns.gm-contacts.store', ['world' => $campaign->world, 'campaign' => $campaign]), [
                'subject' => 'Notification-Matrix',
                'content' => 'Erste Nachricht vom Spieler.',
            ])
            ->assertStatus(302);

        /** @var CampaignGmContactThread $thread */
        $thread = CampaignGmContactThread::query()->latest('id')->firstOrFail();

        $this->assertSame(1, $owner->fresh()->notifications()->count());
        $this->assertSame(1, $coGm->fresh()->notifications()->count());
        $this->assertSame(0, $player->fresh()->notifications()->count());
        $this->assertSame(0, $admin->fresh()->notifications()->count());
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $owner->id,
            'notifiable_type' => User::class,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $coGm->id,
            'notifiable_type' => User::class,
        ]);

        $this->actingAs($coGm)
            ->post(route('campaigns.gm-contacts.messages.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'gmContactThread' => $thread,
            ]), [
                'content' => 'Antwort von Co-GM.',
            ])
            ->assertStatus(302);

        $this->assertSame(1, $owner->fresh()->notifications()->count());
        $this->assertSame(1, $coGm->fresh()->notifications()->count());
        $this->assertSame(1, $player->fresh()->notifications()->count());
        $this->assertSame(0, $admin->fresh()->notifications()->count());

        $this->actingAs($admin)
            ->post(route('campaigns.gm-contacts.messages.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'gmContactThread' => $thread,
            ]), [
                'content' => 'Antwort vom Admin.',
            ])
            ->assertStatus(302);

        $this->assertSame(1, $owner->fresh()->notifications()->count());
        $this->assertSame(1, $coGm->fresh()->notifications()->count());
        $this->assertSame(2, $player->fresh()->notifications()->count());
        $this->assertSame(0, $admin->fresh()->notifications()->count());
    }

    public function test_panel_list_is_hard_scoped_to_current_campaign_for_gm_side_and_admin(): void
    {
        $owner = User::factory()->gm()->create();
        $coGm = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $foreignOwner = User::factory()->gm()->create();
        $playerA = User::factory()->create();
        $playerB = User::factory()->create();

        $campaignA = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'is_public' => false,
            'status' => 'active',
        ]);
        $campaignB = Campaign::factory()->create([
            'owner_id' => $foreignOwner->id,
            'is_public' => false,
            'status' => 'active',
        ]);

        $this->grantMembership($campaignA, $coGm, CampaignMembershipRole::GM, $owner);
        $this->grantMembership($campaignA, $playerA, CampaignMembershipRole::PLAYER, $owner);
        $this->grantMembership($campaignB, $playerB, CampaignMembershipRole::PLAYER, $foreignOwner);

        CampaignGmContactThread::factory()->create([
            'campaign_id' => $campaignA->id,
            'created_by' => $playerA->id,
            'subject' => 'Panel-Nur-Kampagne-A',
        ]);
        $threadB = CampaignGmContactThread::factory()->create([
            'campaign_id' => $campaignB->id,
            'created_by' => $playerB->id,
            'subject' => 'Panel-Kampagnenfremd-B',
        ]);

        $this->actingAs($owner)
            ->get(route('campaigns.show', [
                'world' => $campaignA->world,
                'campaign' => $campaignA,
                'gm_contact_thread' => $threadB->id,
            ]))
            ->assertOk()
            ->assertSee('Panel-Nur-Kampagne-A')
            ->assertDontSee('Panel-Kampagnenfremd-B');

        $this->actingAs($coGm)
            ->get(route('campaigns.show', [
                'world' => $campaignA->world,
                'campaign' => $campaignA,
                'gm_contact_thread' => $threadB->id,
            ]))
            ->assertOk()
            ->assertSee('Panel-Nur-Kampagne-A')
            ->assertDontSee('Panel-Kampagnenfremd-B');

        $this->actingAs($admin)
            ->get(route('campaigns.show', [
                'world' => $campaignA->world,
                'campaign' => $campaignA,
                'gm_contact_thread' => $threadB->id,
            ]))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('campaigns.gm-contacts.show', [
                'world' => $campaignA->world,
                'campaign' => $campaignA,
                'gmContactThread' => $threadB,
            ]))
            ->assertNotFound();
    }

    public function test_self_notifications_are_not_created_when_owner_or_gm_writes(): void
    {
        $owner = User::factory()->gm()->create();
        $coGm = User::factory()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'is_public' => false,
            'status' => 'active',
        ]);

        $this->grantMembership($campaign, $coGm, CampaignMembershipRole::GM, $owner);
        $this->grantMembership($campaign, $player, CampaignMembershipRole::PLAYER, $owner);

        $this->actingAs($owner)
            ->post(route('campaigns.gm-contacts.store', ['world' => $campaign->world, 'campaign' => $campaign]), [
                'subject' => 'Owner-Eigen-Thread',
                'content' => 'Owner schreibt selbst.',
            ])
            ->assertStatus(302);

        /** @var CampaignGmContactThread $ownerThread */
        $ownerThread = CampaignGmContactThread::query()->where('subject', 'Owner-Eigen-Thread')->firstOrFail();

        $this->assertSame(0, $owner->fresh()->notifications()->count());
        $this->assertSame(0, $coGm->fresh()->notifications()->count());
        $this->assertSame(0, $player->fresh()->notifications()->count());

        $this->actingAs($owner)
            ->post(route('campaigns.gm-contacts.messages.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'gmContactThread' => $ownerThread,
            ]), [
                'content' => 'Owner antwortet im eigenen Thread.',
            ])
            ->assertStatus(302);

        $this->assertSame(0, $owner->fresh()->notifications()->count());
        $this->assertSame(0, $coGm->fresh()->notifications()->count());
        $this->assertSame(0, $player->fresh()->notifications()->count());
    }

    public function test_self_notifications_are_not_created_when_player_writes(): void
    {
        $owner = User::factory()->gm()->create();
        $coGm = User::factory()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'is_public' => false,
            'status' => 'active',
        ]);

        $this->grantMembership($campaign, $coGm, CampaignMembershipRole::GM, $owner);
        $this->grantMembership($campaign, $player, CampaignMembershipRole::PLAYER, $owner);

        $this->actingAs($player)
            ->post(route('campaigns.gm-contacts.store', ['world' => $campaign->world, 'campaign' => $campaign]), [
                'subject' => 'Player-Eigen-Thread',
                'content' => 'Player schreibt selbst.',
            ])
            ->assertStatus(302);

        /** @var CampaignGmContactThread $thread */
        $thread = CampaignGmContactThread::query()->where('subject', 'Player-Eigen-Thread')->firstOrFail();

        $this->assertSame(1, $owner->fresh()->notifications()->count());
        $this->assertSame(1, $coGm->fresh()->notifications()->count());
        $this->assertSame(0, $player->fresh()->notifications()->count());

        $this->actingAs($player)
            ->post(route('campaigns.gm-contacts.messages.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'gmContactThread' => $thread,
            ]), [
                'content' => 'Player schreibt erneut.',
            ])
            ->assertStatus(302);

        $this->assertSame(2, $owner->fresh()->notifications()->count());
        $this->assertSame(2, $coGm->fresh()->notifications()->count());
        $this->assertSame(0, $player->fresh()->notifications()->count());
    }

    public function test_global_gm_without_campaign_relation_cannot_create_or_view_threads(): void
    {
        $owner = User::factory()->gm()->create();
        $globalGm = User::factory()->gm()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'is_public' => false,
            'status' => 'active',
        ]);

        $this->grantMembership($campaign, $player, CampaignMembershipRole::PLAYER, $owner);

        $thread = CampaignGmContactThread::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $player->id,
            'subject' => 'Privater Thread ohne Global-GM-Zugriff',
        ]);

        $this->actingAs($globalGm)
            ->get(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertForbidden();

        $this->actingAs($globalGm)
            ->post(route('campaigns.gm-contacts.store', ['world' => $campaign->world, 'campaign' => $campaign]), [
                'subject' => 'Sollte blockiert werden',
                'content' => 'Global-GM ohne Kampagnenbezug.',
            ])
            ->assertForbidden();

        $this->actingAs($globalGm)
            ->get(route('campaigns.gm-contacts.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'gmContactThread' => $thread,
            ]))
            ->assertForbidden();
    }

    public function test_non_participant_public_spectator_cannot_create_or_view_threads(): void
    {
        $owner = User::factory()->gm()->create();
        $participant = User::factory()->create();
        $spectator = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'is_public' => true,
            'status' => 'active',
        ]);

        $this->grantMembership($campaign, $participant, CampaignMembershipRole::PLAYER, $owner);

        $thread = CampaignGmContactThread::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $participant->id,
            'subject' => 'Nur-Teilnehmer-Thread',
        ]);

        $this->actingAs($spectator)
            ->get(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertOk()
            ->assertDontSee('Nur-Teilnehmer-Thread');

        $this->actingAs($spectator)
            ->post(route('campaigns.gm-contacts.store', ['world' => $campaign->world, 'campaign' => $campaign]), [
                'subject' => 'Public Zuschauer',
                'content' => 'Darf nicht erstellt werden.',
            ])
            ->assertForbidden();

        $this->actingAs($spectator)
            ->get(route('campaigns.gm-contacts.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'gmContactThread' => $thread,
            ]))
            ->assertForbidden();
    }

    public function test_closed_threads_block_reply_and_only_gm_side_can_reopen(): void
    {
        $owner = User::factory()->gm()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'is_public' => false,
            'status' => 'active',
        ]);

        $this->grantMembership($campaign, $player, CampaignMembershipRole::PLAYER, $owner);

        $thread = CampaignGmContactThread::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $player->id,
            'status' => CampaignGmContactThread::STATUS_CLOSED,
            'subject' => 'Geschlossener Thread',
        ]);

        CampaignGmContactMessage::factory()->create([
            'thread_id' => $thread->id,
            'user_id' => $player->id,
            'content' => 'Ursprungsnachricht.',
        ]);

        $this->actingAs($player)
            ->post(route('campaigns.gm-contacts.messages.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'gmContactThread' => $thread,
            ]), [
                'content' => 'Antwort auf closed.',
            ])
            ->assertForbidden();

        $this->actingAs($player)
            ->patch(route('campaigns.gm-contacts.status.update', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'gmContactThread' => $thread,
            ]), [
                'status' => CampaignGmContactThread::STATUS_OPEN,
            ])
            ->assertForbidden();

        $this->actingAs($owner)
            ->patch(route('campaigns.gm-contacts.status.update', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'gmContactThread' => $thread,
            ]), [
                'status' => CampaignGmContactThread::STATUS_OPEN,
            ])
            ->assertStatus(302);

        $this->assertDatabaseHas('campaign_gm_contact_threads', [
            'id' => $thread->id,
            'status' => CampaignGmContactThread::STATUS_OPEN,
        ]);

        $this->actingAs($player)
            ->post(route('campaigns.gm-contacts.messages.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'gmContactThread' => $thread,
            ]), [
                'content' => 'Antwort nach Wiederöffnung.',
            ])
            ->assertStatus(302);

        $this->assertDatabaseHas('campaign_gm_contact_messages', [
            'thread_id' => $thread->id,
            'user_id' => $player->id,
            'content' => 'Antwort nach Wiederöffnung.',
        ]);
    }

    private function grantMembership(
        Campaign $campaign,
        User $member,
        CampaignMembershipRole $role,
        User $assigner
    ): void
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
