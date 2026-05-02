<?php

namespace Tests\Unit\Domain;

use App\Domain\Post\Exceptions\PostInventoryAwardInvariantViolationException;
use App\Domain\Post\Exceptions\PostProbeInvariantViolationException;
use App\Domain\Post\PostInventoryAwardService;
use App\Domain\Post\PostProbeService;
use App\Domain\Scene\Exceptions\SceneInventoryQuickActionInvariantViolationException;
use App\Domain\Scene\SceneInventoryQuickActionService;
use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\Character;
use App\Models\DiceRoll;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceScopeInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_probe_service_rejects_world_mismatch_even_for_campaign_participant(): void
    {
        [$gm, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        CampaignMembership::query()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $participant->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => (int) $gm->id,
            'assigned_at' => now(),
        ]);

        $otherWorld = World::factory()->create();
        $targetCharacter = Character::factory()->create([
            'user_id' => $participant->id,
            'world_id' => $otherWorld->id,
            'mu' => 50,
            'le_max' => 40,
            'le_current' => 40,
            'ae_max' => 10,
            'ae_current' => 10,
        ]);

        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'content' => 'GM test post',
        ]);

        try {
            app(PostProbeService::class)->createForPost(
                post: $post,
                data: [
                    'probe_enabled' => true,
                    'probe_character_id' => $targetCharacter->id,
                    'probe_roll_mode' => DiceRoll::MODE_NORMAL,
                    'probe_modifier' => 0,
                    'probe_attribute_key' => 'mu',
                    'probe_explanation' => 'Invariant check world mismatch',
                    'probe_le_delta' => -5,
                    'probe_ae_delta' => 0,
                ],
                user: $gm,
                scene: $scene,
                isModerator: true,
            );
            $this->fail('Expected PostProbeInvariantViolationException to be thrown.');
        } catch (PostProbeInvariantViolationException $exception) {
            $this->assertSame('target_character_world_mismatch', $exception->reason());
            $this->assertSame('probe_character_id', $exception->field());
        }

        $this->assertDatabaseCount('dice_rolls', 0);
        $this->assertSame(40, (int) $targetCharacter->fresh()->le_current);
    }

    public function test_post_inventory_award_service_rejects_non_participant_target(): void
    {
        [$gm, $campaign, $scene] = $this->campaignContext();
        $outsider = User::factory()->create();
        $targetCharacter = Character::factory()->create([
            'user_id' => $outsider->id,
            'world_id' => $campaign->world_id,
            'inventory' => ['Fackel'],
        ]);

        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'content' => 'GM inventory award test',
        ]);

        try {
            app(PostInventoryAwardService::class)->applyForPost(
                post: $post,
                data: [
                    'inventory_award_enabled' => true,
                    'inventory_award_character_id' => $targetCharacter->id,
                    'inventory_award_item' => 'Heiltrank',
                    'inventory_award_quantity' => 2,
                    'inventory_award_equipped' => false,
                ],
                scene: $scene,
                isModerator: true,
                user: $gm,
            );
            $this->fail('Expected PostInventoryAwardInvariantViolationException to be thrown.');
        } catch (PostInventoryAwardInvariantViolationException $exception) {
            $this->assertSame('target_character_not_participant', $exception->reason());
            $this->assertSame('inventory_award_character_id', $exception->field());
        }

        $this->assertSame(['Fackel'], $targetCharacter->fresh()->inventory);
        $this->assertDatabaseCount('character_inventory_logs', 0);
    }

    public function test_post_inventory_award_service_rejects_world_mismatch_even_for_participant(): void
    {
        [$gm, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        CampaignMembership::query()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $participant->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => (int) $gm->id,
            'assigned_at' => now(),
        ]);
        $otherWorld = World::factory()->create();
        $targetCharacter = Character::factory()->create([
            'user_id' => $participant->id,
            'world_id' => $otherWorld->id,
            'inventory' => ['Fackel'],
        ]);

        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'content' => 'GM inventory award world mismatch test',
        ]);

        try {
            app(PostInventoryAwardService::class)->applyForPost(
                post: $post,
                data: [
                    'inventory_award_enabled' => true,
                    'inventory_award_character_id' => $targetCharacter->id,
                    'inventory_award_item' => 'Heiltrank',
                    'inventory_award_quantity' => 2,
                    'inventory_award_equipped' => false,
                ],
                scene: $scene,
                isModerator: true,
                user: $gm,
            );
            $this->fail('Expected PostInventoryAwardInvariantViolationException to be thrown.');
        } catch (PostInventoryAwardInvariantViolationException $exception) {
            $this->assertSame('target_character_world_mismatch', $exception->reason());
            $this->assertSame('inventory_award_character_id', $exception->field());
        }

        $this->assertSame(['Fackel'], $targetCharacter->fresh()->inventory);
        $this->assertDatabaseCount('character_inventory_logs', 0);
    }

    public function test_scene_inventory_quick_action_service_rejects_non_participant_and_world_mismatch_targets(): void
    {
        [$gm, $campaign, $scene] = $this->campaignContext();

        $outsider = User::factory()->create();
        $outsiderCharacter = Character::factory()->create([
            'user_id' => $outsider->id,
            'world_id' => $campaign->world_id,
            'inventory' => ['Fackel'],
        ]);

        $service = app(SceneInventoryQuickActionService::class);

        try {
            $service->execute(
                campaign: $campaign,
                scene: $scene,
                actorUserId: (int) $gm->id,
                data: [
                    'inventory_action_character_id' => $outsiderCharacter->id,
                    'inventory_action_type' => 'add',
                    'inventory_action_item' => 'Heiltrank',
                    'inventory_action_quantity' => 1,
                    'inventory_action_equipped' => false,
                    'inventory_action_note' => '',
                ],
            );
            $this->fail('Expected SceneInventoryQuickActionInvariantViolationException to be thrown.');
        } catch (SceneInventoryQuickActionInvariantViolationException $exception) {
            $this->assertSame('target_character_not_participant', $exception->reason());
            $this->assertSame('inventory_action_character_id', $exception->field());
        }

        $this->assertSame(['Fackel'], $outsiderCharacter->fresh()->inventory);

        $participant = User::factory()->create();
        CampaignMembership::query()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $participant->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => (int) $gm->id,
            'assigned_at' => now(),
        ]);
        $otherWorld = World::factory()->create();
        $wrongWorldCharacter = Character::factory()->create([
            'user_id' => $participant->id,
            'world_id' => $otherWorld->id,
            'inventory' => ['Seil'],
        ]);

        try {
            $service->execute(
                campaign: $campaign,
                scene: $scene,
                actorUserId: (int) $gm->id,
                data: [
                    'inventory_action_character_id' => $wrongWorldCharacter->id,
                    'inventory_action_type' => 'add',
                    'inventory_action_item' => 'Heiltrank',
                    'inventory_action_quantity' => 1,
                    'inventory_action_equipped' => false,
                    'inventory_action_note' => '',
                ],
            );
            $this->fail('Expected SceneInventoryQuickActionInvariantViolationException to be thrown.');
        } catch (SceneInventoryQuickActionInvariantViolationException $exception) {
            $this->assertSame('target_character_world_mismatch', $exception->reason());
            $this->assertSame('inventory_action_character_id', $exception->field());
        }

        $this->assertSame(['Seil'], $wrongWorldCharacter->fresh()->inventory);
        $this->assertDatabaseCount('character_inventory_logs', 0);
    }

    public function test_scene_inventory_quick_action_service_rejects_invalid_scene_campaign_scope(): void
    {
        [$gm, $campaign, $scene] = $this->campaignContext();
        $otherCampaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
            'world_id' => $campaign->world_id,
        ]);

        $participant = User::factory()->create();
        CampaignMembership::query()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $participant->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => (int) $gm->id,
            'assigned_at' => now(),
        ]);
        $targetCharacter = Character::factory()->create([
            'user_id' => $participant->id,
            'world_id' => $campaign->world_id,
            'inventory' => ['Fackel'],
        ]);

        try {
            app(SceneInventoryQuickActionService::class)->execute(
                campaign: $otherCampaign,
                scene: $scene,
                actorUserId: (int) $gm->id,
                data: [
                    'inventory_action_character_id' => $targetCharacter->id,
                    'inventory_action_type' => 'add',
                    'inventory_action_item' => 'Heiltrank',
                    'inventory_action_quantity' => 1,
                    'inventory_action_equipped' => false,
                    'inventory_action_note' => '',
                ],
            );
            $this->fail('Expected SceneInventoryQuickActionInvariantViolationException to be thrown.');
        } catch (SceneInventoryQuickActionInvariantViolationException $exception) {
            $this->assertSame('scene_campaign_mismatch', $exception->reason());
            $this->assertSame('inventory_action_character_id', $exception->field());
        }

        $this->assertSame(['Fackel'], $targetCharacter->fresh()->inventory);
        $this->assertDatabaseCount('character_inventory_logs', 0);
    }

    /**
     * @return array{0: User, 1: Campaign, 2: Scene}
     */
    private function campaignContext(): array
    {
        $gm = User::factory()->gm()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        return [$gm, $campaign, $scene];
    }
}
