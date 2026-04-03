<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Character;
use App\Models\Scene;
use App\Models\SceneBookmark;
use App\Models\SceneSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignAccessInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_campaign_is_hidden_for_non_invited_player(): void
    {
        $owner = User::factory()->gm()->create();
        $outsider = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'title' => 'Geheimbund von Tharn',
            'status' => 'active',
            'is_public' => false,
        ]);

        $indexResponse = $this->actingAs($outsider)->get(route('campaigns.index'));
        $indexResponse->assertOk();
        $indexResponse->assertDontSee('Geheimbund von Tharn');

        $showResponse = $this->actingAs($outsider)->get(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]));
        $showResponse->assertForbidden();
    }

    public function test_player_gains_access_only_after_accepting_invitation(): void
    {
        $owner = User::factory()->gm()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'title' => 'Schwur der Dornfeste',
            'status' => 'active',
            'is_public' => false,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'title' => 'Schattentor',
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $this->actingAs($owner)
            ->post(route('campaigns.invitations.store', ['world' => $campaign->world, 'campaign' => $campaign]), [
                'email' => $player->email,
                'role' => CampaignInvitation::ROLE_PLAYER,
            ])
            ->assertRedirect(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]));

        $invitation = CampaignInvitation::query()
            ->where('campaign_id', $campaign->id)
            ->where('user_id', $player->id)
            ->firstOrFail();

        $this->assertSame(CampaignInvitation::STATUS_PENDING, $invitation->status);
        $this->assertSame(CampaignInvitation::ROLE_PLAYER, $invitation->role);
        $this->assertNull($invitation->accepted_at);

        $this->actingAs($player)
            ->get(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertForbidden();

        $this->actingAs($player)
            ->patch(route('campaign-invitations.accept', ['world' => $campaign->world, 'invitation' => $invitation]))
            ->assertRedirect(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]));

        $invitation->refresh();
        $this->assertSame(CampaignInvitation::STATUS_ACCEPTED, $invitation->status);
        $this->assertNotNull($invitation->accepted_at);

        $this->actingAs($player)
            ->get(route('campaigns.index'))
            ->assertOk()
            ->assertSee('Schwur der Dornfeste');

        $character = Character::factory()->create([
            'user_id' => $player->id,
        ]);

        $this->actingAs($player)
            ->post(route('campaigns.scenes.posts.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]), [
                'post_type' => 'ic',
                'character_id' => $character->id,
                'content_format' => 'plain',
                'content' => 'Ich nehme die Einladung an und betrete die Szene.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'post_type' => 'ic',
        ]);

        $notification = $player->fresh()->unreadNotifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame('campaign_invitation', $notification->data['kind'] ?? null);
    }

    public function test_declined_invitation_does_not_grant_access(): void
    {
        $owner = User::factory()->gm()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);

        $this->actingAs($owner)->post(route('campaigns.invitations.store', ['world' => $campaign->world, 'campaign' => $campaign]), [
            'email' => $player->email,
            'role' => CampaignInvitation::ROLE_PLAYER,
        ])->assertRedirect();

        $invitation = CampaignInvitation::query()
            ->where('campaign_id', $campaign->id)
            ->where('user_id', $player->id)
            ->firstOrFail();

        $this->actingAs($player)
            ->patch(route('campaign-invitations.decline', ['world' => $campaign->world, 'invitation' => $invitation]))
            ->assertRedirect(route('campaign-invitations.index'));

        $this->assertDatabaseHas('campaign_invitations', [
            'id' => $invitation->id,
            'status' => CampaignInvitation::STATUS_DECLINED,
        ]);

        $this->actingAs($player)
            ->get(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertForbidden();
    }

    public function test_legacy_invitation_accept_endpoint_remains_compatible_during_transition(): void
    {
        $owner = User::factory()->gm()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);

        $this->actingAs($owner)->post(route('campaigns.invitations.store', ['world' => $campaign->world, 'campaign' => $campaign]), [
            'email' => $player->email,
            'role' => CampaignInvitation::ROLE_PLAYER,
        ])->assertRedirect();

        $invitation = CampaignInvitation::query()
            ->where('campaign_id', $campaign->id)
            ->where('user_id', $player->id)
            ->firstOrFail();

        $this->actingAs($player)
            ->patch(route('campaign-invitations.accept.legacy', ['invitation' => $invitation]))
            ->assertRedirect(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]));

        $this->assertDatabaseHas('campaign_invitations', [
            'id' => $invitation->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
        ]);
    }

    public function test_invited_co_gm_can_manage_scenes_and_moderate_posts_but_cannot_delete_campaign(): void
    {
        $owner = User::factory()->gm()->create();
        $coGm = User::factory()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $this->actingAs($owner)->post(route('campaigns.invitations.store', ['world' => $campaign->world, 'campaign' => $campaign]), [
            'email' => $coGm->email,
            'role' => CampaignInvitation::ROLE_CO_GM,
        ])->assertRedirect();

        $coGmInvitation = CampaignInvitation::query()
            ->where('campaign_id', $campaign->id)
            ->where('user_id', $coGm->id)
            ->firstOrFail();

        $this->actingAs($coGm)
            ->patch(route('campaign-invitations.accept', ['world' => $campaign->world, 'invitation' => $coGmInvitation]))
            ->assertRedirect();

        $this->actingAs($owner)->post(route('campaigns.invitations.store', ['world' => $campaign->world, 'campaign' => $campaign]), [
            'email' => $player->email,
            'role' => CampaignInvitation::ROLE_PLAYER,
        ])->assertRedirect();

        $playerInvitation = CampaignInvitation::query()
            ->where('campaign_id', $campaign->id)
            ->where('user_id', $player->id)
            ->firstOrFail();

        $this->actingAs($player)
            ->patch(route('campaign-invitations.accept', ['world' => $campaign->world, 'invitation' => $playerInvitation]))
            ->assertRedirect();

        $character = Character::factory()->create([
            'user_id' => $player->id,
        ]);

        $this->actingAs($player)
            ->post(route('campaigns.scenes.posts.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]), [
                'post_type' => 'ic',
                'character_id' => $character->id,
                'content_format' => 'plain',
                'content' => 'Ein Schatten huscht durch den Gang.',
            ])
            ->assertRedirect();

        $post = \App\Models\Post::query()->where('scene_id', $scene->id)->where('user_id', $player->id)->latest('id')->firstOrFail();

        $this->actingAs($coGm)
            ->patch(route('posts.moderate', ['world' => $post->scene->campaign->world, 'post' => $post]), [
                'moderation_status' => 'approved',
                'moderation_note' => 'Co-GM Freigabe.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'moderation_status' => 'approved',
            'approved_by' => $coGm->id,
        ]);

        $this->actingAs($coGm)
            ->post(route('campaigns.scenes.store', ['world' => $campaign->world, 'campaign' => $campaign]), [
                'title' => 'Wachturm',
                'slug' => 'wachturm',
                'summary' => 'Co-GM erstellt eine neue Szene.',
                'status' => 'open',
                'position' => 2,
                'allow_ooc' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('scenes', [
            'campaign_id' => $campaign->id,
            'slug' => 'wachturm',
            'created_by' => $coGm->id,
        ]);

        $this->actingAs($coGm)
            ->delete(route('campaigns.destroy', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertForbidden();
    }

    public function test_non_admin_cannot_assign_trusted_player_role_on_invitation(): void
    {
        $owner = User::factory()->gm()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);

        $this->actingAs($owner)
            ->from(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->post(route('campaigns.invitations.store', ['world' => $campaign->world, 'campaign' => $campaign]), [
                'email' => $player->email,
                'role' => CampaignInvitation::ROLE_TRUSTED_PLAYER,
            ])
            ->assertRedirect(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('campaign_invitations', [
            'campaign_id' => $campaign->id,
            'user_id' => $player->id,
            'role' => CampaignInvitation::ROLE_TRUSTED_PLAYER,
        ]);
    }

    public function test_repeated_invitation_store_requests_keep_single_invitation_record(): void
    {
        $owner = User::factory()->gm()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);

        $payload = [
            'email' => $player->email,
            'role' => CampaignInvitation::ROLE_PLAYER,
        ];

        $this->actingAs($owner)
            ->post(route('campaigns.invitations.store', ['world' => $campaign->world, 'campaign' => $campaign]), $payload)
            ->assertRedirect(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]));

        $this->actingAs($owner)
            ->post(route('campaigns.invitations.store', ['world' => $campaign->world, 'campaign' => $campaign]), $payload)
            ->assertRedirect(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]));

        $this->assertSame(
            1,
            CampaignInvitation::query()
                ->where('campaign_id', $campaign->id)
                ->where('user_id', $player->id)
                ->count()
        );

        $this->assertDatabaseHas('campaign_invitations', [
            'campaign_id' => $campaign->id,
            'user_id' => $player->id,
            'status' => CampaignInvitation::STATUS_PENDING,
            'role' => CampaignInvitation::ROLE_PLAYER,
        ]);
    }

    public function test_admin_can_assign_trusted_player_role_and_player_posts_without_moderation(): void
    {
        $owner = User::factory()->gm()->create();
        $admin = User::factory()->admin()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => false,
            'requires_post_moderation' => true,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $this->actingAs($admin)->post(route('campaigns.invitations.store', ['world' => $campaign->world, 'campaign' => $campaign]), [
            'email' => $player->email,
            'role' => CampaignInvitation::ROLE_TRUSTED_PLAYER,
        ])->assertRedirect();

        $invitation = CampaignInvitation::query()
            ->where('campaign_id', $campaign->id)
            ->where('user_id', $player->id)
            ->firstOrFail();

        $this->assertSame(CampaignInvitation::ROLE_TRUSTED_PLAYER, $invitation->role);

        $this->actingAs($player)
            ->patch(route('campaign-invitations.accept', ['world' => $campaign->world, 'invitation' => $invitation]))
            ->assertRedirect();

        $character = Character::factory()->create([
            'user_id' => $player->id,
            'world_id' => $campaign->world_id,
        ]);

        $this->actingAs($player)->post(route('campaigns.scenes.posts.store', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]), [
            'post_type' => 'ic',
            'character_id' => $character->id,
            'content_format' => 'plain',
            'content' => 'Trusted Player postet ohne Moderationswarteschlange.',
        ])->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'moderation_status' => 'approved',
            'approved_by' => null,
        ]);
    }

    public function test_campaign_show_filters_scenes_by_status_and_search(): void
    {
        $owner = User::factory()->gm()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'title' => 'Kronenfall',
            'status' => 'active',
            'is_public' => true,
        ]);

        Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'title' => 'Nebelpass',
            'summary' => 'Ein stiller Anfang.',
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'title' => 'Ruinentor',
            'summary' => 'Der Pfad ist versiegelt.',
            'status' => 'closed',
            'allow_ooc' => true,
        ]);
        Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'title' => 'Ruinen in Asche',
            'summary' => 'Nur Erinnerungen bleiben.',
            'status' => 'archived',
            'allow_ooc' => true,
        ]);

        $filteredResponse = $this->actingAs($owner)->get(route('campaigns.show', [
            'campaign' => $campaign,
            'scene_status' => 'closed',
            'q' => 'Ruin',
        ]));

        $filteredResponse->assertOk();
        $filteredResponse->assertSee('Ruinentor');
        $filteredResponse->assertDontSee('Nebelpass');
        $filteredResponse->assertDontSee('Ruinen in Asche');

        $allResponse = $this->actingAs($owner)->get(route('campaigns.show', [
            'campaign' => $campaign,
            'scene_status' => 'all',
            'q' => 'Ruin',
        ]));

        $allResponse->assertOk();
        $allResponse->assertSee('Ruinentor');
        $allResponse->assertSee('Ruinen in Asche');
        $allResponse->assertDontSee('Nebelpass');
    }

    public function test_co_gm_can_edit_player_post_with_player_character(): void
    {
        $owner = User::factory()->gm()->create();
        $coGm = User::factory()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        CampaignInvitation::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $coGm->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_CO_GM,
            'accepted_at' => now(),
            'responded_at' => now(),
            'created_at' => now(),
        ]);

        CampaignInvitation::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $player->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'accepted_at' => now(),
            'responded_at' => now(),
            'created_at' => now(),
        ]);

        $character = Character::factory()->create([
            'user_id' => $player->id,
            'world_id' => $campaign->world_id,
        ]);

        $this->actingAs($player)
            ->post(route('campaigns.scenes.posts.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]), [
                'post_type' => 'ic',
                'character_id' => $character->id,
                'content_format' => 'plain',
                'content' => 'Originalbeitrag des Spielers in der Szene.',
            ])
            ->assertRedirect();

        $post = \App\Models\Post::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', $player->id)
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($coGm)
            ->patch(route('posts.update', ['world' => $campaign->world, 'post' => $post]), [
                'post_type' => 'ic',
                'character_id' => $character->id,
                'content_format' => 'plain',
                'content' => 'Co-GM hat den Beitrag redaktionell angepasst.',
                'moderation_status' => 'approved',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $post->refresh();

        $this->assertSame('Co-GM hat den Beitrag redaktionell angepasst.', $post->content);
        $this->assertSame($character->id, (int) $post->character_id);
        $this->assertSame('approved', $post->moderation_status);
    }

    public function test_removing_accepted_invitation_cleans_scene_subscriptions_and_bookmarks(): void
    {
        $owner = User::factory()->gm()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $this->actingAs($owner)->post(route('campaigns.invitations.store', ['world' => $campaign->world, 'campaign' => $campaign]), [
            'email' => $player->email,
            'role' => CampaignInvitation::ROLE_PLAYER,
        ])->assertRedirect();

        $invitation = CampaignInvitation::query()
            ->where('campaign_id', $campaign->id)
            ->where('user_id', $player->id)
            ->firstOrFail();

        $this->actingAs($player)
            ->patch(route('campaign-invitations.accept', ['world' => $campaign->world, 'invitation' => $invitation]))
            ->assertRedirect();

        SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'is_muted' => false,
            'last_read_post_id' => null,
            'last_read_at' => null,
        ]);

        SceneBookmark::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'post_id' => null,
            'label' => 'Zwischenstand',
        ]);

        $this->actingAs($owner)
            ->delete(route('campaigns.invitations.destroy', ['world' => $campaign->world, 'campaign' => $campaign, 'invitation' => $invitation]))
            ->assertRedirect(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]));

        $this->assertDatabaseMissing('scene_subscriptions', [
            'scene_id' => $scene->id,
            'user_id' => $player->id,
        ]);
        $this->assertDatabaseMissing('scene_bookmarks', [
            'scene_id' => $scene->id,
            'user_id' => $player->id,
        ]);
    }
}
