<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Post;

use App\Actions\Post\CreatePostReactionAction;
use App\Actions\Post\DeletePostReactionAction;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\PostReaction;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostReactionActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_reaction_is_idempotent_for_same_user_post_and_emoji(): void
    {
        [$world, $post, $reactor] = $this->seedContext();

        $action = app(CreatePostReactionAction::class);
        $action->execute($world, $post, $reactor, 'heart');
        $action->execute($world, $post, $reactor, 'heart');

        $this->assertSame(1, PostReaction::query()
            ->where('post_id', $post->id)
            ->where('user_id', $reactor->id)
            ->where('emoji', 'heart')
            ->count());
    }

    public function test_delete_reaction_removes_existing_reaction(): void
    {
        [$world, $post, $reactor] = $this->seedContext();

        PostReaction::query()->create([
            'post_id' => $post->id,
            'user_id' => $reactor->id,
            'emoji' => 'joy',
        ]);

        app(DeletePostReactionAction::class)->execute($world, $post, $reactor, 'joy');

        $this->assertDatabaseMissing('post_reactions', [
            'post_id' => $post->id,
            'user_id' => $reactor->id,
            'emoji' => 'joy',
        ]);
    }

    public function test_delete_reaction_is_idempotent_when_called_repeatedly(): void
    {
        [$world, $post, $reactor] = $this->seedContext();

        PostReaction::query()->create([
            'post_id' => $post->id,
            'user_id' => $reactor->id,
            'emoji' => 'clap',
        ]);

        $action = app(DeletePostReactionAction::class);
        $action->execute($world, $post, $reactor, 'clap');
        $action->execute($world, $post, $reactor, 'clap');

        $this->assertSame(0, PostReaction::query()
            ->where('post_id', $post->id)
            ->where('user_id', $reactor->id)
            ->where('emoji', 'clap')
            ->count());
    }

    public function test_create_reaction_throws_for_world_context_mismatch(): void
    {
        [$world, $post, $reactor] = $this->seedContext();
        $otherWorld = World::factory()->create();

        $this->expectException(ModelNotFoundException::class);

        app(CreatePostReactionAction::class)->execute($otherWorld, $post, $reactor, 'fire');
    }

    /**
     * @return array{0: World, 1: Post, 2: User}
     */
    private function seedContext(): array
    {
        $owner = User::factory()->gm()->create();
        $reactor = User::factory()->create();

        $world = World::factory()->create();
        $campaign = Campaign::factory()->create([
            'world_id' => $world->id,
            'owner_id' => $owner->id,
            'status' => 'active',
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $owner->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'Reaktionstext',
            'moderation_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $owner->id,
        ]);

        return [$world, $post, $reactor];
    }
}
