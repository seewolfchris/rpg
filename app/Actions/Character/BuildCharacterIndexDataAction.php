<?php

declare(strict_types=1);

namespace App\Actions\Character;

use App\Models\Character;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\Eloquent\Builder;

class BuildCharacterIndexDataAction
{
    public function execute(User $user, string $selectedWorldSlug, string $selectedStatus): CharacterIndexData
    {
        /** @var array<string, mixed> $characterStatuses */
        $characterStatuses = (array) config('characters.statuses', []);
        $normalizedStatus = $this->normalizeStatus($selectedStatus, array_keys($characterStatuses));
        $worlds = World::query()->active()->ordered()->get(['id', 'name', 'slug']);
        $normalizedWorldSlug = trim($selectedWorldSlug);
        $selectedWorld = $worlds->firstWhere('slug', $normalizedWorldSlug !== '' ? $normalizedWorldSlug : World::defaultSlug())
            ?? $worlds->first();
        $selectedWorldId = $selectedWorld instanceof World ? (int) $selectedWorld->id : null;

        $characters = Character::query()
            ->when(
                $selectedWorldId !== null,
                fn (Builder $query): Builder => $query->where('world_id', $selectedWorldId),
            )
            ->when(
                $normalizedStatus !== 'all',
                fn (Builder $query): Builder => $query->where('status', $normalizedStatus),
            )
            ->when(
                ! $user->isAdmin(),
                fn (Builder $query): Builder => $query->where('user_id', (int) $user->id),
            )
            ->with(['user', 'world'])
            ->latest()
            ->paginate(12);

        return new CharacterIndexData(
            characters: $characters,
            worlds: $worlds,
            selectedWorld: $selectedWorld,
            selectedStatus: $normalizedStatus,
            characterStatuses: $characterStatuses,
        );
    }

    /**
     * @param  list<string>  $statusOptions
     */
    private function normalizeStatus(string $selectedStatus, array $statusOptions): string
    {
        if (! in_array($selectedStatus, array_merge(['all'], $statusOptions), true)) {
            return 'all';
        }

        return $selectedStatus;
    }
}
