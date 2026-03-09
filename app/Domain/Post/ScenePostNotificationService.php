<?php

namespace App\Domain\Post;

use App\Models\Post;
use App\Models\PushSubscription;
use App\Models\SceneSubscription;
use App\Models\User;
use App\Notifications\SceneNewPostNotification;
use App\Notifications\SceneNewPostWebPush;
use App\Support\Observability\StructuredLogger;
use Illuminate\Support\Facades\Notification;
use Throwable;

class ScenePostNotificationService
{
    public function __construct(
        private readonly StructuredLogger $logger,
    ) {}

    /**
     * @return array{in_app_recipients: int, webpush_recipients: int}
     */
    public function notifySceneParticipants(Post $post, User $author): array
    {
        $post->loadMissing(['scene.campaign']);

        $recipientIds = SceneSubscription::query()
            ->where('scene_id', $post->scene_id)
            ->where('user_id', '!=', $author->id)
            ->where('is_muted', false)
            ->pluck('user_id')
            ->unique()
            ->values();

        if ($recipientIds->isEmpty()) {
            return [
                'in_app_recipients' => 0,
                'webpush_recipients' => 0,
            ];
        }

        $recipients = User::query()->whereIn('id', $recipientIds)->get();

        if ($recipients->isEmpty()) {
            return [
                'in_app_recipients' => 0,
                'webpush_recipients' => 0,
            ];
        }

        Notification::send($recipients, new SceneNewPostNotification(
            post: $post,
            author: $author,
        ));

        $browserRecipients = $recipients
            ->filter(fn (User $recipient): bool => $recipient->wantsNotificationChannel('scene_new_post', 'browser'))
            ->values();

        if ($browserRecipients->isEmpty()) {
            return [
                'in_app_recipients' => $recipients->count(),
                'webpush_recipients' => 0,
            ];
        }

        $worldId = (int) ($post->scene->campaign->world_id ?? 0);
        $webPushRecipientIds = PushSubscription::query()
            ->forWorld($worldId)
            ->whereIn('user_id', $browserRecipients->pluck('id'))
            ->distinct()
            ->pluck('user_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $webPushRecipients = $browserRecipients
            ->whereIn('id', $webPushRecipientIds)
            ->values();

        if ($webPushRecipients->isEmpty()) {
            return [
                'in_app_recipients' => $recipients->count(),
                'webpush_recipients' => 0,
            ];
        }

        try {
            Notification::send($webPushRecipients, new SceneNewPostWebPush(
                post: $post,
                author: $author,
            ));

            $this->logger->info('webpush.scene_post_sent', [
                'user_id' => $author->id,
                'scene_id' => $post->scene_id,
                'post_id' => $post->id,
                'world_id' => $worldId,
                'recipient_count' => $webPushRecipients->count(),
            ]);
        } catch (Throwable $exception) {
            $this->logger->info('webpush.scene_post_failed', [
                'user_id' => $author->id,
                'scene_id' => $post->scene_id,
                'post_id' => $post->id,
                'world_id' => $worldId,
                'recipient_count' => $webPushRecipients->count(),
                'error' => $exception->getMessage(),
            ]);
        }

        return [
            'in_app_recipients' => $recipients->count(),
            'webpush_recipients' => $webPushRecipients->count(),
        ];
    }
}
