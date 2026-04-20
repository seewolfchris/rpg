<?php

declare(strict_types=1);

namespace App\Domain\Post;

use App\Models\Post;
use App\Models\PostSceneNotificationDelivery;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ScenePostNotificationDeliveryLedger
{
    private const SENDING_RECLAIM_SECONDS = 300;

    public function claim(Post $post, User $recipient, string $channel): ?PostSceneNotificationDelivery
    {
        $delivery = PostSceneNotificationDelivery::query()->firstOrCreate([
            'post_id' => (int) $post->id,
            'recipient_user_id' => (int) $recipient->id,
            'channel' => $channel,
        ], [
            'status' => PostSceneNotificationDelivery::STATUS_PENDING,
            'attempt_count' => 0,
        ]);

        if ((string) $delivery->status === PostSceneNotificationDelivery::STATUS_SENT) {
            return null;
        }

        $now = now();
        $staleSendingThreshold = $now->copy()->subSeconds(self::SENDING_RECLAIM_SECONDS);

        $claimed = PostSceneNotificationDelivery::query()
            ->whereKey((int) $delivery->id)
            ->where(function ($query) use ($staleSendingThreshold): void {
                $query->whereIn('status', [
                    PostSceneNotificationDelivery::STATUS_PENDING,
                    PostSceneNotificationDelivery::STATUS_FAILED,
                ])->orWhere(function ($staleQuery) use ($staleSendingThreshold): void {
                    $staleQuery->where('status', PostSceneNotificationDelivery::STATUS_SENDING)
                        ->where('updated_at', '<', $staleSendingThreshold);
                });
            })
            ->update([
                'status' => PostSceneNotificationDelivery::STATUS_SENDING,
                'attempt_count' => DB::raw('attempt_count + 1'),
                'last_attempted_at' => $now,
            ]);

        if ($claimed !== 1) {
            return null;
        }

        PostSceneNotificationDelivery::query()
            ->whereKey((int) $delivery->id)
            ->whereNull('first_attempted_at')
            ->update([
                'first_attempted_at' => $now,
            ]);

        /** @var PostSceneNotificationDelivery $claimedDelivery */
        $claimedDelivery = PostSceneNotificationDelivery::query()->findOrFail((int) $delivery->id);

        return $claimedDelivery;
    }

    public function markSent(PostSceneNotificationDelivery $delivery): void
    {
        PostSceneNotificationDelivery::query()
            ->whereKey((int) $delivery->id)
            ->update([
                'status' => PostSceneNotificationDelivery::STATUS_SENT,
                'sent_at' => now(),
                'last_error' => null,
            ]);
    }

    public function markFailed(PostSceneNotificationDelivery $delivery, string $error): void
    {
        PostSceneNotificationDelivery::query()
            ->whereKey((int) $delivery->id)
            ->update([
                'status' => PostSceneNotificationDelivery::STATUS_FAILED,
                'last_error' => mb_substr(trim($error), 0, 1000),
            ]);
    }
}
