<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Post;
use App\Models\PostReaction;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostReactionFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_add_and_remove_positive_reaction_when_feature_is_enabled(): void
    {
        config(['features.wave4.reactions' => true]);

        [$campaign, $scene, $post, $reactor] = $this->seedContext();

        $this->actingAs($reactor)->post(route('posts.reactions.store', [
            'world' => $campaign->world,
            'post' => $post,
        ]), [
            'emoji' => 'heart',
        ])->assertRedirect();

        $this->assertDatabaseHas('post_reactions', [
            'post_id' => $post->id,
            'user_id' => $reactor->id,
            'emoji' => 'heart',
        ]);

        $this->actingAs($reactor)->delete(route('posts.reactions.destroy', [
            'world' => $campaign->world,
            'post' => $post,
        ]), [
            'emoji' => 'heart',
        ])->assertRedirect();

        $this->assertDatabaseMissing('post_reactions', [
            'post_id' => $post->id,
            'user_id' => $reactor->id,
            'emoji' => 'heart',
        ]);
    }

    public function test_duplicate_reaction_requests_remain_idempotent(): void
    {
        config(['features.wave4.reactions' => true]);

        [$campaign, , $post, $reactor] = $this->seedContext();

        $firstResponse = $this->actingAs($reactor)->post(route('posts.reactions.store', [
            'world' => $campaign->world,
            'post' => $post,
        ]), [
            'emoji' => 'joy',
        ]);
        $firstResponse->assertRedirect();

        $secondResponse = $this->actingAs($reactor)->post(route('posts.reactions.store', [
            'world' => $campaign->world,
            'post' => $post,
        ]), [
            'emoji' => 'joy',
        ]);
        $secondResponse->assertRedirect();

        $this->assertSame(1, PostReaction::query()
            ->where('post_id', $post->id)
            ->where('user_id', $reactor->id)
            ->where('emoji', 'joy')
            ->count());
    }

    public function test_reaction_rejects_invalid_emoji(): void
    {
        config(['features.wave4.reactions' => true]);

        [$campaign, $scene, $post, $reactor] = $this->seedContext();

        $response = $this->actingAs($reactor)
            ->from(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->post(route('posts.reactions.store', [
                'world' => $campaign->world,
                'post' => $post,
            ]), [
                'emoji' => 'thumbsdown',
            ]);

        $response->assertRedirect(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));
        $response->assertSessionHasErrors('emoji');

        $this->assertDatabaseCount('post_reactions', 0);
    }

    public function test_reactions_endpoint_is_not_available_when_feature_is_disabled(): void
    {
        config(['features.wave4.reactions' => false]);

        [$campaign, , $post, $reactor] = $this->seedContext();

        $this->actingAs($reactor)->post(route('posts.reactions.store', [
            'world' => $campaign->world,
            'post' => $post,
        ]), [
            'emoji' => PostReaction::ALLOWED_EMOJIS[0],
        ])->assertNotFound();
    }

    /**
     * @return array{0: Campaign, 1: Scene, 2: Post, 3: User}
     */
    private function seedContext(): array
    {
        $gm = User::factory()->gm()->create();
        $reactor = User::factory()->create();

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

        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'Reaktionsziel fuer den Test.',
            'moderation_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $gm->id,
        ]);

        return [$campaign, $scene, $post, $reactor];
    }
}
