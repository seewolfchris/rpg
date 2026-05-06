<?php

declare(strict_types=1);

namespace App\Domain\Magic;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Domain\Magic\Data\MagicActor;
use App\Domain\Magic\Data\MagicTarget;
use App\Domain\Magic\Exceptions\MagicInvariantViolationException;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\Scene;
use Illuminate\Support\Collection;

/**
 * @phpstan-type MagicEntityContext array{
 *     type: string,
 *     character_id: int|null,
 *     character: Character|null,
 *     name: string,
 *     snapshot: array<string, mixed>
 * }
 */
class MagicParticipantResolver
{
    public function __construct(
        private readonly CampaignParticipantResolver $campaignParticipantResolver,
        private readonly MagicSnapshotBuilder $snapshotBuilder,
    ) {}

    /**
     * @return Collection<int, int<1, max>>
     */
    public function participantUserIds(Campaign $campaign): Collection
    {
        return $this->campaignParticipantResolver->participantUserIds($campaign);
    }

    /**
     * @throws MagicInvariantViolationException
     */
    public function assertSceneCampaignScope(Campaign $campaign, Scene $scene): void
    {
        if ((int) $scene->campaign_id !== (int) $campaign->id) {
            throw MagicInvariantViolationException::sceneCampaignMismatch(
                sceneCampaignId: (int) $scene->campaign_id,
                campaignId: (int) $campaign->id,
            );
        }
    }

    /**
     * @param  Collection<int, int<1, max>>  $participantUserIds
     * @return MagicEntityContext
     *
     * @throws MagicInvariantViolationException
     */
    public function resolveActorContext(MagicActor $actor, Campaign $campaign, Collection $participantUserIds): array
    {
        if (! in_array($actor->type, [MagicActor::TYPE_CHARACTER, MagicActor::TYPE_NPC], true)) {
            throw MagicInvariantViolationException::actorTypeInvalid($actor->type);
        }

        if ($actor->isCharacter()) {
            if (! $actor->character instanceof Character || (int) $actor->character->id <= 0) {
                throw MagicInvariantViolationException::actorCharacterMissing();
            }

            $this->assertCharacterCampaignContext(
                character: $actor->character,
                campaign: $campaign,
                participantUserIds: $participantUserIds,
                isActor: true,
            );

            $name = $actor->resolvedName();
            $snapshot = $this->snapshotBuilder->buildCharacterSnapshot($actor->character, $actor->snapshot);

            return [
                'type' => MagicActor::TYPE_CHARACTER,
                'character_id' => (int) $actor->character->id,
                'character' => $actor->character,
                'name' => $name,
                'snapshot' => $snapshot,
            ];
        }

        $name = $actor->resolvedName();
        if ($name === '') {
            throw MagicInvariantViolationException::actorNpcNameMissing();
        }

        $snapshot = $this->snapshotBuilder->buildNpcSnapshot($name, $actor->snapshot);

        return [
            'type' => MagicActor::TYPE_NPC,
            'character_id' => null,
            'character' => null,
            'name' => $name,
            'snapshot' => $snapshot,
        ];
    }

    /**
     * @param  Collection<int, int<1, max>>  $participantUserIds
     * @return MagicEntityContext
     *
     * @throws MagicInvariantViolationException
     */
    public function resolveTargetContext(MagicTarget $target, Campaign $campaign, Collection $participantUserIds): array
    {
        if (! in_array($target->type, [MagicTarget::TYPE_CHARACTER, MagicTarget::TYPE_NPC], true)) {
            throw MagicInvariantViolationException::targetTypeInvalid($target->type);
        }

        if ($target->isCharacter()) {
            if (! $target->character instanceof Character || (int) $target->character->id <= 0) {
                throw MagicInvariantViolationException::targetCharacterMissing();
            }

            $this->assertCharacterCampaignContext(
                character: $target->character,
                campaign: $campaign,
                participantUserIds: $participantUserIds,
                isActor: false,
            );

            $name = $target->resolvedName();
            $snapshot = $this->snapshotBuilder->buildCharacterSnapshot($target->character, $target->snapshot);

            return [
                'type' => MagicTarget::TYPE_CHARACTER,
                'character_id' => (int) $target->character->id,
                'character' => $target->character,
                'name' => $name,
                'snapshot' => $snapshot,
            ];
        }

        $name = $target->resolvedName();
        if ($name === '') {
            throw MagicInvariantViolationException::targetNpcNameMissing();
        }

        $snapshot = $this->snapshotBuilder->buildNpcSnapshot($name, $target->snapshot);

        return [
            'type' => MagicTarget::TYPE_NPC,
            'character_id' => null,
            'character' => null,
            'name' => $name,
            'snapshot' => $snapshot,
        ];
    }

