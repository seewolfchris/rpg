<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Post;

use App\Actions\Post\StorePostAction;
use App\Domain\Post\StorePostResult;
use App\Domain\Post\StorePostService;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorePostActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_delegates_to_store_service_with_calculated_moderation_flags(): void
    {
        $world = World::factory()->create([
            'slug' => 'post-store-world',
            'is_active' => true,
        ]);
        $owner = User::factory()->gm()->create();
        $author = User::factory()->create();
        $campaign = Campaign::factory()->create([
            'world_id' => $world->id,
            'owner_id' => $owner->id,
            'is_public' => false,
            'requires_post_moderation' => true,
            'status' => 'active',
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $resultPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'Persisted by mocked service',
        ]);
        $payload = [
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'Store payload',
        ];

        $storePostService = $this->createMock(StorePostService::class);
        $storePostService->expects($this->once())
            ->method('store')
            ->with(
                $this->callback(static fn (Scene $resolvedScene): bool => (int) $resolvedScene->id === (int) $scene->id),
                $this->callback(static fn (User $resolvedAuthor): bool => (int) $resolvedAuthor->id === (int) $author->id),
                $payload,
            )
            ->willReturn(new StorePostResult($resultPost, false, false));

        $action = new StorePostAction($storePostService);

        $result = $action->execute(
            scene: $scene,
            author: $author,
            data: $payload,
        );

        $this->assertSame((int) $resultPost->id, (int) $result->post->id);
    }

    public function test_it_marks_moderator_posts_as_not_requiring_approval(): void
    {
        $world = World::factory()->create([
            'slug' => 'post-store-moderator',
            'is_active' => true,
        ]);
        $author = User::factory()->gm()->create();
        $campaign = Campaign::factory()->create([
            'world_id' => $world->id,
            'owner_id' => $author->id,
            'is_public' => false,
            'requires_post_moderation' => true,
            'status' => 'active',
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $author->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $resultPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'Moderator result post',
        ]);
        $payload = [
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'Moderator payload',
        ];

        $storePostService = $this->createMock(StorePostService::class);
        $storePostService->expects($this->once())
            ->method('store')
            ->with(
                $this->isInstanceOf(Scene::class),
                $this->isInstanceOf(User::class),
                $payload,
            )
            ->willReturn(new StorePostResult($resultPost, false, false));

        $action = new StorePostAction($storePostService);

        $action->execute(
            scene: $scene,
            author: $author,
            data: $payload,
        );
    }

    public function test_it_propagates_store_service_exceptions(): void
    {
        $world = World::factory()->create();
        $author = User::factory()->create();
        $campaign = Campaign::factory()->create([
            'world_id' => $world->id,
            'owner_id' => User::factory()->gm()->create()->id,
            'status' => 'active',
            'is_public' => false,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $campaign->owner_id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $storePostService = $this->createMock(StorePostService::class);
        $storePostService->expects($this->once())
            ->method('store')
            ->willThrowException(new ModelNotFoundException);

        $action = new StorePostAction($storePostService);

        $this->expectException(ModelNotFoundException::class);

        $action->execute(
            scene: $scene,
            author: $author,
            data: [
                'post_type' => 'ooc',
                'content_format' => 'plain',
                'content' => 'Will fail due context mismatch',
            ],
        );
    }
}
