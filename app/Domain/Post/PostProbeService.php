<?php

namespace App\Domain\Post;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Domain\Post\Exceptions\PostProbeInvariantViolationException;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Support\Observability\StructuredLogger;
use App\Support\ProbeRoller;
use Illuminate\Support\Facades\DB;

class PostProbeService
{
    public function __construct(
        private readonly ProbeRoller $probeRoller,
        private readonly CampaignParticipantResolver $campaignParticipantResolver,
        private readonly StructuredLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws PostProbeInvariantViolationException
     */
    public function createForPost(
        Post $post,
        array $data,
        User $user,
        Scene $scene,
        bool $isModerator,
    ): bool {
        if ((int) $post->scene_id !== (int) $scene->id) {
            throw PostProbeInvariantViolationException::postSceneMismatch((int) $post->scene_id, (int) $scene->id);
        }

        /** @var Campaign|null $campaign */
        $campaign = $scene->campaign;
        if (! $campaign instanceof Campaign) {
            throw PostProbeInvariantViolationException::missingSceneCampaign((int) $scene->id);
        }

        if ((int) $scene->campaign_id !== (int) $campaign->id) {
            throw PostProbeInvariantViolationException::sceneCampaignMismatch((int) $scene->campaign_id, (int) $campaign->id);
        }

        $probeEnabled = (bool) ($data['probe_enabled'] ?? false);
        if (! $probeEnabled || ! $isModerator) {
            return false;
        }

        $explanation = trim((string) ($data['probe_explanation'] ?? ''));
        if ($explanation === '') {
            return false;
        }

        $modifier = (int) ($data['probe_modifier'] ?? 0);
        $rollMode = (string) ($data['probe_roll_mode'] ?? 'normal');
        $probeAttributeKey = (string) ($data['probe_attribute_key'] ?? '');
        if ($probeAttributeKey === '') {
            return false;
        }

        $rolled = $this->probeRoller->roll($rollMode, $modifier);
        $targetCharacterId = (int) ($data['probe_character_id'] ?? 0);
        if ($targetCharacterId <= 0) {
            return false;
        }

        $participantUserIds = $this->campaignParticipantResolver->participantUserIds($campaign);
        $campaignId = (int) $campaign->id;
        $campaignWorldId = (int) $campaign->world_id;
        $requestedLeDelta = (int) ($data['probe_le_delta'] ?? 0);
        $requestedAeDelta = (int) ($data['probe_ae_delta'] ?? 0);

        $result = DB::transaction(function () use (
            $post,
            $scene,
            $user,
            $targetCharacterId,
            $participantUserIds,
            $campaignId,
            $campaignWorldId,
            $probeAttributeKey,
            $rolled,
            $explanation,
            $requestedLeDelta,
            $requestedAeDelta,
        ): array {
            $targetCharacter = Character::query()
                ->lockForUpdate()
                ->find($targetCharacterId);

            if (! $targetCharacter) {
                throw PostProbeInvariantViolationException::targetCharacterMissing($targetCharacterId);
            }

            if (! $participantUserIds->contains((int) $targetCharacter->user_id)) {
                throw PostProbeInvariantViolationException::targetCharacterNotParticipant(
                    characterId: (int) $targetCharacter->id,
                    targetUserId: (int) $targetCharacter->user_id,
                    campaignId: $campaignId,
                );
            }

            if ((int) $targetCharacter->world_id !== $campaignWorldId) {
                throw PostProbeInvariantViolationException::targetCharacterWorldMismatch(
                    characterId: (int) $targetCharacter->id,
                    characterWorldId: (int) $targetCharacter->world_id,
                    campaignWorldId: $campaignWorldId,
                );
            }

            $effectiveAttributes = (array) ($targetCharacter->effective_attributes ?? []);
            $probeTargetValue = array_key_exists($probeAttributeKey, $effectiveAttributes)
                ? (int) max(0, min(100, (int) $effectiveAttributes[$probeAttributeKey]))
                : null;
            $probeSucceeded = $probeTargetValue !== null
                ? (int) $rolled['total'] <= $probeTargetValue
                : false;

            $incomingDamage = 0;
            $armorProtection = 0;
            $damageAfterArmor = 0;
            $effectiveLeDelta = $requestedLeDelta;

            if ($requestedLeDelta < 0) {
                $incomingDamage = abs($requestedLeDelta);
                $armorProtection = max(0, $targetCharacter->armorProtectionValue());
                $damageAfterArmor = max(0, $incomingDamage - $armorProtection);
                $effectiveLeDelta = -$damageAfterArmor;
            }

            [$appliedLeDelta, $resultingLeCurrent] = $this->applyPoolDelta($targetCharacter, 'le', $effectiveLeDelta);
            [$appliedAeDelta, $resultingAeCurrent] = $this->applyPoolDelta($targetCharacter, 'ae', $requestedAeDelta);

            if ($targetCharacter->isDirty(['le_current', 'ae_current'])) {
                $targetCharacter->save();
            }

            $post->diceRoll()->create([
                'scene_id' => $scene->id,
                'user_id' => $user->id,
                'character_id' => $targetCharacter->id,
                'roll_mode' => $rolled['mode'],
                'modifier' => $rolled['modifier'],
                'label' => $explanation,
                'probe_attribute_key' => $probeAttributeKey,
                'probe_target_value' => $probeTargetValue,
                'probe_is_success' => $probeSucceeded,
                'rolls' => $rolled['rolls'],
                'kept_roll' => $rolled['kept_roll'],
                'total' => $rolled['total'],
                'applied_le_delta' => $appliedLeDelta,
                'applied_ae_delta' => $appliedAeDelta,
                'resulting_le_current' => $resultingLeCurrent,
                'resulting_ae_current' => $resultingAeCurrent,
                'is_critical_success' => $rolled['critical_success'],
                'is_critical_failure' => $rolled['critical_failure'],
                'created_at' => now(),
            ]);

            if ($incomingDamage > 0) {
                $meta = is_array($post->meta) ? $post->meta : [];
                $meta['probe_damage'] = [
                    'requested_damage' => $incomingDamage,
                    'armor_rs' => $armorProtection,
                    'effective_damage' => $damageAfterArmor,
                    'effective_le_delta' => $appliedLeDelta,
                ];
                $post->meta = $meta;
                $post->save();
            }

            return [
                'character_id' => (int) $targetCharacter->id,
                'probe_target_value' => $probeTargetValue,
                'probe_success' => (bool) $probeSucceeded,
                'requested_le_delta' => $requestedLeDelta,
                'applied_le_delta' => $appliedLeDelta,
                'requested_ae_delta' => $requestedAeDelta,
                'applied_ae_delta' => $appliedAeDelta,
            ];
        });

        $this->logger->info('probe.post_applied', [
            'user_id' => $user->id,
            'scene_id' => $scene->id,
            'post_id' => $post->id,
            'character_id' => $result['character_id'],
            'probe_attribute_key' => $probeAttributeKey,
            'probe_total' => $rolled['total'],
            'probe_target_value' => $result['probe_target_value'],
            'probe_success' => $result['probe_success'],
            'requested_le_delta' => $result['requested_le_delta'],
            'applied_le_delta' => $result['applied_le_delta'],
            'requested_ae_delta' => $result['requested_ae_delta'],
            'applied_ae_delta' => $result['applied_ae_delta'],
        ]);

        return true;
    }

    /**
     * @return array{0: int, 1: int|null}
     */
    private function applyPoolDelta(Character $character, string $poolPrefix, int $requestedDelta): array
    {
        $maxColumn = $poolPrefix.'_max';
        $currentColumn = $poolPrefix.'_current';

        $rawMax = $character->{$maxColumn};
        $rawCurrent = $character->{$currentColumn};

        if ($rawMax === null && $rawCurrent === null) {
            return [0, null];
        }

        $maxValue = max((int) ($rawMax ?? $rawCurrent ?? 0), 0);
        $currentValue = $this->clampInt((int) ($rawCurrent ?? $maxValue), 0, $maxValue);
        $resultingValue = $this->clampInt($currentValue + $requestedDelta, 0, $maxValue);
        $appliedDelta = $resultingValue - $currentValue;

        if ($rawCurrent !== $resultingValue) {
            $character->{$currentColumn} = $resultingValue;
        }

        return [$appliedDelta, $resultingValue];
    }

    private function clampInt(int $value, int $min, int $max): int
    {
        return max($min, min($value, $max));
    }
}
