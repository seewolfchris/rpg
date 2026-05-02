<?php

namespace Tests\Feature;

use App\Domain\Post\Exceptions\PostProbeInvariantViolationException;
use App\Domain\Post\PostProbeService;
use App\Domain\Scene\Exceptions\SceneInventoryQuickActionInvariantViolationException;
use App\Domain\Scene\SceneInventoryQuickActionService;
use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\Character;
use App\Models\DiceRoll;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainInvariantHttpMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_post_maps_probe_invariant_exception_to_validation_error(): void
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
        $character = Character::factory()->create([
            'user_id' => $gm->id,
            'world_id' => $campaign->world_id,
        ]);

        $probeService = $this->createMock(PostProbeService::class);
        $probeService->expects($this->once())
            ->method('createForPost')
            ->willThrowException(
                PostProbeInvariantViolationException::targetCharacterNotParticipant(
                    characterId: 9999,
                    targetUserId: 111,
                    campaignId: (int) $campaign->id,
                )
            );
        $this->app->instance(PostProbeService::class, $probeService);

        $response = $this->actingAs($gm)
            ->from(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->post(route('campaigns.scenes.posts.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]), [
                'post_type' => 'ic',
                'content_format' => 'markdown',
                'character_id' => $character->id,
                'content' => str_repeat('Probe-Invariant muss als Eingabefehler auftauchen. ', 2),
                'probe_enabled' => '1',
                'probe_character_id' => $character->id,
                'probe_roll_mode' => DiceRoll::MODE_NORMAL,
                'probe_modifier' => 0,
                'probe_attribute_key' => 'mu',
                'probe_le_delta' => -1,
                'probe_ae_delta' => 0,
                'probe_explanation' => 'forcierter invariant fail',
            ]);

        $response->assertRedirect(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]));
        $response->assertSessionHasErrors('probe_character_id');
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_inventory_quick_action_maps_invariant_exception_to_validation_error(): void
    {
        $gm = User::factory()->gm()->create();
        $player = User::factory()->create();
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
        CampaignMembership::query()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $player->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => (int) $gm->id,
            'assigned_at' => now(),
        ]);
        $targetCharacter = Character::factory()->create([
            'user_id' => $player->id,
            'world_id' => $campaign->world_id,
            'inventory' => ['Fackel'],
        ]);

        $service = $this->createMock(SceneInventoryQuickActionService::class);
        $service->expects($this->once())
            ->method('execute')
            ->willThrowException(
                SceneInventoryQuickActionInvariantViolationException::targetCharacterWorldMismatch(
                    characterId: (int) $targetCharacter->id,
                    characterWorldId: (int) $campaign->world_id + 1,
                    campaignWorldId: (int) $campaign->world_id,
                )
            );
        $this->app->instance(SceneInventoryQuickActionService::class, $service);

        $response = $this->actingAs($gm)
            ->from(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->post(route('campaigns.scenes.inventory-quick-action', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]), [
                'inventory_action_character_id' => $targetCharacter->id,
                'inventory_action_type' => 'add',
                'inventory_action_item' => 'Heiltrank',
                'inventory_action_quantity' => 1,
                'inventory_action_equipped' => false,
            ]);

        $response->assertRedirect(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]).'#inventory-quick-action');
        $response->assertSessionHasErrors('inventory_action_character_id');
        $this->assertSame(['Fackel'], $targetCharacter->fresh()->inventory);
    }
}
