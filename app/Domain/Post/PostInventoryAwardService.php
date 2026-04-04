<?php

namespace App\Domain\Post;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Domain\Post\Exceptions\PostInventoryAwardInvariantViolationException;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Support\CharacterInventoryService;
use App\Support\Observability\DomainEventLogger;
use Illuminate\Support\Facades\DB;

class PostInventoryAwardService
{
    public function __construct(
        private readonly CampaignParticipantResolver $campaignParticipantResolver,
        private readonly CharacterInventoryService $inventoryService,
        private readonly DomainEventLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{character_id: int, character_name: string, item: string, quantity: int, equipped: bool}|null
     *
     * @throws PostInventoryAwardInvariantViolationException
     */
    public function applyForPost(
        Post $post,
        array $data,
        Scene $scene,
        bool $isModerator,
        User $user,
    ): ?array {
        if ((int) $post->scene_id !== (int) $scene->id) {
            throw PostInventoryAwardInvariantViolationException::postSceneMismatch((int) $post->scene_id, (int) $scene->id);
        }

        /** @var Campaign|null $campaign */
        $campaign = $scene->campaign;
        if (! $campaign instanceof Campaign) {
            throw PostInventoryAwardInvariantViolationException::missingSceneCampaign((int) $scene->id);
        }

        if ((int) $scene->campaign_id !== (int) $campaign->id) {
            throw PostInventoryAwardInvariantViolationException::sceneCampaignMismatch((int) $scene->campaign_id, (int) $campaign->id);
        }

        $awardEnabled = (bool) ($data['inventory_award_enabled'] ?? false);
        if (! $awardEnabled || ! $isModerator) {
            return null;
        }

        $targetCharacterId = (int) ($data['inventory_award_character_id'] ?? 0);
        $item = trim((string) ($data['inventory_award_item'] ?? ''));
        $quantity = max(1, min(999, (int) ($data['inventory_award_quantity'] ?? 1)));
        $equipped = (bool) ($data['inventory_award_equipped'] ?? false);

        if ($targetCharacterId <= 0 || $item === '') {
            return null;
        }

        $participantUserIds = $this->campaignParticipantResolver->participantUserIds($campaign);
        $campaignId = (int) $campaign->id;
        $campaignWorldId = (int) $campaign->world_id;

        $award = DB::transaction(function () use (
            $post,
            $targetCharacterId,
            $participantUserIds,
            $campaignId,
            $campaignWorldId,
            $item,
            $quantity,
            $equipped,
            $user,
            $scene,
        ): array {
            $targetCharacter = Character::query()
                ->lockForUpdate()
                ->find($targetCharacterId);

            if (! $targetCharacter) {
                throw PostInventoryAwardInvariantViolationException::targetCharacterMissing($targetCharacterId);
            }

            $targetUserId = (int) $targetCharacter->user_id;

            if ($targetUserId < 1 || ! $participantUserIds->contains($targetUserId)) {
                throw PostInventoryAwardInvariantViolationException::targetCharacterNotParticipant(
                    characterId: (int) $targetCharacter->id,
                    targetUserId: $targetUserId,
                    campaignId: $campaignId,
                );
            }

            if ((int) $targetCharacter->world_id !== $campaignWorldId) {
                throw PostInventoryAwardInvariantViolationException::targetCharacterWorldMismatch(
                    characterId: (int) $targetCharacter->id,
                    characterWorldId: (int) $targetCharacter->world_id,
                    campaignWorldId: $campaignWorldId,
                );
            }

            $beforeInventory = $this->inventoryService->normalize($targetCharacter->inventory ?? []);
            $afterInventory = $this->inventoryService->add(
                inventory: $beforeInventory,
                name: $item,
                quantity: $quantity,
                equipped: $equipped,
            );

            $targetCharacter->setAttribute('inventory', $afterInventory);
            $targetCharacter->save();

            $operations = $this->inventoryService->diff($beforeInventory, $afterInventory);
            $this->inventoryService->log(
                character: $targetCharacter,
                actorUserId: $user->id,
                source: 'post_inventory_award',
                operations: $operations,
                context: [
                    'campaign_id' => $scene->campaign_id,
                    'scene_id' => $scene->id,
                    'post_id' => $post->id,
                ],
            );

            $awardMeta = [
                'character_id' => (int) $targetCharacter->id,
                'character_name' => (string) $targetCharacter->name,
                'item' => $item,
                'quantity' => $quantity,
                'equipped' => $equipped,
            ];

            $meta = is_array($post->meta) ? $post->meta : [];
            $meta['inventory_award'] = $awardMeta;
            $post->setAttribute('meta', $meta);
            $post->save();

            return $awardMeta;
        });

        $this->logger->info('inventory.post_award_applied', [
            'world_slug' => (string) data_get($scene, 'campaign.world.slug', 'unknown'),
            'actor_user_id' => $user->id,
            'user_id' => $user->id,
            'scene_id' => $scene->id,
            'post_id' => $post->id,
            'character_id' => $award['character_id'],
            'item' => $award['item'],
            'quantity' => $award['quantity'],
            'equipped' => $award['equipped'],
            'outcome' => 'succeeded',
        ]);

        return $award;
    }
}
