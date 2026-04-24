<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Post;

use App\Actions\Post\DeletePostAction;
use App\Models\Campaign;
use App\Models\PointEvent;
use App\Models\Post;
use App\Models\PostModerationLog;
use App\Models\PostRevision;
use App\Models\Scene;
use App\Models\User;
use App\Support\Gamification\PointService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class DeletePostActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_soft_deletes_post_sets_deleter_and_revokes_approved_points_atomically(): void
    {
        [, $author, $post] = $this->seedApprovedPostContext();
        $moderator = User::factory()->gm()->create();
        $revision = PostRevision::query()->create([
            'post_id' => $post->id,
            'version' => 1,
            'editor_id' => $author->id,
            'character_id' => null,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'Earlier approved post content',
            'meta' => null,
            'moderation_status' => 'approved',
            'created_at' => now(),
        ]);
        $moderationLog = PostModerationLog::query()->create([
            'post_id' => $post->id,
            'moderator_id' => $moderator->id,
            'previous_status' => 'pending',
            'new_status' => 'approved',
            'reason' => 'Approved before deletion.',
            'created_at' => now(),
        ]);

        app(DeletePostAction::class)->execute($post, (int) $moderator->id);

        $author->refresh();
        $this->assertSame(0, (int) $author->points);

        $this->assertSoftDeleted('posts', [
            'id' => $post->id,
            'deleted_by' => $moderator->id,
        ]);
        $this->assertDatabaseMissing('point_events', [
            'user_id' => $author->id,
            'source_type' => 'post',
            'source_id' => $post->id,
            'event_key' => 'approved',
        ]);
        $this->assertDatabaseHas('post_revisions', [
            'id' => $revision->id,
            'post_id' => $post->id,
            'content' => 'Earlier approved post content',
        ]);
        $this->assertDatabaseHas('post_moderation_logs', [
            'id' => $moderationLog->id,
            'post_id' => $post->id,
            'reason' => 'Approved before deletion.',
        ]);
    }

    public function test_it_throws_when_post_context_is_tampered(): void
    {
        [, $author, $post] = $this->seedApprovedPostContext();
        $foreignScene = Scene::factory()->create([
            'campaign_id' => (int) $post->scene->campaign_id,
            'created_by' => (int) $post->scene->created_by,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $post->setAttribute('scene_id', (int) $foreignScene->id);

        $this->expectException(ModelNotFoundException::class);

        try {
            app(DeletePostAction::class)->execute($post);
        } finally {
            $author->refresh();
            $this->assertSame(10, (int) $author->points);
            $this->assertNotSoftDeleted('posts', [
                'id' => $post->id,
            ]);
        }
    }

    public function test_it_rolls_back_when_transaction_fails_after_mutation(): void
    {
        [, $author, $post] = $this->seedApprovedPostContext();

        $realDb = app(DatabaseManager::class);
        $mockedDb = Mockery::mock(DatabaseManager::class);
        $mockedDb->shouldReceive('transaction')
            ->once()
            ->withArgs(static fn (mixed $callback, mixed $attempts): bool => is_callable($callback) && $attempts === 3)
            ->andReturnUsing(function (callable $callback) use ($realDb): mixed {
                $connection = $realDb->connection();
                $connection->beginTransaction();

                try {
                    $callback();

                    throw new RuntimeException('Forced post delete failure');
                } catch (Throwable $throwable) {
                    $connection->rollBack();

                    throw $throwable;
                }
            });

        $action = new DeletePostAction($mockedDb, app(PointService::class));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forced post delete failure');

        try {
            $action->execute($post);
        } finally {
            $author->refresh();
            $this->assertSame(10, (int) $author->points);

            $this->assertNotSoftDeleted('posts', [
                'id' => $post->id,
            ]);
            $this->assertDatabaseHas('point_events', [
                'user_id' => $author->id,
                'source_type' => 'post',
                'source_id' => $post->id,
                'event_key' => 'approved',
                'points' => 10,
            ]);
        }
    }

    /**
     * @return array{0: Campaign, 1: User, 2: Post}
     */
    private function seedApprovedPostContext(): array
    {
        $author = User::factory()->gm()->create([
            'points' => 10,
        ]);
        $campaign = Campaign::factory()->create([
            'owner_id' => $author->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $author->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'Approved post to delete',
            'moderation_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $author->id,
        ]);

        PointEvent::query()->create([
            'user_id' => $author->id,
            'source_type' => 'post',
            'source_id' => $post->id,
            'event_key' => 'approved',
            'points' => 10,
            'meta' => [
                'post_type' => 'ooc',
                'scene_id' => $scene->id,
            ],
            'created_at' => now(),
        ]);

        return [$campaign, $author, $post];
    }
}
