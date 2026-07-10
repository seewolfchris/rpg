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

        $managedCampaignCharacterIds = Character::query()
            ->whereIn('id', $remainingCharacterIds)
            ->get(['id', 'user_id', 'world_id'])
            ->filter(fn (Character $character): bool => $this->campaignAccess->canManageCharacterThroughCampaign($user, $character))
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $remainingAfterManagedCampaignIds = array_values(array_diff($remainingCharacterIds, $managedCampaignCharacterIds));
        if ($remainingAfterManagedCampaignIds === []) {
            return $this->normalizeCharacterIds(array_merge($ownedCharacterIds, $managedCampaignCharacterIds));
        }

        $participantCampaignCharacterIds = Character::query()
            ->whereIn('id', $remainingAfterManagedCampaignIds)
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
            $managedCampaignCharacterIds,
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
