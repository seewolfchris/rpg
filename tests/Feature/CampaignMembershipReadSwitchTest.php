<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignMembershipReadSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_visibility_uses_membership_without_accepted_invitation(): void
    {
        $owner = User::factory()->gm()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'is_public' => false,
            'status' => 'active',
        ]);

        $this->attachMembership($campaign, $member, CampaignMembershipRole::PLAYER);

        $this->actingAs($member)
            ->get(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertOk();

        $this->actingAs($outsider)
            ->get(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertForbidden();
    }

    public function test_owner_and_gm_membership_can_create_update_and_delete_scenes(): void
    {
        $owner = User::factory()->gm()->create();
        $gmMember = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'is_public' => false,
            'status' => 'active',
        ]);

        $this->attachMembership($campaign, $gmMember, CampaignMembershipRole::GM);

        $this->actingAs($owner)->post(route('campaigns.scenes.store', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]), [
            'title' => 'Owner Scene',
            'slug' => 'owner-scene',
            'summary' => 'Owner scene summary.',
            'status' => 'open',
            'mood' => 'neutral',
            'position' => 1,
            'allow_ooc' => true,
        ])->assertRedirect();

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'title' => 'Initial Scene',
            'slug' => 'initial-scene',
            'status' => 'open',
            'allow_ooc' => true,
            'mood' => 'neutral',
        ]);

        $this->actingAs($gmMember)->patch(route('campaigns.scenes.update', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]), [
            'title' => 'GM Updated Scene',
            'slug' => 'gm-updated-scene',
            'summary' => 'Updated by gm membership.',
            'description' => 'Details',
            'status' => 'open',
            'mood' => 'neutral',
            'position' => 2,
            'allow_ooc' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('scenes', [
            'id' => $scene->id,
            'slug' => 'gm-updated-scene',
            'title' => 'GM Updated Scene',
        ]);

        $this->actingAs($gmMember)->delete(route('campaigns.scenes.destroy', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]))->assertRedirect();

        $this->assertDatabaseMissing('scenes', [
            'id' => $scene->id,
        ]);
    }

    public function test_player_and_trusted_player_memberships_cannot_use_gm_only_paths(): void
    {
        $owner = User::factory()->gm()->create();
        $player = User::factory()->create();
        $trustedPlayer = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'is_public' => false,
            'status' => 'active',
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
            'mood' => 'neutral',
        ]);

        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $owner->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'Pending moderation item.',
            'moderation_status' => 'pending',
        ]);

        $this->attachMembership($campaign, $player, CampaignMembershipRole::PLAYER);
        $this->attachMembership($campaign, $trustedPlayer, CampaignMembershipRole::TRUSTED_PLAYER);

        foreach ([$player, $trustedPlayer] as $actor) {
            $this->actingAs($actor)->post(route('campaigns.scenes.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
            ]), [
                'title' => 'Blocked Scene',
                'slug' => 'blocked-scene-'.$actor->id,
                'summary' => 'Should be blocked.',
                'status' => 'open',
                'mood' => 'neutral',
                'position' => 1,
                'allow_ooc' => true,
            ])->assertForbidden();

            $this->actingAs($actor)->patch(route('posts.moderate', [
                'world' => $campaign->world,
                'post' => $post,
            ]), [
                'moderation_status' => 'approved',
            ])->assertForbidden();
        }
    }

    public function test_accepting_invitation_keeps_membership_based_campaign_access_in_sync(): void
    {
        $owner = User::factory()->gm()->create();
        $invitee = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'is_public' => false,
            'status' => 'active',
        ]);

        $invitation = CampaignInvitation::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $invitee->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_PENDING,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'created_at' => now(),
        ]);

        $this->actingAs($invitee)->patch(route('campaign-invitations.accept', [
            'world' => $campaign->world,
            'invitation' => $invitation,
        ]))->assertRedirect(route('campaigns.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]));

        $this->assertDatabaseHas('campaign_memberships', [
            'campaign_id' => $campaign->id,
            'user_id' => $invitee->id,
            'role' => CampaignMembershipRole::PLAYER->value,
        ]);

        $this->actingAs($invitee)
            ->get(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertOk();
    }

    public function test_gm_membership_can_moderate_posts_without_global_gm_role(): void
    {
        $owner = User::factory()->gm()->create();
        $gmMember = User::factory()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'is_public' => false,
            'status' => 'active',
            'requires_post_moderation' => true,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
            'mood' => 'neutral',
        ]);

        $this->attachMembership($campaign, $gmMember, CampaignMembershipRole::GM);
        $this->attachMembership($campaign, $player, CampaignMembershipRole::PLAYER);

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
            'content_format' => 'markdown',
            'character_id' => $character->id,
            'content' => str_repeat('Player pending post. ', 2),
        ])->assertRedirect();

        $post = Post::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', $player->id)
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($gmMember)->patch(route('posts.moderate', [
            'world' => $campaign->world,
            'post' => $post,
        ]), [
            'moderation_status' => 'approved',
        ])->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'moderation_status' => 'approved',
            'approved_by' => $gmMember->id,
        ]);
    }

    public function test_admin_is_not_implicitly_campaign_gm_without_membership(): void
    {
        $owner = User::factory()->gm()->create();
        $admin = User::factory()->admin()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'is_public' => false,
            'status' => 'active',
            'requires_post_moderation' => true,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
            'mood' => 'neutral',
        ]);
        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'Pending admin check.',
            'moderation_status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->assertForbidden();

        $this->actingAs($admin)->post(route('campaigns.scenes.store', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]), [
            'title' => 'Admin blocked',
            'slug' => 'admin-blocked',
            'summary' => 'Blocked',
            'status' => 'open',
            'mood' => 'neutral',
            'position' => 1,
            'allow_ooc' => true,
        ])->assertForbidden();

        $this->actingAs($admin)->patch(route('posts.moderate', [
            'world' => $campaign->world,
            'post' => $post,
        ]), [
            'moderation_status' => 'approved',
        ])->assertForbidden();
    }

    public function test_gm_membership_can_post_ic_gm_without_character(): void
    {
        $owner = User::factory()->gm()->create();
        $gmMember = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'is_public' => false,
            'status' => 'active',
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
            'mood' => 'neutral',
        ]);

        $this->attachMembership($campaign, $gmMember, CampaignMembershipRole::GM);

        $response = $this->actingAs($gmMember)->post(route('campaigns.scenes.posts.store', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]), [
            'post_type' => 'ic',
            'post_mode' => 'gm',
            'content_format' => 'markdown',
            'content' => str_repeat('GM membership narration. ', 2),
        ]);

        $response->assertRedirectContains('/campaigns/'.$campaign->id.'/scenes/'.$scene->id);

        $post = Post::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', $gmMember->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertNull($post->character_id);
        $this->assertSame('gm', data_get($post->meta, 'author_role'));
    }

    public function test_player_ic_post_without_character_remains_blocked(): void
    {
        $owner = User::factory()->gm()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'is_public' => false,
            'status' => 'active',
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
            'mood' => 'neutral',
        ]);

        $this->attachMembership($campaign, $player, CampaignMembershipRole::PLAYER);

        $response = $this->actingAs($player)
            ->from(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->post(route('campaigns.scenes.posts.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]), [
                'post_type' => 'ic',
                'content_format' => 'markdown',
                'content' => str_repeat('IC without character. ', 2),
            ]);

        $response->assertRedirect(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));
        $response->assertSessionHasErrors('character_id');

        $this->assertDatabaseMissing('posts', [
            'scene_id' => $scene->id,
            'user_id' => $player->id,
        ]);
    }

    private function attachMembership(Campaign $campaign, User $user, CampaignMembershipRole $role): void
    {
        $campaign->memberships()->updateOrCreate(
            ['user_id' => (int) $user->id],
            [
                'role' => $role->value,
                'assigned_by' => (int) $campaign->owner_id,
                'assigned_at' => now(),
            ]
        );
    }
}
