<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Character;
use App\Models\Handout;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Tests\TestCase;

class PostImmersiveImagesFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);
    }

    public function test_gm_can_create_gm_narration_post_with_immersive_images(): void
    {
        [$campaign, $scene, $gm] = $this->seedCampaignSceneContext();

        $response = $this->actingAs($gm)->post(route('campaigns.scenes.posts.store', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]), [
            'post_type' => 'ic',
            'post_mode' => 'gm',
            'content_format' => 'markdown',
            'content' => str_repeat('Dichter Nebel steigt aus dem Moor. ', 2),
            'immersive_images' => [
                UploadedFile::fake()->image('mist-1.jpg', 1200, 700),
                UploadedFile::fake()->image('mist-2.png', 1200, 700),
            ],
        ]);

        $post = Post::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', $gm->id)
            ->latest('id')
            ->firstOrFail();

        $response->assertRedirect(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]).'#post-'.$post->id);

        $this->assertTrue($post->isGmNarration());
        $this->assertCount(2, $post->getMedia(Post::IMMERSIVE_IMAGES_COLLECTION));
        $this->assertDatabaseCount('media', 2);
        $this->assertDatabaseHas('media', [
            'model_type' => Post::class,
            'model_id' => $post->id,
            'collection_name' => Post::IMMERSIVE_IMAGES_COLLECTION,
        ]);
    }

    public function test_non_gm_cannot_attach_immersive_images_when_creating_post(): void
    {
        [$campaign, $scene, $gm] = $this->seedCampaignSceneContext();
        $player = User::factory()->create();
        $character = Character::factory()->create([
            'user_id' => $player->id,
            'world_id' => $campaign->world_id,
        ]);

        $response = $this->actingAs($player)
            ->from(route('campaigns.scenes.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]))
            ->post(route('campaigns.scenes.posts.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]), [
                'post_type' => 'ic',
                'post_mode' => 'character',
                'character_id' => $character->id,
                'content_format' => 'markdown',
                'content' => str_repeat('Ich antworte auf das Flüstern im Nebel. ', 2),
                'immersive_images' => [UploadedFile::fake()->image('not-allowed.jpg', 1200, 700)],
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('immersive_images');
        $this->assertDatabaseCount('media', 0);
    }

    public function test_gm_character_ic_post_cannot_attach_immersive_images(): void
    {
        [$campaign, $scene, $gm] = $this->seedCampaignSceneContext();
        $character = Character::factory()->create([
            'user_id' => $gm->id,
            'world_id' => $campaign->world_id,
        ]);

        $response = $this->actingAs($gm)
            ->from(route('campaigns.scenes.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]))
            ->post(route('campaigns.scenes.posts.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]), [
                'post_type' => 'ic',
                'post_mode' => 'character',
                'character_id' => $character->id,
                'content_format' => 'markdown',
                'content' => str_repeat('Die Schritte folgen einem bekannten Pfad. ', 2),
                'immersive_images' => [UploadedFile::fake()->image('character-ic.jpg', 1200, 700)],
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('immersive_images');
        $this->assertDatabaseCount('media', 0);
    }

    public function test_gm_ooc_post_cannot_attach_immersive_images(): void
    {
        [$campaign, $scene, $gm] = $this->seedCampaignSceneContext();

        $response = $this->actingAs($gm)
            ->from(route('campaigns.scenes.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]))
            ->post(route('campaigns.scenes.posts.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]), [
                'post_type' => 'ooc',
                'content_format' => 'markdown',
                'content' => str_repeat('Kurzer Meta-Hinweis zur Taktung. ', 2),
                'immersive_images' => [UploadedFile::fake()->image('ooc.jpg', 1200, 700)],
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('immersive_images');
        $this->assertDatabaseCount('media', 0);
    }

    public function test_gm_can_add_immersive_images_on_update_for_gm_narration_post(): void
    {
        [$campaign, $scene, $gm] = $this->seedCampaignSceneContext();
        $post = $this->createGmNarrationPost($scene, $gm);

        $response = $this->actingAs($gm)->patch(route('posts.update', [
            'world' => $campaign->world,
            'post' => $post,
        ]), [
            ...$this->gmUpdatePayload($post),
            'immersive_images' => [UploadedFile::fake()->image('update-1.jpg', 1200, 700)],
        ]);

        $response->assertRedirect();
        $post->refresh();

        $this->assertCount(1, $post->getMedia(Post::IMMERSIVE_IMAGES_COLLECTION));
        $this->assertDatabaseHas('media', [
            'model_type' => Post::class,
            'model_id' => $post->id,
            'collection_name' => Post::IMMERSIVE_IMAGES_COLLECTION,
        ]);
    }

    public function test_gm_can_remove_existing_immersive_images_on_update(): void
    {
        [$campaign, $scene, $gm] = $this->seedCampaignSceneContext();
        $post = $this->createGmNarrationPost($scene, $gm);
        $mediaItems = $this->attachImmersiveImages($post, 2);

        $response = $this->actingAs($gm)->patch(route('posts.update', [
            'world' => $campaign->world,
            'post' => $post,
        ]), [
            ...$this->gmUpdatePayload($post),
            'remove_immersive_media_ids' => [(int) $mediaItems->first()->id],
        ]);

        $response->assertRedirect();
        $post->refresh();
        $currentMedia = $post->getMedia(Post::IMMERSIVE_IMAGES_COLLECTION);

        $this->assertCount(1, $currentMedia);
        $this->assertDatabaseMissing('media', [
            'id' => (int) $mediaItems->first()->id,
        ]);
    }

    public function test_update_rejects_manipulated_remove_ids_from_other_post_collection(): void
    {
        [$campaign, $scene, $gm] = $this->seedCampaignSceneContext();

        $targetPost = $this->createGmNarrationPost($scene, $gm);
        $otherPost = $this->createGmNarrationPost($scene, $gm, 'Anderer Erzählbeitrag');
        $otherMedia = $this->attachImmersiveImages($otherPost, 1)->first();

        $response = $this->actingAs($gm)
            ->from(route('posts.edit', ['world' => $campaign->world, 'post' => $targetPost]))
            ->patch(route('posts.update', [
                'world' => $campaign->world,
                'post' => $targetPost,
            ]), [
                ...$this->gmUpdatePayload($targetPost),
                'remove_immersive_media_ids' => [(int) $otherMedia->id],
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('remove_immersive_media_ids');
        $this->assertDatabaseHas('media', [
            'id' => (int) $otherMedia->id,
            'model_id' => $otherPost->id,
            'collection_name' => Post::IMMERSIVE_IMAGES_COLLECTION,
        ]);
    }

    public function test_update_rejects_when_total_immersive_image_count_would_exceed_limit(): void
    {
        [$campaign, $scene, $gm] = $this->seedCampaignSceneContext();
        $post = $this->createGmNarrationPost($scene, $gm);
        $this->attachImmersiveImages($post, 3);

        $response = $this->actingAs($gm)
            ->from(route('posts.edit', ['world' => $campaign->world, 'post' => $post]))
            ->patch(route('posts.update', [
                'world' => $campaign->world,
                'post' => $post,
            ]), [
                ...$this->gmUpdatePayload($post),
                'immersive_images' => [
                    UploadedFile::fake()->image('new-1.jpg', 1200, 700),
                    UploadedFile::fake()->image('new-2.jpg', 1200, 700),
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('immersive_images');

        $post->refresh();
        $this->assertCount(3, $post->getMedia(Post::IMMERSIVE_IMAGES_COLLECTION));
    }

    public function test_update_rejects_switch_away_from_gm_narration_without_full_explicit_removal(): void
    {
        [$campaign, $scene, $gm] = $this->seedCampaignSceneContext();
        $post = $this->createGmNarrationPost($scene, $gm);
        $this->attachImmersiveImages($post, 1);
        $character = Character::factory()->create([
            'user_id' => $gm->id,
            'world_id' => $campaign->world_id,
        ]);

        $response = $this->actingAs($gm)
            ->from(route('posts.edit', ['world' => $campaign->world, 'post' => $post]))
            ->patch(route('posts.update', [
                'world' => $campaign->world,
                'post' => $post,
            ]), [
                'post_type' => 'ic',
                'post_mode' => 'character',
                'character_id' => $character->id,
                'content_format' => 'markdown',
                'content' => str_repeat('Die Szene wird nun aus Charaktersicht erzählt. ', 2),
                'ic_quote' => '',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('remove_immersive_media_ids');
    }

    public function test_soft_deleted_posts_do_not_render_immersive_images_in_thread(): void
    {
        [$campaign, $scene, $gm] = $this->seedCampaignSceneContext();
        $post = $this->createGmNarrationPost($scene, $gm);
        $media = $this->attachImmersiveImages($post, 1)->first();
        $mediaUrl = $media->getUrl();

        $post->delete();

        $response = $this->actingAs($gm)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk();
        $response->assertSee('Beitrag gelöscht.');
        $response->assertDontSee($mediaUrl, false);
    }

    public function test_only_post_and_handout_models_have_media_library_interfaces_in_current_scope(): void
    {
        $this->assertTrue(is_subclass_of(Post::class, HasMedia::class));
        $this->assertContains(InteractsWithMedia::class, class_uses_recursive(Post::class));
        $this->assertTrue(is_subclass_of(Handout::class, HasMedia::class));
        $this->assertContains(InteractsWithMedia::class, class_uses_recursive(Handout::class));

        $this->assertFalse(is_subclass_of(Character::class, HasMedia::class));
        $this->assertFalse(is_subclass_of(User::class, HasMedia::class));
        $this->assertNotContains(InteractsWithMedia::class, class_uses_recursive(Character::class));
        $this->assertNotContains(InteractsWithMedia::class, class_uses_recursive(User::class));
    }

    /**
     * @return array{0: Campaign, 1: Scene, 2: User}
     */
    private function seedCampaignSceneContext(): array
    {
        $gm = User::factory()->gm()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
            'requires_post_moderation' => false,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        return [$campaign, $scene, $gm];
    }

    private function createGmNarrationPost(Scene $scene, User $gm, ?string $content = null): Post
    {
        return Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
            'character_id' => null,
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'content' => $content ?? str_repeat('Die Spielleitung beschreibt den nächsten Takt. ', 2),
            'meta' => ['author_role' => 'gm'],
            'moderation_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $gm->id,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media>
     */
    private function attachImmersiveImages(Post $post, int $count)
    {
        for ($index = 1; $index <= $count; $index++) {
            $post
                ->addMedia(UploadedFile::fake()->image("immersive-{$post->id}-{$index}.jpg", 1200, 700))
                ->toMediaCollection(Post::IMMERSIVE_IMAGES_COLLECTION);
        }

        return $post->fresh()->getMedia(Post::IMMERSIVE_IMAGES_COLLECTION);
    }

    /**
     * @return array{
     *   post_type: string,
     *   post_mode: string,
     *   content_format: string,
     *   content: string,
     *   ic_quote: string
     * }
     */
    private function gmUpdatePayload(Post $post): array
    {
        return [
            'post_type' => 'ic',
            'post_mode' => 'gm',
            'content_format' => (string) $post->content_format,
            'content' => (string) $post->content,
            'ic_quote' => '',
        ];
    }
}
