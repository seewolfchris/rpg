<?php

declare(strict_types=1);

namespace App\Domain\Post;

use App\Models\Post;
use App\Models\PostSceneNotificationDelivery;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use RuntimeException;

final class ScenePostNotificationDeliveryLedger
{
    private const SENDING_RECLAIM_SECONDS = 300;

    public function claim(Post $post, User $recipient, string $channel): ?PostSceneNotificationDelivery
    {
        /** @var PostSceneNotificationDelivery|null $claimedDelivery */
        $claimedDelivery = DB::transaction(function () use ($post, $recipient, $channel): ?PostSceneNotificationDelivery {
            $delivery = $this->lockExistingDelivery($post, $recipient, $channel);

            if (! $delivery instanceof PostSceneNotificationDelivery) {
                $delivery = $this->createPendingDeliveryWithDuplicateRecovery($post, $recipient, $channel);
            }

            if ((string) $delivery->status === PostSceneNotificationDelivery::STATUS_SENT) {
                return null;
            }

            if (! $this->canClaimDelivery($delivery)) {
                return null;
            }

            $now = now();
            $nowTimestamp = $now->toDateTimeString();

            $delivery->status = PostSceneNotificationDelivery::STATUS_SENDING;
            $delivery->attempt_count = max(0, (int) $delivery->attempt_count) + 1;
            $delivery->last_attempted_at = $nowTimestamp;

            if ($delivery->first_attempted_at === null) {
                $delivery->first_attempted_at = $nowTimestamp;
            }

            $delivery->save();

            return $delivery->fresh();
        }, 3);

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

    private function lockExistingDelivery(Post $post, User $recipient, string $channel): ?PostSceneNotificationDelivery
    {
        /** @var PostSceneNotificationDelivery|null $delivery */
        $delivery = PostSceneNotificationDelivery::query()
            ->where('post_id', (int) $post->id)
            ->where('recipient_user_id', (int) $recipient->id)
            ->where('channel', $channel)
            ->lockForUpdate()
            ->first();

        return $delivery;
    }

    private function createPendingDeliveryWithDuplicateRecovery(Post $post, User $recipient, string $channel): PostSceneNotificationDelivery
    {
        try {
            /** @var PostSceneNotificationDelivery $delivery */
            $delivery = PostSceneNotificationDelivery::query()->create([
                'post_id' => (int) $post->id,
                'recipient_user_id' => (int) $recipient->id,
                'channel' => $channel,
                'status' => PostSceneNotificationDelivery::STATUS_PENDING,
                'attempt_count' => 0,
            ]);

            return $delivery;
        } catch (QueryException $exception) {
            if (! $this->isDuplicateDeliveryKey($exception)) {
                throw $exception;
            }
        }

        $existingDelivery = $this->lockExistingDelivery($post, $recipient, $channel);

        if ($existingDelivery instanceof PostSceneNotificationDelivery) {
            return $existingDelivery;
        }

        throw new RuntimeException('Post scene notification delivery claim failed after duplicate key recovery.');
    }

    private function canClaimDelivery(PostSceneNotificationDelivery $delivery): bool
    {
        $status = (string) $delivery->status;

        if ($status === PostSceneNotificationDelivery::STATUS_PENDING || $status === PostSceneNotificationDelivery::STATUS_FAILED) {
            return true;
        }

        if ($status !== PostSceneNotificationDelivery::STATUS_SENDING) {
            return false;
        }

        $updatedAt = $delivery->updated_at;

        if ($updatedAt === null) {
            return true;
        }

        return $updatedAt->lt(now()->subSeconds(self::SENDING_RECLAIM_SECONDS));
    }

    private function isDuplicateDeliveryKey(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo;
        $driverCode = is_array($errorInfo) && isset($errorInfo[1])
            ? (int) $errorInfo[1]
            : 0;
        $message = strtolower($exception->getMessage());

        if ($driverCode === 1062) {
            return true;
        }

        if (str_contains($message, 'duplicate entry')) {
            return true;
        }

        return str_contains($message, 'unique constraint failed');
    }
}
