<?php

namespace App\Domain\Post\Exceptions;

use App\Domain\Shared\Exceptions\DomainInvariantViolationException;

class PostProbeInvariantViolationException extends DomainInvariantViolationException
{
    public static function postSceneMismatch(int $postSceneId, int $sceneId): self
    {
        return new self(
            reason: 'post_scene_mismatch',
            field: 'probe_character_id',
            message: 'Probe kann nur in der zugehörigen Szene ausgeführt werden.',
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
            field: 'probe_character_id',
            message: 'Probe kann nicht verarbeitet werden, weil der Szenen-Kontext unvollständig ist.',
            context: [
                'scene_id' => $sceneId,
            ],
        );
    }

    public static function sceneCampaignMismatch(int $sceneCampaignId, int $campaignId): self
    {
        return new self(
            reason: 'scene_campaign_mismatch',
            field: 'probe_character_id',
            message: 'Probe kann nicht verarbeitet werden, weil Szene und Kampagne nicht zusammenpassen.',
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
            field: 'probe_character_id',
            message: 'Der Ziel-Held der Probe konnte nicht gefunden werden.',
            context: [
                'character_id' => $characterId,
            ],
        );
    }

    public static function targetCharacterNotParticipant(int $characterId, int $targetUserId, int $campaignId): self
    {
        return new self(
            reason: 'target_character_not_participant',
            field: 'probe_character_id',
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
            field: 'probe_character_id',
            message: 'Der Ziel-Held gehört nicht zur Welt dieser Kampagne.',
            context: [
                'character_id' => $characterId,
                'character_world_id' => $characterWorldId,
                'campaign_world_id' => $campaignWorldId,
            ],
        );
    }

    public static function previewTokenMissingOrExpired(): self
    {
        return new self(
            reason: 'preview_token_missing_or_expired',
            field: 'probe_roll_token',
            message: 'Die vorab gewürfelte Probe ist ungültig oder abgelaufen. Bitte würfle erneut.',
        );
    }

    /**
     * @param  scalar|null  $expected
     * @param  scalar|null  $actual
     */
    public static function previewTokenScopeMismatch(string $scopeField, string|int|float|bool|null $expected, string|int|float|bool|null $actual): self
    {
        return new self(
            reason: 'preview_token_scope_mismatch',
            field: 'probe_roll_token',
            message: 'Die vorab gewürfelte Probe passt nicht zu den aktuellen Eingaben. Bitte würfle erneut.',
            context: [
                'scope_field' => $scopeField,
                'expected' => $expected,
                'actual' => $actual,
            ],
        );
    }

    public static function previewTokenPayloadInvalid(string $field): self
    {
        return new self(
            reason: 'preview_token_payload_invalid',
            field: 'probe_roll_token',
            message: 'Die vorab gewürfelte Probe ist ungültig. Bitte würfle erneut.',
            context: [
                'payload_field' => $field,
            ],
        );
    }
}
