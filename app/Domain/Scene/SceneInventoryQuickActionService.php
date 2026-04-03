<?php

namespace App\Domain\Scene;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Domain\Scene\Exceptions\SceneInventoryQuickActionInvariantViolationException;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\Scene;
use App\Support\CharacterInventoryService;
use App\Support\Observability\DomainEventLogger;
use Illuminate\Support\Facades\DB;

class SceneInventoryQuickActionService
{
    public function __construct(
        private readonly CharacterInventoryService $inventoryService,
        private readonly DomainEventLogger $logger,
        private readonly CampaignParticipantResolver $campaignParticipantResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     status: string,
     *     message?: string
     * }
     *
     * @throws SceneInventoryQuickActionInvariantViolationException
     */
    public function execute(Campaign $campaign, Scene $scene, int $actorUserId, array $data): array
    {
        if ((int) $scene->campaign_id !== (int) $campaign->id) {
            throw SceneInventoryQuickActionInvariantViolationException::sceneCampaignMismatch(
                sceneCampaignId: (int) $scene->campaign_id,
                campaignId: (int) $campaign->id,
            );
        }

        $characterId = (int) $data['inventory_action_character_id'];
        $actionType = (string) $data['inventory_action_type'];
        $item = trim((string) $data['inventory_action_item']);
        $quantity = max(1, min(999, (int) ($data['inventory_action_quantity'] ?? 1)));
        $equipped = (bool) ($data['inventory_action_equipped'] ?? false);
        $note = trim((string) ($data['inventory_action_note'] ?? ''));
        $participantUserIds = $this->campaignParticipantResolver->participantUserIds($campaign);
        $campaignWorldId = (int) $campaign->world_id;

        $result = DB::transaction(function () use (
            $characterId,
            $actionType,
            $item,
            $quantity,
            $equipped,
            $note,
            $participantUserIds,
            $campaignWorldId,
            $campaign,
            $scene,
            $actorUserId
        ): array {
            $character = Character::query()
                ->lockForUpdate()
                ->find($characterId);

            if (! $character) {
                throw SceneInventoryQuickActionInvariantViolationException::targetCharacterMissing($characterId);
            }

            $targetUserId = (int) $character->user_id;

            if ($targetUserId < 1 || ! $participantUserIds->contains($targetUserId)) {
                throw SceneInventoryQuickActionInvariantViolationException::targetCharacterNotParticipant(
                    characterId: (int) $character->id,
                    targetUserId: $targetUserId,
                    campaignId: (int) $campaign->id,
                );
            }

            if ((int) $character->world_id !== $campaignWorldId) {
                throw SceneInventoryQuickActionInvariantViolationException::targetCharacterWorldMismatch(
                    characterId: (int) $character->id,
                    characterWorldId: (int) $character->world_id,
                    campaignWorldId: $campaignWorldId,
                );
            }

            $beforeInventory = $this->inventoryService->normalize($character->inventory ?? []);
            $afterInventory = $beforeInventory;
            $removedQuantity = 0;
            $removedEquipped = null;

            if ($actionType === 'remove') {
                $removeResult = $this->inventoryService->remove($beforeInventory, $item, $quantity);
                $afterInventory = $removeResult['inventory'];
                $removedQuantity = (int) $removeResult['removed'];
                $removedEquipped = $removeResult['removed_equipped'];

                if ($removedQuantity <= 0) {
                    return [
                        'status' => 'item_not_found',
                    ];
                }
            } else {
                $afterInventory = $this->inventoryService->add(
                    inventory: $beforeInventory,
                    name: $item,
                    quantity: $quantity,
                    equipped: $equipped,
                );
            }

            $character->setAttribute('inventory', $afterInventory);
            $character->save();

            if ($actionType === 'remove') {
                $operations = $this->inventoryService->diff($beforeInventory, $afterInventory);
                if ($operations === []) {
                    $operations = [[
                        'action' => 'remove',
                        'item_name' => $item,
                        'quantity' => $removedQuantity,
                        'equipped' => (bool) ($removedEquipped ?? false),
                    ]];
                }
            } else {
                $operations = $this->inventoryService->diff($beforeInventory, $afterInventory);
            }

            $this->inventoryService->log(
                character: $character,
                actorUserId: $actorUserId,
                source: 'scene_inventory_quick_action',
                operations: $operations,
                note: $note !== '' ? $note : null,
                context: [
                    'campaign_id' => $campaign->id,
                    'scene_id' => $scene->id,
                ],
            );

            return [
                'status' => 'ok',
                'character_name' => (string) $character->name,
                'quantity' => $actionType === 'remove' ? $removedQuantity : $quantity,
                'equipped' => $actionType === 'remove' ? (bool) ($removedEquipped ?? false) : $equipped,
                'note' => $note,
                'item' => $item,
                'action_type' => $actionType,
            ];
        });

        if (($result['status'] ?? '') !== 'ok') {
            return ['status' => (string) ($result['status'] ?? 'unknown_error')];
        }

        $statusLabel = $result['action_type'] === 'remove' ? 'entfernt' : 'hinzugefügt';
        $displayQuantity = max(1, (int) ($result['quantity'] ?? $quantity));
        $equippedLabel = (bool) ($result['equipped'] ?? false) ? ' (ausgerüstet)' : '';
        $statusMessage = 'Inventar-Schnellaktion: '.$displayQuantity.'x '.$result['item'].$equippedLabel.' bei '.$result['character_name'].' '.$statusLabel.'.';

        if (($result['note'] ?? '') !== '') {
            $statusMessage .= ' Notiz: '.$result['note'];
        }

        $this->logger->info('inventory.scene_quick_action_applied', [
            'world_slug' => (string) data_get($campaign, 'world.slug', 'unknown'),
            'user_id' => $actorUserId,
            'scene_id' => $scene->id,
            'character_name' => $result['character_name'],
            'item' => $result['item'],
            'quantity' => $displayQuantity,
            'action' => $result['action_type'],
            'equipped' => (bool) $result['equipped'],
            'outcome' => 'succeeded',
        ]);

        return [
            'status' => 'ok',
            'message' => $statusMessage,
        ];
    }
}
