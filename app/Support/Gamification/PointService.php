<?php

namespace App\Support\Gamification;

use App\Models\PointEvent;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class PointService
{
    private const SOURCE_POST = 'post';

    private const EVENT_APPROVED = 'approved';

    public function syncApprovedPost(Post $post): void
    {
        if ($post->moderation_status === 'approved') {
            $this->grantApprovedPostPoints($post);

            return;
        }

        $this->revokeApprovedPostPoints($post);
    }

    public function revokeApprovedPostPoints(Post $post): void
    {
        DB::transaction(function () use ($post): void {
            $event = $this->eventQuery($post)->lockForUpdate()->first();

            if (! $event) {
                return;
            }

            $pointsToRevoke = (int) $event->points;
            $event->delete();

            /** @var User|null $user */
            $user = User::query()->lockForUpdate()->find($post->user_id);

            if (! $user) {
                return;
            }

            $user->points = max(0, (int) $user->points - $pointsToRevoke);
            $user->save();
        });
    }

    private function grantApprovedPostPoints(Post $post): void
    {
        DB::transaction(function () use ($post): void {
            $event = $this->eventQuery($post)->lockForUpdate()->first();

            if ($event) {
                return;
            }

            $points = (int) config('gamification.post_approved_points', 10);

            try {
                PointEvent::query()->create([
                    'user_id' => $post->user_id,
                    'source_type' => self::SOURCE_POST,
                    'source_id' => $post->id,
                    'event_key' => self::EVENT_APPROVED,
                    'points' => $points,
                    'meta' => [
                        'post_type' => $post->post_type,
                        'scene_id' => $post->scene_id,
                    ],
                    'created_at' => now(),
                ]);
            } catch (QueryException $exception) {
                if ($this->isDuplicatePointEventException($exception)) {
                    return;
                }

                throw $exception;
            }

            User::query()
                ->whereKey($post->user_id)
                ->increment('points', $points);
        });
    }

    private function eventQuery(Post $post)
    {
        return PointEvent::query()
            ->where('user_id', $post->user_id)
            ->where('source_type', self::SOURCE_POST)
            ->where('source_id', $post->id)
            ->where('event_key', self::EVENT_APPROVED);
    }

    private function isDuplicatePointEventException(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        return in_array($sqlState, ['23000', '23505'], true);
    }
}
