<?php

declare(strict_types=1);

namespace App\Domain\Magic\Exceptions;

use App\Domain\Shared\Exceptions\DomainInvariantViolationException;

class MagicInvariantViolationException extends DomainInvariantViolationException
{
    public static function sceneCampaignMismatch(int $sceneCampaignId, int $campaignId): self
    {
        return new self(
            reason: 'scene_campaign_mismatch',
            field: 'scene',
            message: 'Die Zauberaktion kann nicht verarbeitet werden, weil Szene und Kampagne nicht zusammenpassen.',
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
            message: 'Der Zauberer-Typ ist ungueltig. Erlaubt sind character oder npc.',
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

    public static function actorCharacterNotFound(int $characterId): self
    {
        return new self(
            reason: 'actor_character_not_found',
            field: 'actor',
            message: 'Der Zauberer-Character konnte nicht geladen werden.',
            context: ['character_id' => $characterId],
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
            message: 'Der Zauberer-Character muss aktiver Teilnehmer der Kampagne sein.',
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
            message: 'Der Ziel-Character muss aktiver Teilnehmer der Kampagne sein.',
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
            message: 'Der Zauberer-Character gehoert nicht zur Welt dieser Kampagne.',
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

    public static function insufficientAstralEnergy(int $characterId, int $requestedCost, int $availableCurrent): self
    {
        return new self(
            reason: 'insufficient_astral_energy',
            field: 'ae_cost',
            message: 'Der Zauber kann nicht gewirkt werden, weil nicht genug Astralenergie vorhanden ist.',
            context: [
                'character_id' => $characterId,
                'requested_ae_cost' => $requestedCost,
                'available_ae_current' => $availableCurrent,
            ],
        );
    }

    public static function effectTypeInvalid(string $effectType): self
    {
        return new self(
            reason: 'effect_type_invalid',
            field: 'effect_type',
            message: 'Die Effektart ist ungueltig.',
            context: ['effect_type' => $effectType],
        );
    }

    public static function targetAttributeKeyRequired(string $effectType): self
    {
        return new self(
            reason: 'target_attribute_key_required',
            field: 'target_attribute_key',
            message: 'Fuer den Effekt attribute_delta ist ein Zielattribut erforderlich.',
            context: ['effect_type' => $effectType],
        );
    }

    public static function targetAttributeKeyInvalid(string $attributeKey): self
    {
        return new self(
            reason: 'target_attribute_key_invalid',
            field: 'target_attribute_key',
            message: 'Das angegebene Zielattribut wird nicht unterstuetzt.',
            context: ['target_attribute_key' => $attributeKey],
        );
    }
}
