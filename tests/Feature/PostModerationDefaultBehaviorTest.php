<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostModerationDefaultBehaviorTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_campaign_posts_are_approved_by_default(): void
    {
        [$owner, $player, $campaign, $scene, $character] = $this->seedContext(
            isPublic: false,
            requiresModeration: false,
        );

        $this->actingAs($player)->post(route('campaigns.scenes.posts.store', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]), [
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'character_id' => $character->id,
            'content' => 'Der Kundschafter meldet freien Weg durch den Nebel.',
        ])->assertRedirect();

        $post = Post::query()->latest('id')->firstOrFail();

        $this->assertSame('approved', $post->moderation_status);
        $this->assertNull($post->approved_by);
        $this->assertNotNull($post->approved_at);
    }

    public function test_private_campaign_can_enforce_moderation_via_campaign_setting(): void
    {
        [$owner, $player, $campaign, $scene, $character] = $this->seedContext(
            isPublic: false,
            requiresModeration: true,
        );

        $this->actingAs($player)->post(route('campaigns.scenes.posts.store', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]), [
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'character_id' => $character->id,
            'content' => 'Die Gruppe hält inne und wartet auf Freigabe.',
        ])->assertRedirect();

        $post = Post::query()->latest('id')->firstOrFail();

        $this->assertSame('pending', $post->moderation_status);
        $this->assertNull($post->approved_by);
        $this->assertNull($post->approved_at);
    }

    public function test_public_campaign_posts_require_moderation_by_default(): void
    {
        [$owner, $player, $campaign, $scene, $character] = $this->seedContext(
            isPublic: true,
            requiresModeration: false,
        );

        $this->actingAs($player)->post(route('campaigns.scenes.posts.store', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]), [
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'character_id' => $character->id,
            'content' => 'Öffentliche Kampagne mit Freigabeprozess.',
        ])->assertRedirect();

        $post = Post::query()->latest('id')->firstOrFail();

        $this->assertSame('pending', $post->moderation_status);
        $this->assertNull($post->approved_by);
        $this->assertNull($post->approved_at);
    }

    public function test_admin_user_override_bypasses_moderation_requirement(): void
    {
        [$owner, $player, $campaign, $scene, $character] = $this->seedContext(
            isPublic: true,
            requiresModeration: true,
            playerCanPostWithoutModeration: true,
        );

        $this->actingAs($player)->post(route('campaigns.scenes.posts.store', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]), [
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'character_id' => $character->id,
            'content' => 'Admin-Override: direkt sichtbar trotz Moderationsmodus.',
        ])->assertRedirect();

        $post = Post::query()->latest('id')->firstOrFail();

        $this->assertSame('approved', $post->moderation_status);
        $this->assertNull($post->approved_by);
        $this->assertNotNull($post->approved_at);
    }

    public function test_trusted_player_invitation_role_bypasses_moderation_requirement(): void
    {
        [$owner, $player, $campaign, $scene, $character] = $this->seedContext(
            isPublic: false,
            requiresModeration: true,
            invitationRole: CampaignInvitation::ROLE_TRUSTED_PLAYER,
        );

        $this->actingAs($player)->post(route('campaigns.scenes.posts.store', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]), [
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'character_id' => $character->id,
            'content' => 'Trusted Player postet ohne Warteschlange.',
        ])->assertRedirect();

        $post = Post::query()->latest('id')->firstOrFail();

        $this->assertSame('approved', $post->moderation_status);
        $this->assertNull($post->approved_by);
        $this->assertNotNull($post->approved_at);
    }

    /**
     * @return array{0: User, 1: User, 2: Campaign, 3: Scene, 4: Character}
     */
    private function seedContext(
        bool $isPublic,
        bool $requiresModeration,
        bool $playerCanPostWithoutModeration = false,
        string $invitationRole = CampaignInvitation::ROLE_PLAYER,
    ): array {
        $owner = User::factory()->gm()->create();
        $player = User::factory()->create([
            'can_post_without_moderation' => $playerCanPostWithoutModeration,
        ]);

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => $isPublic,
            'requires_post_moderation' => $requiresModeration,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        if (! $isPublic) {
            $campaign->invitations()->create([
                'user_id' => $player->id,
                'invited_by' => $owner->id,
                'status' => CampaignInvitation::STATUS_ACCEPTED,
                'role' => $invitationRole,
                'accepted_at' => now(),
                'responded_at' => now(),
                'created_at' => now(),
            ]);
        }

        $character = Character::factory()->create([
            'user_id' => $player->id,
            'world_id' => $campaign->world_id,
        ]);

        return [$owner, $player, $campaign, $scene, $character];
    }
}
