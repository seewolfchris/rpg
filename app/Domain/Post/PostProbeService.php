<?php

namespace App\Domain\Post;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Domain\Post\Contracts\ProbeRollTokenStore;
use App\Domain\Post\Exceptions\PostProbeInvariantViolationException;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Support\Observability\DomainEventLogger;
use App\Support\ProbeRoller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PostProbeService
{
    public function __construct(
        private readonly ProbeRoller $probeRoller,
        private readonly CampaignParticipantResolver $campaignParticipantResolver,
        private readonly DomainEventLogger $logger,
        private readonly ProbeRollTokenStore $probeRollTokenStore,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function previewForComposer(array $data, User $user, Scene $scene, bool $isModerator): array
    {
        if (! $isModerator) {
            throw PostProbeInvariantViolationException::previewTokenPayloadInvalid('moderator');
        }

        $campaign = $this->resolveCampaign($scene);
        $params = $this->extractProbeParameters($data);

        if ($params === null) {
            throw PostProbeInvariantViolationException::previewTokenPayloadInvalid('probe_parameters');
        }

        $participantUserIds = $this->campaignParticipantResolver->participantUserIds($campaign);
        $targetCharacter = $this->resolveTargetCharacter(
            campaign: $campaign,
            targetCharacterId: $params['target_character_id'],
            participantUserIds: $participantUserIds,
            lockForUpdate: false,
        );

        $rolled = $this->normalizeRolledPayload(
            $this->probeRoller->roll($params['roll_mode'], $params['modifier'])
        );

        $probeTargetValue = $this->resolveProbeTargetValue($targetCharacter, $params['attribute_key']);
        $probeIsSuccess = $probeTargetValue !== null
            ? $rolled['total'] <= $probeTargetValue
            : false;

        $createdAt = now()->toIso8601String();
        $tokenPayload = [
            'user_id' => (int) $user->id,
            'campaign_id' => (int) $campaign->id,
            'scene_id' => (int) $scene->id,
            'character_id' => (int) $params['target_character_id'],
            'probe_roll_mode' => (string) $params['roll_mode'],
            'probe_modifier' => (int) $params['modifier'],
            'probe_attribute_key' => (string) $params['attribute_key'],
            'probe_explanation' => (string) $params['explanation'],
            'probe_le_delta' => (int) $params['le_delta'],
            'probe_ae_delta' => (int) $params['ae_delta'],
            'probe_target_value' => $probeTargetValue,
            'rolls' => $rolled['rolls'],
            'kept_roll' => $rolled['kept_roll'],
            'total' => $rolled['total'],
            'probe_is_success' => $probeIsSuccess,
            'is_critical_success' => $rolled['is_critical_success'],
            'is_critical_failure' => $rolled['is_critical_failure'],
            'created_at' => $createdAt,
        ];

        $token = $this->probeRollTokenStore->issue($tokenPayload);

        return [
            ...$tokenPayload,
            'token' => $token,
            'character_name' => (string) $targetCharacter->name,
        ];
    }

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

        $campaign = $this->resolveCampaign($scene);

        $probeEnabled = (bool) ($data['probe_enabled'] ?? false);
        if (! $probeEnabled || ! $isModerator) {
            return false;
        }

        $params = $this->extractProbeParameters($data);

        if ($params === null) {
            return false;
        }

        $probeRollToken = trim((string) ($data['probe_roll_token'] ?? ''));

        if ($probeRollToken === '') {
            throw PostProbeInvariantViolationException::previewTokenMissingOrExpired();
        }

        return $this->createForPostFromPreviewToken(
            post: $post,
            user: $user,
            scene: $scene,
            campaign: $campaign,
            params: $params,
            probeRollToken: $probeRollToken,
        );
    }

    /**
     * @param  array{target_character_id: int, roll_mode: string, modifier: int, attribute_key: string, explanation: string, le_delta: int, ae_delta: int}  $params
     */
    private function createForPostFromPreviewToken(
        Post $post,
        User $user,
        Scene $scene,
        Campaign $campaign,
        array $params,
        string $probeRollToken,
    ): bool {
        /** @var array{result: array{character_id: int, probe_target_value: int|null, probe_success: bool, requested_le_delta: int, applied_le_delta: int, requested_ae_delta: int, applied_ae_delta: int}, rolled: array{roll_mode: string, modifier: int, rolls: list<int>, kept_roll: int, total: int, is_critical_success: bool, is_critical_failure: bool}}|null $consumedResult */
        $consumedResult = $this->probeRollTokenStore->consume($probeRollToken, function (array $tokenPayload) use ($post, $user, $scene, $campaign, $params): array {
            $this->assertTokenScopeAndParameters(
                tokenPayload: $tokenPayload,
                user: $user,
                scene: $scene,
                campaign: $campaign,
                params: $params,
            );

            $rolled = $this->extractRolledPayloadFromToken($tokenPayload);
            $forcedProbeTargetValue = $this->extractNullableTokenInt($tokenPayload, 'probe_target_value', 0, 100);
            $forcedProbeSuccess = $this->extractTokenBool($tokenPayload, 'probe_is_success');

            if ($forcedProbeTargetValue !== null) {
                $computedSuccess = $rolled['total'] <= $forcedProbeTargetValue;

                if ($forcedProbeSuccess !== $computedSuccess) {
                    throw PostProbeInvariantViolationException::previewTokenPayloadInvalid('probe_is_success');
                }
            }

            $result = $this->persistProbeResult(
                post: $post,
                scene: $scene,
                user: $user,
                campaign: $campaign,
                params: $params,
                rolled: $rolled,
                forcedProbeTargetValue: $forcedProbeTargetValue,
                forcedProbeSuccess: $forcedProbeSuccess,
            );

            return [
                'result' => $result,
                'rolled' => $rolled,
            ];
        });

        if (! is_array($consumedResult)) {
            throw PostProbeInvariantViolationException::previewTokenMissingOrExpired();
        }

        $rolled = $consumedResult['rolled'] ?? null;
        $result = $consumedResult['result'] ?? null;

        if (! is_array($rolled) || ! is_array($result)) {
            throw PostProbeInvariantViolationException::previewTokenPayloadInvalid('consume_result');
        }

        /** @var array{character_id: int, probe_target_value: int|null, probe_success: bool, requested_le_delta: int, applied_le_delta: int, requested_ae_delta: int, applied_ae_delta: int} $result */
        /** @var array{roll_mode: string, modifier: int, rolls: list<int>, kept_roll: int, total: int, is_critical_success: bool, is_critical_failure: bool} $rolled */
        $this->logProbeApplied(
            scene: $scene,
            user: $user,
            post: $post,
            result: $result,
            params: $params,
            rolled: $rolled,
        );

        return true;
    }

    /**
     * @param  array<string, mixed>  $tokenPayload
     * @param  array{target_character_id: int, roll_mode: string, modifier: int, attribute_key: string, explanation: string, le_delta: int, ae_delta: int}  $params
     */
    private function assertTokenScopeAndParameters(
        array $tokenPayload,
        User $user,
        Scene $scene,
        Campaign $campaign,
        array $params,
    ): void {
        $this->assertTokenIntEquals($tokenPayload, 'user_id', (int) $user->id);
        $this->assertTokenIntEquals($tokenPayload, 'campaign_id', (int) $campaign->id);
        $this->assertTokenIntEquals($tokenPayload, 'scene_id', (int) $scene->id);
        $this->assertTokenIntEquals($tokenPayload, 'character_id', (int) $params['target_character_id']);
        $this->assertTokenStringEquals($tokenPayload, 'probe_roll_mode', (string) $params['roll_mode']);
        $this->assertTokenIntEquals($tokenPayload, 'probe_modifier', (int) $params['modifier']);
        $this->assertTokenStringEquals($tokenPayload, 'probe_attribute_key', (string) $params['attribute_key']);
        $this->assertTokenIntEquals($tokenPayload, 'probe_le_delta', (int) $params['le_delta']);
        $this->assertTokenIntEquals($tokenPayload, 'probe_ae_delta', (int) $params['ae_delta']);
        $this->assertTokenStringEquals($tokenPayload, 'probe_explanation', (string) $params['explanation']);
    }

    /**
     * @param  array<string, mixed>  $tokenPayload
     */
    private function assertTokenIntEquals(array $tokenPayload, string $field, int $expectedValue): void
    {
        $actualValue = $this->extractTokenInt($tokenPayload, $field);

        if ($actualValue !== $expectedValue) {
            throw PostProbeInvariantViolationException::previewTokenScopeMismatch($field, $expectedValue, $actualValue);
        }
    }

    /**
     * @param  array<string, mixed>  $tokenPayload
     */
    private function assertTokenStringEquals(array $tokenPayload, string $field, string $expectedValue): void
    {
        if (! array_key_exists($field, $tokenPayload) || ! is_string($tokenPayload[$field])) {
            throw PostProbeInvariantViolationException::previewTokenPayloadInvalid($field);
        }

        $actualValue = trim((string) $tokenPayload[$field]);

        if ($actualValue !== $expectedValue) {
            throw PostProbeInvariantViolationException::previewTokenScopeMismatch($field, $expectedValue, $actualValue);
        }
    }

    /**
     * @param  array<string, mixed>  $tokenPayload
     * @return array{roll_mode: string, modifier: int, rolls: list<int>, kept_roll: int, total: int, is_critical_success: bool, is_critical_failure: bool}
     */
    private function extractRolledPayloadFromToken(array $tokenPayload): array
    {
        $rollMode = (string) ($tokenPayload['probe_roll_mode'] ?? '');

        if (! in_array($rollMode, [
            \App\Models\DiceRoll::MODE_NORMAL,
            \App\Models\DiceRoll::MODE_ADVANTAGE,
            \App\Models\DiceRoll::MODE_DISADVANTAGE,
        ], true)) {
            throw PostProbeInvariantViolationException::previewTokenPayloadInvalid('probe_roll_mode');
        }

        $modifier = $this->extractTokenInt($tokenPayload, 'probe_modifier');
        $keptRoll = $this->extractTokenInt($tokenPayload, 'kept_roll', 1, 100);
        $total = $this->extractTokenInt($tokenPayload, 'total', -1000, 1000);

        $rawRolls = $tokenPayload['rolls'] ?? null;
        if (! is_array($rawRolls) || $rawRolls === []) {
            throw PostProbeInvariantViolationException::previewTokenPayloadInvalid('rolls');
        }

        $rolls = [];
        foreach ($rawRolls as $rawRoll) {
            if (! is_int($rawRoll) && ! is_numeric($rawRoll)) {
                throw PostProbeInvariantViolationException::previewTokenPayloadInvalid('rolls');
            }

            $roll = (int) $rawRoll;

            if ($roll < 1 || $roll > 100) {
                throw PostProbeInvariantViolationException::previewTokenPayloadInvalid('rolls');
            }

            $rolls[] = $roll;
        }

        return [
            'roll_mode' => $rollMode,
            'modifier' => $modifier,
            'rolls' => $rolls,
            'kept_roll' => $keptRoll,
            'total' => $total,
            'is_critical_success' => $this->extractTokenBool($tokenPayload, 'is_critical_success'),
            'is_critical_failure' => $this->extractTokenBool($tokenPayload, 'is_critical_failure'),
        ];
    }

    /**
     * @param  array<string, mixed>  $tokenPayload
     */
    private function extractTokenBool(array $tokenPayload, string $field): bool
    {
        if (! array_key_exists($field, $tokenPayload)) {
            throw PostProbeInvariantViolationException::previewTokenPayloadInvalid($field);
        }

        $value = $tokenPayload[$field];

        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === '1') {
            return true;
        }

        if ($value === 0 || $value === '0') {
            return false;
        }

        throw PostProbeInvariantViolationException::previewTokenPayloadInvalid($field);
    }

    /**
     * @param  array<string, mixed>  $tokenPayload
     */
    private function extractTokenInt(array $tokenPayload, string $field, ?int $min = null, ?int $max = null): int
    {
        if (! array_key_exists($field, $tokenPayload)) {
            throw PostProbeInvariantViolationException::previewTokenPayloadInvalid($field);
        }

        $value = $tokenPayload[$field];

        if (! is_int($value) && ! is_numeric($value)) {
            throw PostProbeInvariantViolationException::previewTokenPayloadInvalid($field);
        }

        $intValue = (int) $value;

        if ($min !== null && $intValue < $min) {
            throw PostProbeInvariantViolationException::previewTokenPayloadInvalid($field);
        }

        if ($max !== null && $intValue > $max) {
            throw PostProbeInvariantViolationException::previewTokenPayloadInvalid($field);
        }

        return $intValue;
    }

    /**
     * @param  array<string, mixed>  $tokenPayload
     */
    private function extractNullableTokenInt(array $tokenPayload, string $field, ?int $min = null, ?int $max = null): ?int
    {
        if (! array_key_exists($field, $tokenPayload) || $tokenPayload[$field] === null) {
            return null;
        }

        return $this->extractTokenInt($tokenPayload, $field, $min, $max);
    }

    /**
     * @param  array{target_character_id: int, roll_mode: string, modifier: int, attribute_key: string, explanation: string, le_delta: int, ae_delta: int}  $params
     * @param  array{roll_mode: string, modifier: int, rolls: list<int>, kept_roll: int, total: int, is_critical_success: bool, is_critical_failure: bool}  $rolled
     * @return array{character_id: int, probe_target_value: int|null, probe_success: bool, requested_le_delta: int, applied_le_delta: int, requested_ae_delta: int, applied_ae_delta: int}
     */
    private function persistProbeResult(
        Post $post,
        Scene $scene,
        User $user,
        Campaign $campaign,
        array $params,
        array $rolled,
        ?int $forcedProbeTargetValue,
        ?bool $forcedProbeSuccess,
    ): array {
        $participantUserIds = $this->campaignParticipantResolver->participantUserIds($campaign);

        return DB::transaction(function () use (
            $post,
            $scene,
            $user,
            $campaign,
            $params,
            $rolled,
            $participantUserIds,
            $forcedProbeTargetValue,
            $forcedProbeSuccess,
        ): array {
            $targetCharacter = $this->resolveTargetCharacter(
                campaign: $campaign,
                targetCharacterId: (int) $params['target_character_id'],
                participantUserIds: $participantUserIds,
                lockForUpdate: true,
            );

            $resolvedProbeTargetValue = $forcedProbeTargetValue;
            if ($resolvedProbeTargetValue === null) {
                $resolvedProbeTargetValue = $this->resolveProbeTargetValue($targetCharacter, (string) $params['attribute_key']);
            }

            $probeSucceeded = $forcedProbeSuccess;
            if ($probeSucceeded === null) {
                $probeSucceeded = $resolvedProbeTargetValue !== null
                    ? $rolled['total'] <= $resolvedProbeTargetValue
                    : false;
            }

            $incomingDamage = 0;
            $armorProtection = 0;
            $damageAfterArmor = 0;
            $effectiveLeDelta = (int) $params['le_delta'];

            if ((int) $params['le_delta'] < 0) {
                $incomingDamage = abs((int) $params['le_delta']);
                $armorProtection = max(0, $targetCharacter->armorProtectionValue());
                $damageAfterArmor = max(0, $incomingDamage - $armorProtection);
                $effectiveLeDelta = -$damageAfterArmor;
            }

            [$appliedLeDelta, $resultingLeCurrent] = $this->applyPoolDelta($targetCharacter, 'le', $effectiveLeDelta);
            [$appliedAeDelta, $resultingAeCurrent] = $this->applyPoolDelta($targetCharacter, 'ae', (int) $params['ae_delta']);

            if ($targetCharacter->isDirty(['le_current', 'ae_current'])) {
                $targetCharacter->save();
            }

            $post->diceRoll()->create([
                'scene_id' => $scene->id,
                'user_id' => $user->id,
                'character_id' => $targetCharacter->id,
                'roll_mode' => $rolled['roll_mode'],
                'modifier' => $rolled['modifier'],
                'label' => $params['explanation'],
                'probe_attribute_key' => $params['attribute_key'],
                'probe_target_value' => $resolvedProbeTargetValue,
                'probe_is_success' => $probeSucceeded,
                'rolls' => $rolled['rolls'],
                'kept_roll' => $rolled['kept_roll'],
                'total' => $rolled['total'],
                'applied_le_delta' => $appliedLeDelta,
                'applied_ae_delta' => $appliedAeDelta,
                'resulting_le_current' => $resultingLeCurrent,
                'resulting_ae_current' => $resultingAeCurrent,
                'is_critical_success' => $rolled['is_critical_success'],
                'is_critical_failure' => $rolled['is_critical_failure'],
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
                $post->setAttribute('meta', $meta);
                $post->save();
            }

            return [
                'character_id' => (int) $targetCharacter->id,
                'probe_target_value' => $resolvedProbeTargetValue,
                'probe_success' => (bool) $probeSucceeded,
                'requested_le_delta' => (int) $params['le_delta'],
                'applied_le_delta' => $appliedLeDelta,
                'requested_ae_delta' => (int) $params['ae_delta'],
                'applied_ae_delta' => $appliedAeDelta,
            ];
        });
    }

    /**
     * @param  array{target_character_id: int, roll_mode: string, modifier: int, attribute_key: string, explanation: string, le_delta: int, ae_delta: int}  $params
     * @param  array{roll_mode: string, modifier: int, rolls: list<int>, kept_roll: int, total: int, is_critical_success: bool, is_critical_failure: bool}  $rolled
     * @param  array{character_id: int, probe_target_value: int|null, probe_success: bool, requested_le_delta: int, applied_le_delta: int, requested_ae_delta: int, applied_ae_delta: int}  $result
     */
    private function logProbeApplied(
        Scene $scene,
        User $user,
        Post $post,
        array $result,
        array $params,
        array $rolled,
    ): void {
        $this->logger->info('probe.post_applied', [
            'world_slug' => (string) data_get($scene, 'campaign.world.slug', 'unknown'),
            'actor_user_id' => $user->id,
            'user_id' => $user->id,
            'scene_id' => $scene->id,
            'post_id' => $post->id,
            'character_id' => $result['character_id'],
            'probe_attribute_key' => $params['attribute_key'],
            'probe_total' => $rolled['total'],
            'probe_target_value' => $result['probe_target_value'],
            'probe_success' => $result['probe_success'],
            'requested_le_delta' => $result['requested_le_delta'],
            'applied_le_delta' => $result['applied_le_delta'],
            'requested_ae_delta' => $result['requested_ae_delta'],
            'applied_ae_delta' => $result['applied_ae_delta'],
            'resolution_source' => 'preview_token',
            'outcome' => 'succeeded',
        ]);
    }

    private function resolveCampaign(Scene $scene): Campaign
    {
        /** @var Campaign|null $campaign */
        $campaign = $scene->campaign;
        if (! $campaign instanceof Campaign) {
            throw PostProbeInvariantViolationException::missingSceneCampaign((int) $scene->id);
        }

        if ((int) $scene->campaign_id !== (int) $campaign->id) {
            throw PostProbeInvariantViolationException::sceneCampaignMismatch((int) $scene->campaign_id, (int) $campaign->id);
        }

        return $campaign;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{target_character_id: int, roll_mode: string, modifier: int, attribute_key: string, explanation: string, le_delta: int, ae_delta: int}|null
     */
    private function extractProbeParameters(array $data): ?array
    {
        $explanation = trim((string) ($data['probe_explanation'] ?? ''));
        if ($explanation === '') {
            return null;
        }

        $probeAttributeKey = trim((string) ($data['probe_attribute_key'] ?? ''));
        if ($probeAttributeKey === '') {
            return null;
        }

        $targetCharacterId = (int) ($data['probe_character_id'] ?? 0);
        if ($targetCharacterId <= 0) {
            return null;
        }

        $rollMode = (string) ($data['probe_roll_mode'] ?? 'normal');

        return [
            'target_character_id' => $targetCharacterId,
            'roll_mode' => $rollMode,
            'modifier' => (int) ($data['probe_modifier'] ?? 0),
            'attribute_key' => $probeAttributeKey,
            'explanation' => $explanation,
            'le_delta' => (int) ($data['probe_le_delta'] ?? 0),
            'ae_delta' => (int) ($data['probe_ae_delta'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $rolled
     * @return array{roll_mode: string, modifier: int, rolls: list<int>, kept_roll: int, total: int, is_critical_success: bool, is_critical_failure: bool}
     */
    private function normalizeRolledPayload(array $rolled): array
    {
        $rawRolls = is_array($rolled['rolls'] ?? null) ? $rolled['rolls'] : [];
        $rolls = [];

        foreach ($rawRolls as $roll) {
            if (! is_int($roll) && ! is_numeric($roll)) {
                continue;
            }

            $normalizedRoll = max(1, min(100, (int) $roll));
            $rolls[] = $normalizedRoll;
        }

        if ($rolls === []) {
            $rolls[] = max(1, min(100, (int) ($rolled['kept_roll'] ?? 1)));
        }

        return [
            'roll_mode' => (string) ($rolled['mode'] ?? 'normal'),
            'modifier' => (int) ($rolled['modifier'] ?? 0),
            'rolls' => $rolls,
            'kept_roll' => max(1, min(100, (int) ($rolled['kept_roll'] ?? $rolls[0]))),
            'total' => (int) ($rolled['total'] ?? 0),
            'is_critical_success' => (bool) ($rolled['critical_success'] ?? false),
            'is_critical_failure' => (bool) ($rolled['critical_failure'] ?? false),
        ];
    }

    private function resolveProbeTargetValue(Character $targetCharacter, string $probeAttributeKey): ?int
    {
        $effectiveAttributes = (array) ($targetCharacter->effective_attributes ?? []);

        if (! array_key_exists($probeAttributeKey, $effectiveAttributes)) {
            return null;
        }

        return (int) max(0, min(100, (int) $effectiveAttributes[$probeAttributeKey]));
    }

    /**
     * @param  Collection<int, int<1, max>>  $participantUserIds
     */
    private function resolveTargetCharacter(
        Campaign $campaign,
        int $targetCharacterId,
        Collection $participantUserIds,
        bool $lockForUpdate,
    ): Character {
        $characterQuery = Character::query();

        if ($lockForUpdate) {
            $characterQuery->lockForUpdate();
        }

        $targetCharacter = $characterQuery->find($targetCharacterId);

        if (! $targetCharacter instanceof Character) {
            throw PostProbeInvariantViolationException::targetCharacterMissing($targetCharacterId);
        }

        $targetUserId = (int) $targetCharacter->user_id;

        if ($targetUserId < 1 || ! $participantUserIds->contains($targetUserId)) {
            throw PostProbeInvariantViolationException::targetCharacterNotParticipant(
                characterId: (int) $targetCharacter->id,
                targetUserId: $targetUserId,
                campaignId: (int) $campaign->id,
            );
        }

        if ((int) $targetCharacter->world_id !== (int) $campaign->world_id) {
            throw PostProbeInvariantViolationException::targetCharacterWorldMismatch(
                characterId: (int) $targetCharacter->id,
                characterWorldId: (int) $targetCharacter->world_id,
                campaignWorldId: (int) $campaign->world_id,
            );
        }

        return $targetCharacter;
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
