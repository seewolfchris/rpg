<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Post\PostModerationService;
use App\Domain\Post\StorePostResult;
use App\Domain\Post\StorePostService;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostControllerActionDeflationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_path_delegates_directly_to_store_post_service(): void
    {
        $owner = User::factory()->gm()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => false,
            'requires_post_moderation' => false,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $storedPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $owner->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'Persisted by mocked StorePostService.',
            'moderation_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $owner->id,
        ]);

        $payload = [
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'Controller delegates store writes.',
        ];

        $storePostService = $this->createMock(StorePostService::class);
        $storePostService->expects($this->once())
            ->method('store')
            ->with(
                $this->callback(static fn (Scene $resolvedScene): bool => $resolvedScene->is($scene)),
                $this->callback(static fn (User $resolvedAuthor): bool => $resolvedAuthor->is($owner)),
                $this->callback(static function (array $resolvedPayload) use ($payload): bool {
                    return ($resolvedPayload['post_type'] ?? null) === $payload['post_type']
                        && ($resolvedPayload['content_format'] ?? null) === $payload['content_format']
                        && ($resolvedPayload['content'] ?? null) === $payload['content']
                        && ($resolvedPayload['post_mode'] ?? null) === 'character'
                        && ($resolvedPayload['probe_enabled'] ?? null) === false
                        && ($resolvedPayload['inventory_award_enabled'] ?? null) === false;
                }),
            )
            ->willReturn(new StorePostResult($storedPost, false, false));
        $this->app->instance(StorePostService::class, $storePostService);

        $response = $this->actingAs($owner)->post(route('campaigns.scenes.posts.store', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]), $payload);

        $response
            ->assertRedirect(route('campaigns.scenes.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]).'#post-'.$storedPost->id)
            ->assertSessionHas('status', 'Beitrag gespeichert.');
    }

    public function test_moderate_path_delegates_directly_to_apply_post_moderation_transition_action(): void
    {
        $gm = User::factory()->gm()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
        ]);
        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => User::factory()->create()->id,
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $postModerationService = $this->createMock(PostModerationService::class);
        $postModerationService->expects($this->once())
            ->method('synchronize')
            ->with(
                $this->callback(static fn (Post $resolvedPost): bool => $resolvedPost->is($post)),
                $this->callback(static fn (User $resolvedModerator): bool => $resolvedModerator->is($gm)),
                'pending',
                'Freigabe',
            );
        $this->app->instance(PostModerationService::class, $postModerationService);

        $response = $this->actingAs($gm)
            ->from(route('campaigns.scenes.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]))
            ->patch(route('posts.moderate', [
                'world' => $campaign->world,
                'post' => $post,
            ]), [
                'moderation_status' => 'approved',
                'moderation_note' => '  Freigabe  ',
            ]);

        $response
            ->assertRedirect(route('campaigns.scenes.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]))
            ->assertSessionHas('status', 'Moderationsstatus aktualisiert.');
    }
}
