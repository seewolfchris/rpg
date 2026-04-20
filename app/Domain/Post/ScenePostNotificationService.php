<?php

namespace App\Domain\Post;

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Post;
use App\Models\PostSceneNotificationDelivery;
use App\Models\PushSubscription;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;
use App\Notifications\SceneNewPostNotification;
use App\Notifications\SceneNewPostWebPush;
use App\Support\Observability\DomainEventLogger;
use Illuminate\Support\Facades\Notification;
use Throwable;

class ScenePostNotificationService
{
    private const DELIVERY_SENT = 'sent';

    private const DELIVERY_SKIPPED = 'skipped';

    private const DELIVERY_FAILED = 'failed';

    public function __construct(
        private readonly DomainEventLogger $logger,
        private readonly ScenePostNotificationDeliveryLedger $deliveryLedger,
    ) {}

    /**
     * @return array{in_app_recipients: int, webpush_recipients: int, has_failures: bool}
     */
    public function notifySceneParticipants(Post $post, User $author): array
    {
        $post->loadMissing(['scene.campaign']);
        /** @var Scene $scene */
        $scene = $post->scene;
        /** @var Campaign $campaign */
        $campaign = $scene->campaign;
        $campaign->loadMissing([
            'invitations' => fn ($query) => $query
                ->select(['id', 'campaign_id', 'user_id', 'status', 'role', 'accepted_at', 'responded_at', 'invited_by', 'created_at'])
                ->where('status', CampaignInvitation::STATUS_ACCEPTED),
        ]);

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
                'has_failures' => false,
            ];
        }

        $recipients = User::query()
            ->whereIn('id', $recipientIds)
            ->orderBy('id')
            ->get()
            ->filter(fn (User $recipient): bool => $campaign->isVisibleTo($recipient))
            ->values();

        if ($recipients->isEmpty()) {
            return [
                'in_app_recipients' => 0,
                'webpush_recipients' => 0,
                'has_failures' => false,
            ];
        }

        $worldId = (int) ($post->scene->campaign->world_id ?? 0);
        $webPushRecipientIdLookup = $this->resolveWebPushRecipientIdLookup($recipients, $worldId);
        $inAppRecipientCount = 0;
        $webPushRecipientCount = 0;
        $hasFailures = false;

        foreach ($recipients as $recipient) {
            if ($this->sendInAppNotification($post, $author, $recipient)) {
                $inAppRecipientCount++;
            }

            $webPushDeliveryStatus = $this->sendWebPushNotification(
                $post,
                $author,
                $recipient,
                $campaign,
                $worldId,
                $webPushRecipientIdLookup,
            );

            if ($webPushDeliveryStatus === self::DELIVERY_SENT) {
                $webPushRecipientCount++;
            }

            if ($webPushDeliveryStatus === self::DELIVERY_FAILED) {
                $hasFailures = true;
            }
        }

        return [
            'in_app_recipients' => $inAppRecipientCount,
            'webpush_recipients' => $webPushRecipientCount,
            'has_failures' => $hasFailures,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, User>  $recipients
     * @return array<int, true>
     */
    private function resolveWebPushRecipientIdLookup(\Illuminate\Support\Collection $recipients, int $worldId): array
    {
        $browserRecipientIds = $recipients
            ->filter(static fn (User $recipient): bool => $recipient->wantsNotificationChannel('scene_new_post', 'browser'))
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->all();

        if ($browserRecipientIds === []) {
            return [];
        }

        /** @var array<int, int> $resolvedRecipientIds */
        $resolvedRecipientIds = PushSubscription::query()
            ->forWorld($worldId)
            ->whereIn('user_id', $browserRecipientIds)
            ->distinct()
            ->pluck('user_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $lookup = [];

        foreach ($resolvedRecipientIds as $recipientId) {
            if ($recipientId > 0) {
                $lookup[$recipientId] = true;
            }
        }

        return $lookup;
    }

    private function sendInAppNotification(Post $post, User $author, User $recipient): bool
    {
        $delivery = $this->deliveryLedger->claim(
            post: $post,
            recipient: $recipient,
            channel: PostSceneNotificationDelivery::CHANNEL_DATABASE,
        );

        if (! $delivery instanceof PostSceneNotificationDelivery) {
            return false;
        }

        try {
            Notification::send($recipient, new SceneNewPostNotification(
                post: $post,
                author: $author,
            ));
            $this->deliveryLedger->markSent($delivery);
        } catch (Throwable $throwable) {
            $this->deliveryLedger->markFailed($delivery, $throwable->getMessage());

            throw $throwable;
        }

        return true;
    }

    /**
     * @param  array<int, true>  $webPushRecipientIdLookup
     */
    private function sendWebPushNotification(
        Post $post,
        User $author,
        User $recipient,
        Campaign $campaign,
        int $worldId,
        array $webPushRecipientIdLookup,
    ): string {
        if (! $recipient->wantsNotificationChannel('scene_new_post', 'browser')) {
            return self::DELIVERY_SKIPPED;
        }

        if (! isset($webPushRecipientIdLookup[(int) $recipient->id])) {
            return self::DELIVERY_SKIPPED;
        }

        $delivery = $this->deliveryLedger->claim(
            post: $post,
            recipient: $recipient,
            channel: PostSceneNotificationDelivery::CHANNEL_WEBPUSH,
        );

        if (! $delivery instanceof PostSceneNotificationDelivery) {
            return self::DELIVERY_SKIPPED;
        }

        try {
            Notification::send($recipient, new SceneNewPostWebPush(
                post: $post,
                author: $author,
            ));
            $this->deliveryLedger->markSent($delivery);
            $this->logger->info('webpush.scene_post_sent', [
                'author_id' => (int) $author->id,
                'user_id' => (int) $author->id,
                'recipient_user_id' => (int) $recipient->id,
                'scene_id' => (int) $post->scene_id,
                'post_id' => (int) $post->id,
                'world_id' => $worldId,
                'world_slug' => (string) data_get($campaign, 'world.slug', 'unknown'),
                'recipient_count' => 1,
                'outcome' => 'succeeded',
            ]);

            return self::DELIVERY_SENT;
        } catch (Throwable $exception) {
            $this->deliveryLedger->markFailed($delivery, $exception->getMessage());
            $this->logger->info('webpush.scene_post_failed', [
                'author_id' => (int) $author->id,
                'user_id' => (int) $author->id,
                'recipient_user_id' => (int) $recipient->id,
                'scene_id' => (int) $post->scene_id,
                'post_id' => (int) $post->id,
                'world_id' => $worldId,
                'world_slug' => (string) data_get($campaign, 'world.slug', 'unknown'),
                'recipient_count' => 1,
                'error' => $exception->getMessage(),
                'outcome' => 'failed',
            ]);

            return self::DELIVERY_FAILED;
        }
    }
}
