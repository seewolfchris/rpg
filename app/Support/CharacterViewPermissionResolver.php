<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Campaign\CampaignAccess;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CharacterViewPermissionResolver
{
    public function __construct(
        private readonly CampaignAccess $campaignAccess,
    ) {}

    /**
     * @param  array<int, int|string|null>  $characterIds
     * @return list<int>
     */
    public function resolveViewableIdsForUser(array $characterIds, User $user): array
    {
        $normalizedCharacterIds = $this->normalizeCharacterIds($characterIds);
        if ($normalizedCharacterIds === []) {
            return [];
        }

        if ($user->isAdmin()) {
            return $normalizedCharacterIds;
        }

        $ownedCharacterIds = Character::query()
            ->whereIn('id', $normalizedCharacterIds)
            ->where('user_id', (int) $user->id)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $remainingCharacterIds = array_values(array_diff($normalizedCharacterIds, $ownedCharacterIds));
        if ($remainingCharacterIds === []) {
            return $this->normalizeCharacterIds($ownedCharacterIds);
        }

        $coGmWorldIds = $this->campaignAccess->coGmWorldIds($user);

        $coGmWorldCharacterIds = [];
        if ($coGmWorldIds !== []) {
            $coGmWorldCharacterIds = Character::query()
                ->whereIn('id', $remainingCharacterIds)
                ->whereIn('world_id', $coGmWorldIds)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
        }

        $remainingAfterCoGmIds = array_values(array_diff($remainingCharacterIds, $coGmWorldCharacterIds));
        if ($remainingAfterCoGmIds === []) {
            return $this->normalizeCharacterIds(array_merge($ownedCharacterIds, $coGmWorldCharacterIds));
        }

        $participantCampaignCharacterIds = Character::query()
            ->whereIn('id', $remainingAfterCoGmIds)
            ->whereHas('posts.scene.campaign', function (Builder $campaignQuery) use ($user): void {
                $campaignQuery
                    ->whereColumn('campaigns.world_id', 'characters.world_id');
                $this->applyParticipantCampaignConstraint($campaignQuery, $user);
            })
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        return $this->normalizeCharacterIds(array_merge(
            $ownedCharacterIds,
            $coGmWorldCharacterIds,
            $participantCampaignCharacterIds
        ));
    }

    /**
     * @param  array<int, int|string|null>  $characterIds
     * @return list<int>
     */
    private function normalizeCharacterIds(array $characterIds): array
    {
        $normalized = array_map(static fn (mixed $id): int => (int) $id, $characterIds);
        $normalized = array_filter($normalized, static fn (int $id): bool => $id > 0);
        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }

    /**
     * @param  Builder<Model>  $campaignQuery
     */
    private function applyParticipantCampaignConstraint(Builder $campaignQuery, User $user): void
    {
        $this->campaignAccess->applyParticipantCampaignConstraint($campaignQuery, $user);
    }
}
