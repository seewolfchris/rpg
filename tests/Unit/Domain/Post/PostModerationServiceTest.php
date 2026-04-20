<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Post;

use App\Domain\Post\PostModerationNotificationDispatcher;
use App\Domain\Post\PostModerationService;
use App\Jobs\Post\SendPostModerationStatusNotificationJob;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Support\Gamification\PointService;
use App\Support\Observability\DomainEventLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class PostModerationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_post_moderation_notification_after_status_change(): void
    {
        Queue::fake();

        [$gm, , $post] = $this->seedModerationContext();
        $post->moderation_status = 'approved';
        $post->approved_at = now();
        $post->approved_by = $gm->id;
        $post->save();

        app(PostModerationService::class)->synchronize(
            post: $post,
            moderator: $gm,
            previousStatus: 'pending',
            moderationNote: 'Freigabe nach Pruefung.',
        );

        $this->assertDatabaseHas('post_moderation_logs', [
            'post_id' => $post->id,
            'moderator_id' => $gm->id,
            'previous_status' => 'pending',
            'new_status' => 'approved',
            'reason' => 'Freigabe nach Pruefung.',
        ]);
        Queue::assertPushed(SendPostModerationStatusNotificationJob::class, 1);
    }

    public function test_it_does_not_break_moderation_write_when_notification_dispatcher_throws(): void
    {
        [$gm, , $post] = $this->seedModerationContext();
        $post->moderation_status = 'rejected';
        $post->approved_at = null;
        $post->approved_by = null;
        $post->save();

        $dispatcher = $this->createMock(PostModerationNotificationDispatcher::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->willThrowException(new RuntimeException('Queue transport unavailable'));
        $this->app->instance(PostModerationNotificationDispatcher::class, $dispatcher);

        app(PostModerationService::class)->synchronize(
            post: $post,
            moderator: $gm,
            previousStatus: 'pending',
            moderationNote: 'Technischer Fehler darf Moderation nicht blockieren.',
        );

        $this->assertDatabaseHas('post_moderation_logs', [
            'post_id' => $post->id,
            'moderator_id' => $gm->id,
            'previous_status' => 'pending',
            'new_status' => 'rejected',
            'reason' => 'Technischer Fehler darf Moderation nicht blockieren.',
        ]);
    }

    public function test_it_logs_post_author_user_id_in_moderation_events(): void
    {
        [$gm, $player, $post] = $this->seedModerationContext();
        $post->moderation_status = 'approved';
        $post->approved_at = now();
        $post->approved_by = $gm->id;
        $post->save();

        $dispatcher = $this->createMock(PostModerationNotificationDispatcher::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->willThrowException(new RuntimeException('Forced notification dispatch failure'));

        $pointService = $this->createMock(PointService::class);
        $pointService->expects($this->once())
            ->method('syncApprovedPost')
            ->with($this->callback(static fn (Post $updatedPost): bool => (int) $updatedPost->id === (int) $post->id));

        $logger = $this->createMock(DomainEventLogger::class);
        $logger->expects($this->exactly(2))
            ->method('info')
            ->with(
                $this->logicalOr(
                    $this->equalTo('moderation.post_notification_dispatch_failed'),
                    $this->equalTo('moderation.post_status_changed'),
                ),
                $this->callback(function (array $context) use ($player, $post): bool {
                    return (int) ($context['user_id'] ?? 0) === (int) $player->id
                        && (int) ($context['post_id'] ?? 0) === (int) $post->id;
                }),
            );

        $service = new PostModerationService($dispatcher, $pointService, $logger);

        $service->synchronize(
            post: $post,
            moderator: $gm,
            previousStatus: 'pending',
            moderationNote: 'Freigabe nach Pruefung.',
        );
    }

    /**
     * @return array{0: User, 1: User, 2: Post}
     */
    private function seedModerationContext(): array
    {
        $gm = User::factory()->gm()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $character = Character::factory()->create([
            'user_id' => $player->id,
            'world_id' => $campaign->world_id,
        ]);

        $post = Post::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'character_id' => $character->id,
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'content' => 'Die Flamme zuckt ueber kalte Steinplatten.',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        return [$gm, $player, $post];
    }
}
