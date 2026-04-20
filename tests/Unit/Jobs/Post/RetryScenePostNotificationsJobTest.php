<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs\Post;

use App\Domain\Post\ScenePostNotificationService;
use App\Jobs\Post\RetryScenePostNotificationsJob;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Support\Observability\DomainEventLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class RetryScenePostNotificationsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_throws_when_scene_service_reports_failed_deliveries(): void
    {
        $author = User::factory()->create();
        $owner = User::factory()->gm()->create();
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
        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'Retry job should fail when ledger still has failed recipients.',
            'moderation_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $owner->id,
        ]);

        $sceneService = $this->createMock(ScenePostNotificationService::class);
        $sceneService->expects($this->once())
            ->method('notifySceneParticipants')
            ->willReturn([
                'in_app_recipients' => 1,
                'webpush_recipients' => 0,
                'has_failures' => true,
            ]);

        $logger = $this->createMock(DomainEventLogger::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'post.scene_notifications_retry_failed',
                $this->callback(static fn (array $context): bool => (int) ($context['post_id'] ?? 0) === (int) $post->id
                    && (int) ($context['in_app_recipients'] ?? 0) === 1
                    && (int) ($context['webpush_recipients'] ?? 0) === 0),
            );

        $job = new RetryScenePostNotificationsJob((int) $post->id, (int) $author->id, 'unit-test');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Scene notification delivery incomplete.');

        $job->handle($sceneService, $logger);
    }
}
