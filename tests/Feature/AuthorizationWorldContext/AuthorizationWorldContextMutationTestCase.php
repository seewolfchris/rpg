<?php

namespace Tests\Feature\AuthorizationWorldContext;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class AuthorizationWorldContextMutationTestCase extends TestCase
{
    use RefreshDatabase;

    protected function seedCampaignRoleMatrix(bool $worldActive): array
    {
        $owner = User::factory()->gm()->canCreateCampaigns()->create();
        $coGm = User::factory()->create();
        $player = User::factory()->create();
        $outsider = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $world = World::factory()->create([
            'slug' => $worldActive ? 'a3-aktive-welt' : 'a3-inaktive-kampagnenwelt',
            'is_active' => $worldActive,
            'position' => -300,
        ]);

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'world_id' => $world->id,
            'status' => 'active',
            'is_public' => false,
        ]);

        $this->grantMembership($campaign, $coGm, CampaignMembershipRole::GM, $owner);
        $this->grantMembership($campaign, $player, CampaignMembershipRole::PLAYER, $owner);

        return [$campaign, $owner, $coGm, $player, $outsider, $admin];
    }

    protected function grantMembership(Campaign $campaign, User $user, CampaignMembershipRole $role, User $inviter): void
    {
        CampaignMembership::query()->updateOrCreate(
            [
                'campaign_id' => (int) $campaign->id,
                'user_id' => (int) $user->id,
            ],
            [
                'role' => $role->value,
                'assigned_by' => (int) $inviter->id,
                'assigned_at' => now(),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function scenePayload(string $slug, string $title): array
    {
        return [
            'title' => $title,
            'slug' => $slug,
            'summary' => 'A3 Matrix Szenen-Create',
            'status' => 'open',
            'mood' => 'neutral',
            'position' => 1,
            'allow_ooc' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function gmProgressionPayload(
        int $campaignId,
        int $sceneId,
        int $characterId,
        string $reason
    ): array {
        return [
            'campaign_id' => $campaignId,
            'scene_id' => $sceneId,
            'event_mode' => 'milestone',
            'reason' => $reason,
            'awards' => [[
                'character_id' => $characterId,
                'xp_delta' => 30,
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function inventoryQuickActionPayload(int $characterId, array $overrides = []): array
    {
        return array_merge([
            'inventory_action_character_id' => $characterId,
            'inventory_action_type' => 'add',
            'inventory_action_item' => 'Heiltrank',
            'inventory_action_quantity' => 2,
            'inventory_action_equipped' => '0',
            'inventory_action_note' => 'A3 Matrix',
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    protected function sceneSubscriptionBulkPayload(string $action, string $status, string $search): array
    {
        return [
            'bulk_action' => $action,
            'status' => $status,
            'q' => $search,
        ];
    }

    /**
     * @param  list<int>  $postIds
     * @return array<string, mixed>
     */
    protected function gmBulkModerationPayload(string $moderationStatus, array $postIds, ?int $sceneId = null): array
    {
        $payload = [
            'status' => 'all',
            'q' => '',
            'moderation_status' => $moderationStatus,
            'moderation_note' => 'A3 Matrix Bulk',
        ];

        if ($postIds !== []) {
            $payload['post_ids'] = $postIds;
        }

        if ($sceneId !== null && $sceneId > 0) {
            $payload['scene_id'] = $sceneId;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function campaignInvitationPayload(string $email, string $role): array
    {
        return [
            'email' => $email,
            'role' => $role,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function postUpdatePayload(string $content): array
    {
        return [
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => $content,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function sceneUpdatePayload(string $slug, string $title): array
    {
        return [
            'title' => $title,
            'slug' => $slug,
            'summary' => 'A3 Matrix Scene Update',
            'description' => 'A3 Matrix Scene Update Description',
            'status' => 'open',
            'mood' => 'neutral',
            'position' => 2,
            'allow_ooc' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function campaignUpdatePayload(string $slug, string $title): array
    {
        return [
            'title' => $title,
            'slug' => $slug,
            'summary' => 'A3 Matrix Campaign Update',
            'lore' => 'A3 Matrix Campaign Update Lore',
            'status' => 'active',
            'is_public' => false,
            'requires_post_moderation' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function postStorePayload(string $content): array
    {
        return [
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => $content,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function postModerationPayload(string $status, string $note): array
    {
        return [
            'moderation_status' => $status,
            'moderation_note' => $note,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function campaignStorePayload(string $slug, string $title): array
    {
        return [
            'title' => $title,
            'slug' => $slug,
            'summary' => 'A3 Matrix Campaign Store',
            'lore' => 'A3 Matrix Campaign Store Lore',
            'status' => 'active',
            'is_public' => false,
            'requires_post_moderation' => false,
        ];
    }
}
