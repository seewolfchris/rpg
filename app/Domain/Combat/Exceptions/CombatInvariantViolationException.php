<?php

declare(strict_types=1);

namespace App\Domain\Combat\Exceptions;

use App\Domain\Shared\Exceptions\DomainInvariantViolationException;

class CombatInvariantViolationException extends DomainInvariantViolationException
{
    public static function sceneCampaignMismatch(int $sceneCampaignId, int $campaignId): self
    {
        return new self(
            reason: 'scene_campaign_mismatch',
            field: 'scene',
            message: 'Kampfaktion kann nicht verarbeitet werden, weil Szene und Kampagne nicht zusammenpassen.',
            context: [
                'scene_campaign_id' => $sceneCampaignId,
                'campaign_id' => $campaignId,
            ],
        );
    }

    public static function actorTypeInvalid(string $type): self
    {
        return new self(
            reason: 'actor_type_invalid',
            field: 'actor',
            message: 'Der Angreifer-Typ ist ungueltig. Erlaubt sind character oder npc.',
            context: ['actor_type' => $type],
        );
    }

    public static function targetTypeInvalid(string $type): self
    {
        return new self(
            reason: 'target_type_invalid',
            field: 'target',
            message: 'Der Ziel-Typ ist ungueltig. Erlaubt sind character oder npc.',
            context: ['target_type' => $type],
        );
    }

    public static function actorCharacterMissing(): self
    {
        return new self(
            reason: 'actor_character_missing',
            field: 'actor',
            message: 'Fuer actor_type=character ist ein gueltiger Character erforderlich.',
        );
    }

    public static function targetCharacterMissing(): self
    {
        return new self(
            reason: 'target_character_missing',
            field: 'target',
            message: 'Fuer target_type=character ist ein gueltiger Character erforderlich.',
        );
    }

    public static function targetCharacterNotFound(int $characterId): self
    {
        return new self(
            reason: 'target_character_not_found',
            field: 'target',
            message: 'Der Ziel-Character konnte nicht geladen werden.',
            context: ['character_id' => $characterId],
        );
    }

    public static function actorNpcNameMissing(): self
    {
        return new self(
            reason: 'actor_npc_name_missing',
            field: 'actor',
            message: 'Fuer actor_type=npc ist ein Name erforderlich.',
        );
    }

    public static function targetNpcNameMissing(): self
    {
        return new self(
            reason: 'target_npc_name_missing',
            field: 'target',
            message: 'Fuer target_type=npc ist ein Name erforderlich.',
        );
    }

    public static function actorCharacterNotParticipant(int $characterId, int $targetUserId, int $campaignId): self
    {
        return new self(
            reason: 'actor_character_not_participant',
            field: 'actor',
            message: 'Der angreifende Character muss ein aktiver Teilnehmer der Kampagne sein.',
            context: [
                'character_id' => $characterId,
                'target_user_id' => $targetUserId,
                'campaign_id' => $campaignId,
            ],
        );
    }

    public static function targetCharacterNotParticipant(int $characterId, int $targetUserId, int $campaignId): self
    {
        return new self(
            reason: 'target_character_not_participant',
            field: 'target',
            message: 'Der Ziel-Character muss ein aktiver Teilnehmer der Kampagne sein.',
            context: [
                'character_id' => $characterId,
                'target_user_id' => $targetUserId,
                'campaign_id' => $campaignId,
            ],
        );
    }

    public static function actorCharacterWorldMismatch(int $characterId, int $characterWorldId, int $campaignWorldId): self
    {
        return new self(
            reason: 'actor_character_world_mismatch',
            field: 'actor',
            message: 'Der angreifende Character gehoert nicht zur Welt dieser Kampagne.',
            context: [
                'character_id' => $characterId,
                'character_world_id' => $characterWorldId,
                'campaign_world_id' => $campaignWorldId,
            ],
        );
    }

    public static function targetCharacterWorldMismatch(int $characterId, int $characterWorldId, int $campaignWorldId): self
    {
        return new self(
            reason: 'target_character_world_mismatch',
            field: 'target',
            message: 'Der Ziel-Character gehoert nicht zur Welt dieser Kampagne.',
            context: [
                'character_id' => $characterId,
                'character_world_id' => $characterWorldId,
                'campaign_world_id' => $campaignWorldId,
            ],
        );
    }
}