    /**
     * @return array<int, Character>
     */
    public function lockCharactersForAction(?Character $actorCharacter, ?Character $targetCharacter): array
    {
        $ids = [];

        if ($actorCharacter instanceof Character) {
            $ids[] = (int) $actorCharacter->id;
        }

        if ($targetCharacter instanceof Character) {
            $ids[] = (int) $targetCharacter->id;
        }

        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        /** @var Collection<int, Character> $characters */
        $characters = Character::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (Character $character): int => (int) $character->id);

        return $characters->all();
    }

    /**
     * @param  MagicEntityContext  $actorContext
     * @param  Collection<int, int<1, max>>  $participantUserIds
     * @param  array<int, Character>  $lockedCharacters
     * @return MagicEntityContext
     *
     * @throws MagicInvariantViolationException
     */
    public function resolveLockedActorContext(
        array $actorContext,
        Campaign $campaign,
        Collection $participantUserIds,
        array $lockedCharacters,
    ): array {
        if ($actorContext['character'] instanceof Character) {
            $characterId = (int) $actorContext['character']->id;
            $lockedActor = $lockedCharacters[$characterId] ?? null;

            if (! $lockedActor instanceof Character) {
                throw MagicInvariantViolationException::actorCharacterNotFound($characterId);
            }

            $this->assertCharacterCampaignContext(
                character: $lockedActor,
                campaign: $campaign,
                participantUserIds: $participantUserIds,
                isActor: true,
            );

            $actorContext['character'] = $lockedActor;
            $actorContext['snapshot'] = $this->snapshotBuilder->buildCharacterSnapshot($lockedActor, $actorContext['snapshot']);
        }

        /** @var MagicEntityContext $actorContext */
        return $actorContext;
    }

    /**
     * @param  MagicEntityContext  $targetContext
     * @param  Collection<int, int<1, max>>  $participantUserIds
     * @param  array<int, Character>  $lockedCharacters
     * @return MagicEntityContext
     *
     * @throws MagicInvariantViolationException
     */
    public function resolveLockedTargetContext(
        array $targetContext,
        Campaign $campaign,
        Collection $participantUserIds,
        array $lockedCharacters,
    ): array {
        if ($targetContext['character'] instanceof Character) {
            $characterId = (int) $targetContext['character']->id;
            $lockedTarget = $lockedCharacters[$characterId] ?? null;

            if (! $lockedTarget instanceof Character) {
                throw MagicInvariantViolationException::targetCharacterNotFound($characterId);
            }

            $this->assertCharacterCampaignContext(
                character: $lockedTarget,
                campaign: $campaign,
                participantUserIds: $participantUserIds,
                isActor: false,
            );

            $targetContext['character'] = $lockedTarget;
            $targetContext['snapshot'] = $this->snapshotBuilder->buildCharacterSnapshot($lockedTarget, $targetContext['snapshot']);
        }

        /** @var MagicEntityContext $targetContext */
        return $targetContext;
    }

    /**
     * @param  Collection<int, int<1, max>>  $participantUserIds
     *
     * @throws MagicInvariantViolationException
     */
    private function assertCharacterCampaignContext(
        Character $character,
        Campaign $campaign,
        Collection $participantUserIds,
        bool $isActor,
    ): void {
        $characterId = (int) $character->id;
        $targetUserId = (int) $character->user_id;
        $campaignId = (int) $campaign->id;
        $campaignWorldId = (int) $campaign->world_id;

        if ($targetUserId < 1 || ! $participantUserIds->contains($targetUserId)) {
            if ($isActor) {
                throw MagicInvariantViolationException::actorCharacterNotParticipant(
                    characterId: $characterId,
                    targetUserId: $targetUserId,
                    campaignId: $campaignId,
                );
            }

            throw MagicInvariantViolationException::targetCharacterNotParticipant(
                characterId: $characterId,
                targetUserId: $targetUserId,
                campaignId: $campaignId,
            );
        }

        if ((int) $character->world_id !== $campaignWorldId) {
            if ($isActor) {
                throw MagicInvariantViolationException::actorCharacterWorldMismatch(
                    characterId: $characterId,
                    characterWorldId: (int) $character->world_id,
                    campaignWorldId: $campaignWorldId,
                );
            }

            throw MagicInvariantViolationException::targetCharacterWorldMismatch(
                characterId: $characterId,
                characterWorldId: (int) $character->world_id,
                campaignWorldId: $campaignWorldId,
            );
        }
    }
}
