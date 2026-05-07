<?php

declare(strict_types=1);

namespace App\Domain\SceneConflict;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Domain\SceneConflict\Exceptions\SceneConflictActorInvariantViolationException;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\Scene;
use App\Models\SceneConflictActor;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SceneConflictActorService
{
    public function __construct(
        private readonly CampaignParticipantResolver $campaignParticipantResolver,
    ) {}

    /**
     * @throws SceneConflictActorInvariantViolationException
     */
    public function addCharacterActor(
        Campaign $campaign,
        Scene $scene,
        Character $character,
        ?int $sortOrder = null,
    ): SceneConflictActor {
        $this->assertSceneCampaignScope($campaign, $scene);
        $participantUserIds = $this->campaignParticipantResolver->participantUserIds($campaign);
        $this->assertCharacterContext($campaign, $character, $participantUserIds);

        try {
            return DB::transaction(function () use ($campaign, $scene, $character, $sortOrder): SceneConflictActor {
                Scene::query()
                    ->whereKey((int) $scene->id)
                    ->lockForUpdate()
                    ->first();

                $alreadyExists = SceneConflictActor::query()
                    ->where('scene_id', (int) $scene->id)
                    ->where('character_id', (int) $character->id)
                    ->exists();

                if ($alreadyExists) {
                    throw SceneConflictActorInvariantViolationException::duplicateCharacterActor(
                        sceneId: (int) $scene->id,
                        characterId: (int) $character->id,
                    );
                }

                return SceneConflictActor::query()->create([
                    'campaign_id' => (int) $campaign->id,
                    'scene_id' => (int) $scene->id,
                    'actor_type' => SceneConflictActor::TYPE_CHARACTER,
                    'character_id' => (int) $character->id,
                    'name' => trim((string) $character->name),
                    'le_current' => $character->le_current !== null ? (int) $character->le_current : null,
                    'le_max' => $character->le_max !== null ? (int) $character->le_max : null,
                    'ae_current' => $character->ae_current !== null ? (int) $character->ae_current : null,
                    'ae_max' => $character->ae_max !== null ? (int) $character->ae_max : null,
                    'attack_value' => null,
                    'defense_value' => null,
                    'armor_protection' => $character->armorProtectionValue(),
                    'damage_value' => null,
                    'spell_value' => null,
                    'notes' => null,
                    'sort_order' => $this->resolveSortOrder($scene, $sortOrder),
                ]);
            });
        } catch (QueryException $exception) {
            if ($this->isSceneCharacterDuplicateQuery($exception)) {
                throw SceneConflictActorInvariantViolationException::duplicateCharacterActor(
                    sceneId: (int) $scene->id,
                    characterId: (int) $character->id,
                );
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws SceneConflictActorInvariantViolationException
     */
    public function addNpcActor(Campaign $campaign, Scene $scene, array $payload): SceneConflictActor
    {
        $this->assertSceneCampaignScope($campaign, $scene);
        $normalized = $this->normalizeNpcPayload(
            payload: $payload,
            isCreate: true,
            existing: null,
        );

        return DB::transaction(function () use ($campaign, $scene, $normalized): SceneConflictActor {
            Scene::query()
                ->whereKey((int) $scene->id)
                ->lockForUpdate()
                ->first();

            return SceneConflictActor::query()->create([
                'campaign_id' => (int) $campaign->id,
                'scene_id' => (int) $scene->id,
                'actor_type' => SceneConflictActor::TYPE_NPC,
                'character_id' => null,
                'name' => $normalized['name'],
                'le_current' => $normalized['le_current'],
                'le_max' => $normalized['le_max'],
                'ae_current' => $normalized['ae_current'],
                'ae_max' => $normalized['ae_max'],
                'attack_value' => $normalized['attack_value'],
                'defense_value' => $normalized['defense_value'],
                'armor_protection' => $normalized['armor_protection'],
                'damage_value' => $normalized['damage_value'],
                'spell_value' => $normalized['spell_value'],
                'notes' => $normalized['notes'],
                'sort_order' => $this->resolveSortOrder($scene, $normalized['sort_order']),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws SceneConflictActorInvariantViolationException
     */
    public function updateNpcActor(SceneConflictActor $actor, array $payload): SceneConflictActor
    {
        if (! $actor->isNpc()) {
            throw SceneConflictActorInvariantViolationException::npcUpdateOnly($actor);
        }

        $normalized = $this->normalizeNpcPayload(
            payload: $payload,
            isCreate: false,
            existing: $actor,
        );

        $actor->fill([
            'name' => $normalized['name'],
            'le_current' => $normalized['le_current'],
            'le_max' => $normalized['le_max'],
            'ae_current' => $normalized['ae_current'],
            'ae_max' => $normalized['ae_max'],
            'attack_value' => $normalized['attack_value'],
            'defense_value' => $normalized['defense_value'],
            'armor_protection' => $normalized['armor_protection'],
            'damage_value' => $normalized['damage_value'],
            'spell_value' => $normalized['spell_value'],
            'notes' => $normalized['notes'],
            'sort_order' => $normalized['sort_order'],
        ]);
        $actor->save();

        return $actor->refresh();
    }

    public function removeActor(SceneConflictActor $actor): void
    {
        $actor->delete();
    }

    /**
     * @return Collection<int, SceneConflictActor>
     */
    public function listForScene(Scene $scene): Collection
    {
        /** @var Collection<int, SceneConflictActor> $actors */
        $actors = SceneConflictActor::query()
            ->where('scene_id', (int) $scene->id)
            ->orderByRaw('sort_order IS NULL')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->with('character:id,name,le_current,le_max,ae_current,ae_max')
            ->get();

        return $actors;
    }

    /**
     * @param  Collection<int, int<1, max>>  $participantUserIds
     *
     * @throws SceneConflictActorInvariantViolationException
     */
    private function assertCharacterContext(Campaign $campaign, Character $character, Collection $participantUserIds): void
    {
        if ((int) $character->world_id !== (int) $campaign->world_id) {
            throw SceneConflictActorInvariantViolationException::characterWorldMismatch(
                characterId: (int) $character->id,
                characterWorldId: (int) $character->world_id,
                campaignWorldId: (int) $campaign->world_id,
            );
        }

        $targetUserId = (int) $character->user_id;
        if ($targetUserId <= 0 || ! $participantUserIds->contains($targetUserId)) {
            throw SceneConflictActorInvariantViolationException::characterNotParticipant(
                characterId: (int) $character->id,
                targetUserId: $targetUserId,
                campaignId: (int) $campaign->id,
            );
        }
    }

    /**
     * @throws SceneConflictActorInvariantViolationException
     */
    private function assertSceneCampaignScope(Campaign $campaign, Scene $scene): void
    {
        if ((int) $scene->campaign_id !== (int) $campaign->id) {
            throw SceneConflictActorInvariantViolationException::sceneCampaignMismatch(
                sceneCampaignId: (int) $scene->campaign_id,
                campaignId: (int) $campaign->id,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     name: string,
     *     le_current: int|null,
     *     le_max: int|null,
     *     ae_current: int|null,
     *     ae_max: int|null,
     *     attack_value: int|null,
     *     defense_value: int|null,
     *     armor_protection: int|null,
     *     damage_value: int|null,
     *     spell_value: int|null,
     *     notes: string|null,
     *     sort_order: int|null
     * }
     *
     * @throws SceneConflictActorInvariantViolationException
     */
    private function normalizeNpcPayload(array $payload, bool $isCreate, ?SceneConflictActor $existing): array
    {
        $nameProvided = array_key_exists('name', $payload);
        $trimmedName = is_string($payload['name'] ?? null) ? trim((string) $payload['name']) : '';

        if ($isCreate && $trimmedName === '') {
            throw SceneConflictActorInvariantViolationException::npcNameRequired();
        }

        if (! $isCreate && $nameProvided && $trimmedName === '') {
            throw SceneConflictActorInvariantViolationException::npcNameRequired();
        }

        $existingName = $existing instanceof SceneConflictActor
            ? trim((string) $existing->name)
            : '';
        $existingLeCurrent = $existing instanceof SceneConflictActor ? $existing->le_current : null;
        $existingLeMax = $existing instanceof SceneConflictActor ? $existing->le_max : null;
        $existingAeCurrent = $existing instanceof SceneConflictActor ? $existing->ae_current : null;
        $existingAeMax = $existing instanceof SceneConflictActor ? $existing->ae_max : null;
        $existingAttack = $existing instanceof SceneConflictActor ? $existing->attack_value : null;
        $existingDefense = $existing instanceof SceneConflictActor ? $existing->defense_value : null;
        $existingArmor = $existing instanceof SceneConflictActor ? $existing->armor_protection : null;
        $existingDamage = $existing instanceof SceneConflictActor ? $existing->damage_value : null;
        $existingSpell = $existing instanceof SceneConflictActor ? $existing->spell_value : null;
        $existingSortOrder = $existing instanceof SceneConflictActor ? $existing->sort_order : null;
        $existingNotes = $existing instanceof SceneConflictActor ? $existing->notes : null;

        $name = $isCreate
            ? $trimmedName
            : ($nameProvided ? $trimmedName : $existingName);

        $leCurrent = $this->intField($payload, 'le_current', 0, 999, $isCreate ? null : $existingLeCurrent);
        $leMax = $this->intField($payload, 'le_max', 0, 999, $isCreate ? null : $existingLeMax);
        $aeCurrent = $this->intField($payload, 'ae_current', 0, 999, $isCreate ? null : $existingAeCurrent);
        $aeMax = $this->intField($payload, 'ae_max', 0, 999, $isCreate ? null : $existingAeMax);
        $attackValue = $this->intField($payload, 'attack_value', 0, 100, $isCreate ? null : $existingAttack);
        $defenseValue = $this->intField($payload, 'defense_value', 0, 100, $isCreate ? null : $existingDefense);
        $armorProtection = $this->intField($payload, 'armor_protection', 0, 999, $isCreate ? null : $existingArmor);
        $damageValue = $this->intField($payload, 'damage_value', 0, 999, $isCreate ? null : $existingDamage);
        $spellValue = $this->intField($payload, 'spell_value', 0, 100, $isCreate ? null : $existingSpell);
        $sortOrder = $this->intField($payload, 'sort_order', 1, 1000000, $isCreate ? null : $existingSortOrder);
        $notes = $this->nullableTrimmedString(
            $payload,
            'notes',
            $isCreate ? null : $existingNotes,
        );

        if ($leCurrent !== null && $leMax !== null && $leCurrent > $leMax) {
            $leCurrent = $leMax;
        }
        if ($aeCurrent !== null && $aeMax !== null && $aeCurrent > $aeMax) {
            $aeCurrent = $aeMax;
        }

        return [
            'name' => $name,
            'le_current' => $leCurrent,
            'le_max' => $leMax,
            'ae_current' => $aeCurrent,
            'ae_max' => $aeMax,
            'attack_value' => $attackValue,
            'defense_value' => $defenseValue,
            'armor_protection' => $armorProtection,
            'damage_value' => $damageValue,
            'spell_value' => $spellValue,
            'notes' => $notes,
            'sort_order' => $sortOrder,
        ];
    }

    private function resolveSortOrder(Scene $scene, ?int $requestedSortOrder): int
    {
        if ($requestedSortOrder !== null && $requestedSortOrder > 0) {
            return $requestedSortOrder;
        }

        $maxSort = SceneConflictActor::query()
            ->where('scene_id', (int) $scene->id)
            ->max('sort_order');

        $resolved = is_int($maxSort) ? $maxSort + 1 : 1;

        return max(1, $resolved);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function intField(array $payload, string $key, int $min, int $max, ?int $fallback): ?int
    {
        if (! array_key_exists($key, $payload)) {
            return $fallback;
        }

        $value = $payload[$key];
        if ($value === null || $value === '') {
            return null;
        }

        return max($min, min($max, (int) $value));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function nullableTrimmedString(array $payload, string $key, ?string $fallback): ?string
    {
        if (! array_key_exists($key, $payload)) {
            return $fallback;
        }

        if (! is_string($payload[$key])) {
            return null;
        }

        $trimmed = trim((string) $payload[$key]);

        return $trimmed === '' ? null : $trimmed;
    }

    private function isSceneCharacterDuplicateQuery(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'scene_conflict_actors_scene_character_unique');
    }
}
