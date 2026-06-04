<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Campaign\DeleteCampaignAction;
use App\Actions\Scene\DeleteSceneAction;
use App\Models\Campaign;
use App\Models\Handout;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class CampaignDeletionMediaCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        config(['media-library.disk_name' => 'public']);
    }

    public function test_delete_campaign_action_removes_post_and_handout_media_before_campaign_cascade(): void
    {
        $owner = User::factory()->gm()->create();
        $world = World::factory()->create();

        $campaign = Campaign::factory()->create([
            'world_id' => (int) $world->id,
            'owner_id' => (int) $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => (int) $campaign->id,
            'created_by' => (int) $owner->id,
            'header_image_path' => 'scene-headers/campaign-scene.webp',
        ]);
        $post = Post::factory()->create([
            'scene_id' => (int) $scene->id,
            'user_id' => (int) $owner->id,
        ]);
        $handout = Handout::factory()->create([
            'campaign_id' => (int) $campaign->id,
            'scene_id' => (int) $scene->id,
            'created_by' => (int) $owner->id,
        ]);

        $postMedia = $post
            ->addMedia(UploadedFile::fake()->image('campaign-post.jpg', 1200, 700))
            ->toMediaCollection(Post::IMMERSIVE_IMAGES_COLLECTION);
        $handoutMedia = $handout
            ->addMedia(UploadedFile::fake()->image('campaign-handout.jpg', 1200, 700))
            ->toMediaCollection(Handout::HANDOUT_FILE_COLLECTION);
        $sceneContentMedia = $scene
            ->addMedia(UploadedFile::fake()->image('campaign-scene-content.jpg', 1200, 700))
            ->withCustomProperties(['slot' => 1])
            ->toMediaCollection(Scene::CONTENT_IMAGES_COLLECTION);

        $postPath = $postMedia->getPathRelativeToRoot();
        $handoutPath = $handoutMedia->getPathRelativeToRoot();
        $sceneContentPath = $sceneContentMedia->getPathRelativeToRoot();
        Storage::disk('public')->put('scene-headers/campaign-scene.webp', 'scene-title');

        $this->assertTrue(Storage::disk('public')->exists($postPath));
        $this->assertTrue(Storage::disk('local')->exists($handoutPath));
        $this->assertTrue(Storage::disk('public')->exists($sceneContentPath));
        $this->assertTrue(Storage::disk('public')->exists('scene-headers/campaign-scene.webp'));

        $otherCampaign = Campaign::factory()->create([
            'world_id' => (int) $world->id,
            'owner_id' => (int) $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);
        $otherScene = Scene::factory()->create([
            'campaign_id' => (int) $otherCampaign->id,
            'created_by' => (int) $owner->id,
            'header_image_path' => 'scene-headers/other-scene.webp',
        ]);
        $otherPost = Post::factory()->create([
            'scene_id' => (int) $otherScene->id,
            'user_id' => (int) $owner->id,
        ]);
        $otherHandout = Handout::factory()->create([
            'campaign_id' => (int) $otherCampaign->id,
            'scene_id' => (int) $otherScene->id,
            'created_by' => (int) $owner->id,
        ]);

        $otherPostMedia = $otherPost
            ->addMedia(UploadedFile::fake()->image('other-post.jpg', 1200, 700))
            ->toMediaCollection(Post::IMMERSIVE_IMAGES_COLLECTION);
        $otherHandoutMedia = $otherHandout
            ->addMedia(UploadedFile::fake()->image('other-handout.jpg', 1200, 700))
            ->toMediaCollection(Handout::HANDOUT_FILE_COLLECTION);
        $otherSceneContentMedia = $otherScene
            ->addMedia(UploadedFile::fake()->image('other-scene-content.jpg', 1200, 700))
            ->withCustomProperties(['slot' => 1])
            ->toMediaCollection(Scene::CONTENT_IMAGES_COLLECTION);

        $otherPostPath = $otherPostMedia->getPathRelativeToRoot();
        $otherHandoutPath = $otherHandoutMedia->getPathRelativeToRoot();
        $otherSceneContentPath = $otherSceneContentMedia->getPathRelativeToRoot();
        Storage::disk('public')->put('scene-headers/other-scene.webp', 'other-scene-title');

        $this->assertTrue(Storage::disk('public')->exists($otherPostPath));
        $this->assertTrue(Storage::disk('local')->exists($otherHandoutPath));
        $this->assertTrue(Storage::disk('public')->exists($otherSceneContentPath));
        $this->assertTrue(Storage::disk('public')->exists('scene-headers/other-scene.webp'));

        app(DeleteCampaignAction::class)->execute($world, $campaign);

        $this->assertDatabaseMissing('campaigns', ['id' => (int) $campaign->id]);
        $this->assertDatabaseMissing('scenes', ['id' => (int) $scene->id]);
        $this->assertDatabaseMissing('posts', ['id' => (int) $post->id]);
        $this->assertDatabaseMissing('handouts', ['id' => (int) $handout->id]);

        $this->assertDatabaseMissing('media', ['id' => (int) $postMedia->id]);
        $this->assertDatabaseMissing('media', ['id' => (int) $handoutMedia->id]);
        $this->assertDatabaseMissing('media', ['id' => (int) $sceneContentMedia->id]);
        $this->assertFalse(Storage::disk('public')->exists($postPath));
        $this->assertFalse(Storage::disk('local')->exists($handoutPath));
        $this->assertFalse(Storage::disk('public')->exists($sceneContentPath));
        $this->assertFalse(Storage::disk('public')->exists('scene-headers/campaign-scene.webp'));

        $this->assertDatabaseHas('campaigns', ['id' => (int) $otherCampaign->id]);
        $this->assertDatabaseHas('media', ['id' => (int) $otherPostMedia->id]);
        $this->assertDatabaseHas('media', ['id' => (int) $otherHandoutMedia->id]);
        $this->assertDatabaseHas('media', ['id' => (int) $otherSceneContentMedia->id]);
        $this->assertTrue(Storage::disk('public')->exists($otherPostPath));
        $this->assertTrue(Storage::disk('local')->exists($otherHandoutPath));
        $this->assertTrue(Storage::disk('public')->exists($otherSceneContentPath));
        $this->assertTrue(Storage::disk('public')->exists('scene-headers/other-scene.webp'));
    }

    public function test_delete_scene_action_removes_scene_title_and_content_images_without_touching_other_scene_media(): void
    {
        $owner = User::factory()->gm()->create();
        $world = World::factory()->create();
        $campaign = Campaign::factory()->create([
            'world_id' => (int) $world->id,
            'owner_id' => (int) $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => (int) $campaign->id,
            'created_by' => (int) $owner->id,
            'header_image_path' => 'scene-headers/direct-scene.webp',
        ]);
        $otherScene = Scene::factory()->create([
            'campaign_id' => (int) $campaign->id,
            'created_by' => (int) $owner->id,
            'header_image_path' => 'scene-headers/other-direct-scene.webp',
        ]);

        Storage::disk('public')->put('scene-headers/direct-scene.webp', 'scene-title');
        Storage::disk('public')->put('scene-headers/other-direct-scene.webp', 'other-scene-title');

        $sceneContentMedia = $scene
            ->addMedia(UploadedFile::fake()->image('direct-scene-content.jpg', 1200, 700))
            ->withCustomProperties(['slot' => 1])
            ->toMediaCollection(Scene::CONTENT_IMAGES_COLLECTION);
        $otherSceneContentMedia = $otherScene
            ->addMedia(UploadedFile::fake()->image('other-direct-scene-content.jpg', 1200, 700))
            ->withCustomProperties(['slot' => 1])
            ->toMediaCollection(Scene::CONTENT_IMAGES_COLLECTION);

        $sceneContentPath = $sceneContentMedia->getPathRelativeToRoot();
        $otherSceneContentPath = $otherSceneContentMedia->getPathRelativeToRoot();

        app(DeleteSceneAction::class)->execute($scene);

        $this->assertDatabaseMissing('scenes', ['id' => (int) $scene->id]);
        $this->assertDatabaseMissing('media', ['id' => (int) $sceneContentMedia->id]);
        $this->assertFalse(Storage::disk('public')->exists('scene-headers/direct-scene.webp'));
        $this->assertFalse(Storage::disk('public')->exists($sceneContentPath));

        $this->assertDatabaseHas('scenes', ['id' => (int) $otherScene->id]);
        $this->assertDatabaseHas('media', ['id' => (int) $otherSceneContentMedia->id]);
        $this->assertTrue(Storage::disk('public')->exists('scene-headers/other-direct-scene.webp'));
        $this->assertTrue(Storage::disk('public')->exists($otherSceneContentPath));
    }

    public function test_delete_campaign_action_without_media_keeps_behavior_unchanged(): void
    {
        $owner = User::factory()->gm()->create();
        $world = World::factory()->create();
        $campaign = Campaign::factory()->create([
            'world_id' => (int) $world->id,
            'owner_id' => (int) $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => (int) $campaign->id,
            'created_by' => (int) $owner->id,
        ]);
        $post = Post::factory()->create([
            'scene_id' => (int) $scene->id,
            'user_id' => (int) $owner->id,
        ]);
        $handout = Handout::factory()->create([
            'campaign_id' => (int) $campaign->id,
            'scene_id' => (int) $scene->id,
            'created_by' => (int) $owner->id,
        ]);

        $this->assertSame(0, (int) Media::query()->count());

        app(DeleteCampaignAction::class)->execute($world, $campaign);

        $this->assertDatabaseMissing('campaigns', ['id' => (int) $campaign->id]);
        $this->assertDatabaseMissing('scenes', ['id' => (int) $scene->id]);
        $this->assertDatabaseMissing('posts', ['id' => (int) $post->id]);
        $this->assertDatabaseMissing('handouts', ['id' => (int) $handout->id]);
        $this->assertSame(0, (int) Media::query()->count());
    }
}
