<?php

declare(strict_types=1);

namespace App\Actions\SceneSubscription;

use App\Models\Campaign;
use App\Models\SceneSubscription;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

final class BulkUpdateSceneSubscriptionsAction
{
    public function execute(
        User $user,
        World $world,
        string $action,
        string $status,
        string $search,
    ): BulkUpdateSceneSubscriptionsResult {
        $filteredQuery = $this->visibleSubscriptionsQuery($user, $world);
        $this->applyFilters($filteredQuery, $status, $search);

        $visibleAllQuery = $this->visibleSubscriptionsQuery($user, $world);

        $affected = match ($action) {
            'mute_filtered' => (clone $filteredQuery)
                ->where('is_muted', false)
                ->update(['is_muted' => true, 'updated_at' => now()]),
            'unmute_filtered' => (clone $filteredQuery)
                ->where('is_muted', true)
                ->update(['is_muted' => false, 'updated_at' => now()]),
            'unfollow_filtered' => (clone $filteredQuery)->delete(),
            'mute_all_active' => (clone $visibleAllQuery)
                ->where('is_muted', false)
                ->update(['is_muted' => true, 'updated_at' => now()]),
            'unmute_all_muted' => (clone $visibleAllQuery)
                ->where('is_muted', true)
                ->update(['is_muted' => false, 'updated_at' => now()]),
            'unfollow_all_muted' => (clone $visibleAllQuery)
                ->where('is_muted', true)
                ->delete(),
            default => throw new InvalidArgumentException('Unsupported scene subscription bulk action: '.$action),
        };

        return new BulkUpdateSceneSubscriptionsResult(
            affected: (int) $affected,
            message: $this->messageForAction($action),
        );
    }

    /**
     * @param  Builder<SceneSubscription>  $query
     */
    private function applyFilters(Builder $query, string $status, string $search): void
    {
        if ($status === 'active') {
            $query->where('is_muted', false);
        } elseif ($status === 'muted') {
            $query->where('is_muted', true);
        }

        if ($search !== '') {
            $searchTerm = '%'.$search.'%';
            $query->where(function (Builder $innerQuery) use ($searchTerm): void {
                $innerQuery->whereHas('scene', function (Builder $sceneQuery) use ($searchTerm): void {
                    $sceneQuery->where('title', 'like', $searchTerm);
                })->orWhereHas('scene.campaign', function (Builder $campaignQuery) use ($searchTerm): void {
                    $campaignQuery->where('title', 'like', $searchTerm);
                });
            });
        }
    }

    /**
     * @return Builder<SceneSubscription>
     */
    private function visibleSubscriptionsQuery(User $user, World $world): Builder
    {
        return SceneSubscription::query()
            ->where('user_id', $user->id)
            ->whereHas('scene.campaign', function (Builder $campaignQuery) use ($user, $world): void {
                $campaignQuery->whereIn('id', Campaign::query()
                    ->visibleTo($user)
                    ->where('world_id', (int) $world->id)
                    ->select('id'));
            });
    }

    private function messageForAction(string $action): string
    {
        return match ($action) {
            'mute_filtered' => 'Gefilterte Abos stummgeschaltet.',
            'unmute_filtered' => 'Gefilterte Abos aktiviert.',
            'unfollow_filtered' => 'Gefilterte Abos entfernt.',
            'mute_all_active' => 'Alle aktiven Abos stummgeschaltet.',
            'unmute_all_muted' => 'Alle stummen Abos aktiviert.',
            'unfollow_all_muted' => 'Alle stummen Abos entfernt.',
            default => 'Bulk-Aktion ausgeführt.',
        };
    }
}
