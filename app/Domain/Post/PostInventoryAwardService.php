<?php

namespace App\Domain\Post;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Support\CharacterInventoryService;
use App\Support\Observability\StructuredLogger;
use Illuminate\Support\Facades\DB;

class PostInventoryAwardService
{
    public function __construct(
        private readonly CampaignParticipantResolver $campaignParticipantResolver,
        private readonly CharacterInventoryService $inventoryService,
        private readonly StructuredLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{character_id: int, character_name: string, item: string, quantity: int, equipped: bool}|null
     */
    public function applyForPost(
        Post $post,
        array $data,
        Scene $scene,
        bool $isModerator,
        User $user,
    ): ?array {
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

        $participantUserIds = $this->campaignParticipantResolver->participantUserIds($scene->campaign);

        $award = DB::transaction(function () use (
            $post,
            $targetCharacterId,
            $participantUserIds,
            $item,
            $quantity,
            $equipped,
            $user,
            $scene,
        ): ?array {
            $targetCharacter = Character::query()
                ->lockForUpdate()
                ->find($targetCharacterId);

            if (! $targetCharacter) {
                return null;
            }

            if (! $participantUserIds->contains((int) $targetCharacter->user_id)) {
                return null;
            }

            $beforeInventory = $this->inventoryService->normalize($targetCharacter->inventory ?? []);
            $afterInventory = $this->inventoryService->add(
                inventory: $beforeInventory,
                name: $item,
                quantity: $quantity,
                equipped: $equipped,
            );

            $targetCharacter->inventory = $afterInventory;
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
            $post->meta = $meta;
            $post->save();

            return $awardMeta;
        });

        if ($award !== null) {
            $this->logger->info('inventory.post_award_applied', [
                'user_id' => $user->id,
                'scene_id' => $scene->id,
                'post_id' => $post->id,
                'character_id' => $award['character_id'],
                'item' => $award['item'],
                'quantity' => $award['quantity'],
                'equipped' => $award['equipped'],
            ]);
        }

        return $award;
    }
}
