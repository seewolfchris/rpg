<?php

declare(strict_types=1);

namespace App\Domain\SceneConflict\Exceptions;

use App\Domain\Shared\Exceptions\DomainInvariantViolationException;
use App\Models\SceneConflictActor;

class SceneConflictActorInvariantViolationException extends DomainInvariantViolationException
{
    public static function sceneCampaignMismatch(int $sceneCampaignId, int $campaignId): self
    {
        return new self(
            reason: 'scene_campaign_mismatch',
            field: 'scene',
            message: 'Der Beteiligte kann nicht gespeichert werden, weil Szene und Kampagne nicht zusammenpassen.',
            context: [
                'scene_campaign_id' => $sceneCampaignId,
                'campaign_id' => $campaignId,
            ],
        );
    }

    public static function actorNotInScene(int $actorId, int $actorSceneId, int $sceneId): self
    {
        return new self(
            reason: 'actor_not_in_scene',
            field: 'scene_conflict_actor',
            message: 'Der Beteiligte gehört nicht zu dieser Szene.',
            context: [
                'actor_id' => $actorId,
                'actor_scene_id' => $actorSceneId,
                'scene_id' => $sceneId,
            ],
        );
    }

    public static function actorNotInCampaign(int $actorId, int $actorCampaignId, int $campaignId): self
    {
        return new self(
            reason: 'actor_not_in_campaign',
            field: 'scene_conflict_actor',
            message: 'Der Beteiligte gehört nicht zu dieser Kampagne.',
            context: [
                'actor_id' => $actorId,
                'actor_campaign_id' => $actorCampaignId,
                'campaign_id' => $campaignId,
            ],
        );
    }

    public static function characterNotParticipant(int $characterId, int $targetUserId, int $campaignId): self
    {
        return new self(
            reason: 'character_not_participant',
            field: 'character_id',
            message: 'Der Charakter muss ein aktiver Teilnehmer der Kampagne sein.',
            context: [
                'character_id' => $characterId,
                'target_user_id' => $targetUserId,
                'campaign_id' => $campaignId,
            ],
        );
    }

    public static function characterWorldMismatch(int $characterId, int $characterWorldId, int $campaignWorldId): self
    {
        return new self(
            reason: 'character_world_mismatch',
            field: 'character_id',
            message: 'Der Charakter gehört nicht zur Welt dieser Kampagne.',
            context: [
                'character_id' => $characterId,
                'character_world_id' => $characterWorldId,
                'campaign_world_id' => $campaignWorldId,
            ],
        );
    }

    public static function duplicateCharacterActor(int $sceneId, int $characterId): self
    {
        return new self(
            reason: 'duplicate_character_actor',
            field: 'character_id',
            message: 'Dieser Charakter ist in der Szene bereits als Beteiligter vorhanden.',
            context: [
                'scene_id' => $sceneId,
                'character_id' => $characterId,
            ],
        );
    }

    public static function npcNameRequired(): self
    {
        return new self(
            reason: 'npc_name_required',
            field: 'name',
            message: 'Für NPC-Beteiligte ist ein Name erforderlich.',
        );
    }

    public static function npcUpdateOnly(SceneConflictActor $actor): self
    {
        return new self(
            reason: 'npc_update_only',
            field: 'scene_conflict_actor',
            message: 'Nur NPC-Beteiligte können über dieses Formular bearbeitet werden.',
            context: [
                'actor_id' => (int) $actor->id,
                'actor_type' => (string) $actor->actor_type,
            ],
        );
    }
}

