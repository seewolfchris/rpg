<?php

namespace App\Domain\Post\Exceptions;

use App\Domain\Shared\Exceptions\DomainInvariantViolationException;

class PostInventoryAwardInvariantViolationException extends DomainInvariantViolationException
{
    public static function postSceneMismatch(int $postSceneId, int $sceneId): self
    {
        return new self(
            reason: 'post_scene_mismatch',
            field: 'inventory_award_character_id',
            message: 'Inventar-Fund kann nur in der zugehörigen Szene vergeben werden.',
            context: [
                'post_scene_id' => $postSceneId,
                'scene_id' => $sceneId,
            ],
        );
    }

    public static function missingSceneCampaign(int $sceneId): self
    {
        return new self(
            reason: 'scene_campaign_missing',
            field: 'inventory_award_character_id',
            message: 'Inventar-Fund kann nicht verarbeitet werden, weil der Szenen-Kontext unvollständig ist.',
            context: [
                'scene_id' => $sceneId,
            ],
        );
    }

    public static function sceneCampaignMismatch(int $sceneCampaignId, int $campaignId): self
    {
        return new self(
            reason: 'scene_campaign_mismatch',
            field: 'inventory_award_character_id',
            message: 'Inventar-Fund kann nicht verarbeitet werden, weil Szene und Kampagne nicht zusammenpassen.',
            context: [
                'scene_campaign_id' => $sceneCampaignId,
                'campaign_id' => $campaignId,
            ],
        );
    }

    public static function targetCharacterMissing(int $characterId): self
    {
        return new self(
            reason: 'target_character_missing',
            field: 'inventory_award_character_id',
            message: 'Der Ziel-Held für den Inventar-Fund konnte nicht gefunden werden.',
            context: [
                'character_id' => $characterId,
            ],
        );
    }

    public static function targetCharacterNotParticipant(int $characterId, int $targetUserId, int $campaignId): self
    {
        return new self(
            reason: 'target_character_not_participant',
            field: 'inventory_award_character_id',
            message: 'Der Ziel-Held muss ein aktiver Teilnehmer dieser Kampagne sein.',
            context: [
                'character_id' => $characterId,
                'target_user_id' => $targetUserId,
                'campaign_id' => $campaignId,
            ],
        );
    }

    public static function targetCharacterWorldMismatch(int $characterId, int $characterWorldId, int $campaignWorldId): self
    {
        return new self(
            reason: 'target_character_world_mismatch',
            field: 'inventory_award_character_id',
            message: 'Der Ziel-Held gehört nicht zur Welt dieser Kampagne.',
            context: [
                'character_id' => $characterId,
                'character_world_id' => $characterWorldId,
                'campaign_world_id' => $campaignWorldId,
            ],
        );
    }
}
