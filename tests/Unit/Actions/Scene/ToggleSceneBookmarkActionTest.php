<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Scene;

use App\Actions\Scene\ToggleSceneBookmarkAction;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneBookmark;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class ToggleSceneBookmarkActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_and_updates_bookmark_with_valid_scene_post_context(): void
    {
        [$world, $campaign, $scene, $user] = $this->seedContext();
        $firstPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $campaign->owner_id,
        ]);
        $latestPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $campaign->owner_id,
        ]);

        $bookmark = app(ToggleSceneBookmarkAction::class)->create(
            world: $world,
            campaign: $campaign,
            scene: $scene,
            user: $user,
            requestedPostId: null,
            label: '  Erster Marker  ',
        );

        $this->assertInstanceOf(SceneBookmark::class, $bookmark);
        $this->assertSame((int) $latestPost->id, (int) $bookmark->post_id);
        $this->assertSame('Erster Marker', $bookmark->label);

        $updatedBookmark = app(ToggleSceneBookmarkAction::class)->create(
            world: $world,
            campaign: $campaign,
            scene: $scene,
            user: $user,
            requestedPostId: (int) $firstPost->id,
            label: ' ',
        );

        $this->assertInstanceOf(SceneBookmark::class, $updatedBookmark);
        $this->assertSame((int) $bookmark->id, (int) $updatedBookmark->id);
        $this->assertSame((int) $firstPost->id, (int) $updatedBookmark->post_id);
        $this->assertNull($updatedBookmark->label);
        $this->assertDatabaseCount('scene_bookmarks', 1);
    }

    public function test_it_throws_validation_exception_for_post_outside_scene(): void
    {
        [$world, $campaign, $sceneA, $user] = $this->seedContext();
        $sceneB = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $campaign->owner_id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $foreignPost = Post::factory()->create([
            'scene_id' => $sceneB->id,
            'user_id' => $campaign->owner_id,
        ]);

        $this->expectException(ValidationException::class);

        try {
            app(ToggleSceneBookmarkAction::class)->create(
                world: $world,
                campaign: $campaign,
                scene: $sceneA,
                user: $user,
                requestedPostId: (int) $foreignPost->id,
                label: 'Invalid',
            );
        } finally {
            $this->assertDatabaseMissing('scene_bookmarks', [
                'user_id' => $user->id,
                'scene_id' => $sceneA->id,
            ]);
        }
    }

    public function test_it_throws_for_world_context_mismatch(): void
    {
        [$world, $campaign, $scene, $user] = $this->seedContext();
        $foreignWorld = World::factory()->create([
            'slug' => 'toggle-bookmark-foreign',
            'is_active' => true,
        ]);

        $this->expectException(ModelNotFoundException::class);

        app(ToggleSceneBookmarkAction::class)->create(
            world: $foreignWorld,
            campaign: $campaign,
            scene: $scene,
            user: $user,
        );
    }

    public function test_it_deletes_existing_bookmark_when_requested(): void
    {
        [$world, $campaign, $scene, $user] = $this->seedContext();
        $bookmark = SceneBookmark::query()->create([
            'user_id' => $user->id,
            'scene_id' => $scene->id,
            'post_id' => null,
            'label' => 'To delete',
        ]);

        app(ToggleSceneBookmarkAction::class)->delete(
            world: $world,
            campaign: $campaign,
            scene: $scene,
            user: $user,
        );

        $this->assertDatabaseMissing('scene_bookmarks', [
            'id' => $bookmark->id,
        ]);
    }

    public function test_it_deletes_bookmark_idempotently_when_called_repeatedly(): void
    {
        [$world, $campaign, $scene, $user] = $this->seedContext();

        SceneBookmark::query()->create([
            'user_id' => $user->id,
            'scene_id' => $scene->id,
            'post_id' => null,
            'label' => 'Repeat delete',
        ]);

        $action = app(ToggleSceneBookmarkAction::class);
        $action->delete(
            world: $world,
            campaign: $campaign,
            scene: $scene,
            user: $user,
        );
        $action->delete(
            world: $world,
            campaign: $campaign,
            scene: $scene,
            user: $user,
        );

        $this->assertDatabaseMissing('scene_bookmarks', [
            'user_id' => $user->id,
            'scene_id' => $scene->id,
        ]);
    }

    public function test_it_rolls_back_when_transaction_fails_after_mutation(): void
    {
        [$world, $campaign, $scene, $user] = $this->seedContext();
        $bookmark = SceneBookmark::query()->create([
            'user_id' => $user->id,
            'scene_id' => $scene->id,
            'post_id' => null,
            'label' => 'Before failure',
        ]);
        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $campaign->owner_id,
        ]);

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

                    throw new RuntimeException('Forced bookmark transaction failure');
                } catch (Throwable $throwable) {
                    $connection->rollBack();

                    throw $throwable;
                }
            });

        $action = new ToggleSceneBookmarkAction($mockedDb);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forced bookmark transaction failure');

        try {
            $action->create(
                world: $world,
                campaign: $campaign,
                scene: $scene,
                user: $user,
                requestedPostId: (int) $post->id,
                label: 'After failure',
            );
        } finally {
            $this->assertDatabaseHas('scene_bookmarks', [
                'id' => $bookmark->id,
                'user_id' => $user->id,
                'scene_id' => $scene->id,
                'post_id' => null,
                'label' => 'Before failure',
            ]);
        }
    }

    /**
     * @return array{0: World, 1: Campaign, 2: Scene, 3: User}
     */
    private function seedContext(): array
    {
        $world = World::factory()->create([
            'slug' => 'toggle-bookmark-world',
            'is_active' => true,
        ]);
        $owner = User::factory()->gm()->create();
        $user = User::factory()->create();
        $campaign = Campaign::factory()->create([
            'world_id' => $world->id,
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

        return [$world, $campaign, $scene, $user];
    }
}
