<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Post\ScenePostNotificationService;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\PostSceneNotificationDelivery;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;
use App\Notifications\SceneNewPostNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class PostSceneNotificationIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_scene_notifications_are_not_duplicated_after_repeat_execution(): void
    {
        [$campaign, $scene, $author, $recipient] = $this->seedSingleRecipientContext();

        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'Idempotent delivery baseline.',
            'moderation_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $author->id,
        ]);

        $service = app(ScenePostNotificationService::class);

        $first = $service->notifySceneParticipants($post, $author);
        $second = $service->notifySceneParticipants($post, $author);

        $this->assertSame(1, $first['in_app_recipients']);
        $this->assertSame(0, $second['in_app_recipients']);
        $this->assertSame(1, $this->scenePostNotificationCount($recipient, $post));

        $this->assertDatabaseHas('post_scene_notification_deliveries', [
            'post_id' => $post->id,
            'recipient_user_id' => $recipient->id,
            'channel' => PostSceneNotificationDelivery::CHANNEL_DATABASE,
            'status' => PostSceneNotificationDelivery::STATUS_SENT,
            'attempt_count' => 1,
        ]);
    }

    public function test_retry_after_partial_failure_only_sends_unsent_recipients(): void
    {
        [$campaign, $scene, $author, $firstRecipient, $secondRecipient] = $this->seedTwoRecipientContext();

        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'Partial failure dedupe scenario.',
            'moderation_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $author->id,
        ]);

        $failSecondRecipientOnce = true;
        Event::listen(NotificationSending::class, function (NotificationSending $event) use ($secondRecipient, &$failSecondRecipientOnce): void {
            if (! $failSecondRecipientOnce) {
                return;
            }

            if (! $event->notification instanceof SceneNewPostNotification) {
                return;
            }

            if ($event->channel !== 'database') {
                return;
            }

            if (! $event->notifiable instanceof User) {
                return;
            }

            if ((int) $event->notifiable->id !== (int) $secondRecipient->id) {
                return;
            }

            $failSecondRecipientOnce = false;

            throw new RuntimeException('Forced scene notification failure for recipient 2');
        });

        $service = app(ScenePostNotificationService::class);

        try {
            $service->notifySceneParticipants($post, $author);
            $this->fail('Expected forced partial scene notification failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced scene notification failure for recipient 2', $exception->getMessage());
        }

        $this->assertSame(1, $this->scenePostNotificationCount($firstRecipient, $post));
        $this->assertSame(0, $this->scenePostNotificationCount($secondRecipient, $post));
        $this->assertDatabaseHas('post_scene_notification_deliveries', [
            'post_id' => $post->id,
            'recipient_user_id' => $firstRecipient->id,
            'channel' => PostSceneNotificationDelivery::CHANNEL_DATABASE,
            'status' => PostSceneNotificationDelivery::STATUS_SENT,
        ]);
        $this->assertDatabaseHas('post_scene_notification_deliveries', [
            'post_id' => $post->id,
            'recipient_user_id' => $secondRecipient->id,
            'channel' => PostSceneNotificationDelivery::CHANNEL_DATABASE,
            'status' => PostSceneNotificationDelivery::STATUS_FAILED,
        ]);

        $retryResult = $service->notifySceneParticipants($post, $author);

        $this->assertSame(1, $retryResult['in_app_recipients']);
        $this->assertSame(1, $this->scenePostNotificationCount($firstRecipient, $post));
        $this->assertSame(1, $this->scenePostNotificationCount($secondRecipient, $post));
        $this->assertDatabaseHas('post_scene_notification_deliveries', [
            'post_id' => $post->id,
            'recipient_user_id' => $secondRecipient->id,
            'channel' => PostSceneNotificationDelivery::CHANNEL_DATABASE,
            'status' => PostSceneNotificationDelivery::STATUS_SENT,
            'attempt_count' => 2,
        ]);
    }

    /**
     * @return array{0: Campaign, 1: Scene, 2: User, 3: User}
     */
    private function seedSingleRecipientContext(): array
    {
        $owner = User::factory()->gm()->create();
        $author = User::factory()->create();
        $recipient = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $recipient->id,
            'is_muted' => false,
        ]);

        return [$campaign, $scene, $author, $recipient];
    }

    /**
     * @return array{0: Campaign, 1: Scene, 2: User, 3: User, 4: User}
     */
    private function seedTwoRecipientContext(): array
    {
        $owner = User::factory()->gm()->create();
        $author = User::factory()->create();
        $firstRecipient = User::factory()->create();
        $secondRecipient = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $firstRecipient->id,
            'is_muted' => false,
        ]);
        SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $secondRecipient->id,
            'is_muted' => false,
        ]);

        return [$campaign, $scene, $author, $firstRecipient, $secondRecipient];
    }

    private function scenePostNotificationCount(User $recipient, Post $post): int
    {
        return $recipient->fresh()
            ->notifications
            ->filter(static function ($notification) use ($post): bool {
                return (string) ($notification->type ?? '') === SceneNewPostNotification::class
                    && (string) ($notification->data['kind'] ?? '') === 'scene_new_post'
                    && (int) ($notification->data['post_id'] ?? 0) === (int) $post->id;
            })
            ->count();
    }
}

