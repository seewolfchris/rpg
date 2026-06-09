<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Scene\SceneContentImageService;
use App\Models\Campaign;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class SceneContentImagesFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);
    }

    public function test_gm_can_create_scene_with_content_images_and_stable_slots(): void
    {
        [$campaign, $gm] = $this->seedCampaignContext();

        $response = $this->actingAs($gm)->post(route('campaigns.scenes.store', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]), [
            ...$this->scenePayload([
                'title' => 'Der versunkene Hof',
                'description' => "Nebel ueber Stein.\n\n[bild:1]",
            ]),
            'content_images' => [
                UploadedFile::fake()->image('hof-1.jpg', 1200, 700),
                UploadedFile::fake()->image('hof-2.png', 1200, 700),
            ],
        ]);

        $scene = Scene::query()
            ->where('campaign_id', $campaign->id)
            ->where('title', 'Der versunkene Hof')
            ->firstOrFail();

        $response->assertRedirect(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $mediaItems = $scene->getMedia(Scene::CONTENT_IMAGES_COLLECTION);
        $this->assertCount(2, $mediaItems);
        $this->assertSame([1, 2], $mediaItems
            ->map(static fn (Media $media): int => (int) $media->getCustomProperty('slot'))
            ->sort()
            ->values()
            ->all());
        $this->assertDatabaseHas('media', [
            'model_type' => Scene::class,
            'model_id' => $scene->id,
            'collection_name' => Scene::CONTENT_IMAGES_COLLECTION,
        ]);
    }

    public function test_non_manager_cannot_attach_scene_content_images(): void
    {
        [$campaign] = $this->seedCampaignContext();
        $player = User::factory()->create();

        $response = $this->actingAs($player)->post(route('campaigns.scenes.store', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]), [
            ...$this->scenePayload(),
            'content_images' => [UploadedFile::fake()->image('not-allowed.jpg', 1200, 700)],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('media', 0);
        $this->assertDatabaseCount('scenes', 0);
    }

    public function test_create_rejects_more_than_four_scene_content_images(): void
    {
        [$campaign, $gm] = $this->seedCampaignContext();

        $response = $this->actingAs($gm)
            ->from(route('campaigns.scenes.create', [
                'world' => $campaign->world,
                'campaign' => $campaign,
            ]))
            ->post(route('campaigns.scenes.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
            ]), [
                ...$this->scenePayload(),
                'content_images' => [
                    UploadedFile::fake()->image('one.jpg', 1200, 700),
                    UploadedFile::fake()->image('two.jpg', 1200, 700),
                    UploadedFile::fake()->image('three.jpg', 1200, 700),
                    UploadedFile::fake()->image('four.jpg', 1200, 700),
                    UploadedFile::fake()->image('five.jpg', 1200, 700),
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('content_images');
        $this->assertDatabaseCount('media', 0);
        $this->assertDatabaseCount('scenes', 0);
    }

    public function test_update_rejects_when_total_scene_content_images_would_exceed_limit(): void
    {
        [$campaign, $gm, $scene] = $this->seedCampaignSceneContext();
        $this->attachContentImages($scene, 3);

        $response = $this->actingAs($gm)
            ->from(route('campaigns.scenes.edit', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]))
            ->put(route('campaigns.scenes.update', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]), [
                ...$this->scenePayload($scene),
                'content_images' => [
                    UploadedFile::fake()->image('new-1.jpg', 1200, 700),
                    UploadedFile::fake()->image('new-2.jpg', 1200, 700),
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('content_images');
        $this->assertCount(3, $scene->fresh()->getMedia(Scene::CONTENT_IMAGES_COLLECTION));
    }

    public function test_update_rejects_invalid_content_image_type(): void
    {
        [$campaign, $gm, $scene] = $this->seedCampaignSceneContext();

        $response = $this->actingAs($gm)
            ->from(route('campaigns.scenes.edit', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]))
            ->put(route('campaigns.scenes.update', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]), [
                ...$this->scenePayload($scene),
                'content_images' => [
                    UploadedFile::fake()->create('scene.txt', 12, 'text/plain'),
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('content_images.0');
        $this->assertDatabaseCount('media', 0);
    }

    public function test_update_rejects_manipulated_remove_ids_from_other_scene(): void
    {
        [$campaign, $gm, $scene] = $this->seedCampaignSceneContext();
        $otherScene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
            'mood' => 'neutral',
        ]);
        $otherMedia = $this->attachContentImages($otherScene, 1)->firstOrFail();

        $response = $this->actingAs($gm)
            ->from(route('campaigns.scenes.edit', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]))
            ->put(route('campaigns.scenes.update', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]), [
                ...$this->scenePayload($scene),
                'remove_content_media_ids' => [(int) $otherMedia->id],
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('remove_content_media_ids');
        $this->assertDatabaseHas('media', [
            'id' => (int) $otherMedia->id,
            'model_type' => Scene::class,
            'model_id' => $otherScene->id,
            'collection_name' => Scene::CONTENT_IMAGES_COLLECTION,
        ]);
    }

    public function test_scene_description_renders_inline_images_and_gallery_without_duplicates(): void
    {
        [$campaign, $gm, $scene] = $this->seedCampaignSceneContext([
            'description' => "Erster Blick.\n\n[bild:1]\n\nZweiter Blick.\n\n[bild:2]",
        ]);
        $mediaItems = $this->attachContentImages($scene, 3);
        $firstMedia = $mediaItems->get(0);
        $secondMedia = $mediaItems->get(1);
        $thirdMedia = $mediaItems->get(2);

        $response = $this->actingAs($gm)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk();

        $html = $response->getContent();
        $this->assertInlineImageSlotContains($html, 1, $firstMedia->getUrl());
        $this->assertInlineImageSlotContains($html, 2, $secondMedia->getUrl());
        $this->assertSame(2, substr_count($html, $firstMedia->getUrl()));
        $this->assertSame(2, substr_count($html, $secondMedia->getUrl()));
        $this->assertGalleryImageContains($html, (int) $thirdMedia->id, $thirdMedia->getUrl());
    }

    public function test_scene_description_renders_missing_slot_notice_and_escapes_html(): void
    {
        [$campaign, $gm, $scene] = $this->seedCampaignSceneContext([
            'description' => "<strong>Rohtext</strong>\nZeile zwei\n\n[bild:4]",
        ]);

        $response = $this->actingAs($gm)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('&lt;strong&gt;Rohtext&lt;/strong&gt;<br>', $html);
        $this->assertStringContainsString('Bild 4 nicht verfügbar', $html);
        $this->assertStringContainsString('data-scene-inline-image-missing="1"', $html);
        $this->assertStringNotContainsString('<strong>Rohtext</strong>', $html);
    }

    public function test_stable_scene_slots_survive_removal_and_lowest_free_slot_is_reused(): void
    {
        [$campaign, $gm, $scene] = $this->seedCampaignSceneContext();

        app(SceneContentImageService::class)->mutateContentImages($scene, [
            UploadedFile::fake()->image('slot-1.jpg', 1200, 700),
            UploadedFile::fake()->image('slot-2.jpg', 1200, 700),
        ]);

        $mediaBySlot = $this->mediaByPersistedSlot($scene->fresh());
        $slotOneMedia = $mediaBySlot[1];
        $slotTwoMedia = $mediaBySlot[2];

        $this->actingAs($gm)->put(route('campaigns.scenes.update', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]), [
            ...$this->scenePayload($scene),
            'remove_content_media_ids' => [(int) $slotOneMedia->id],
        ])->assertRedirect();

        $slotTwoMedia->refresh();
        $this->assertSame(2, (int) $slotTwoMedia->getCustomProperty('slot'));
        $this->assertDatabaseMissing('media', ['id' => (int) $slotOneMedia->id]);

        $this->actingAs($gm)->put(route('campaigns.scenes.update', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]), [
            ...$this->scenePayload($scene),
            'content_images' => [UploadedFile::fake()->image('new-slot-1.jpg', 1200, 700)],
        ])->assertRedirect();

        $mediaBySlot = $this->mediaByPersistedSlot($scene->fresh());

        $this->assertArrayHasKey(1, $mediaBySlot);
        $this->assertArrayHasKey(2, $mediaBySlot);
        $this->assertSame((int) $slotTwoMedia->id, (int) $mediaBySlot[2]->id);
        $this->assertNotSame((int) $slotTwoMedia->id, (int) $mediaBySlot[1]->id);
    }

    public function test_scene_title_image_and_content_images_remain_separate(): void
    {
        [$campaign, $gm] = $this->seedCampaignContext();

        $response = $this->actingAs($gm)->post(route('campaigns.scenes.store', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]), [
            ...$this->scenePayload(['title' => 'Bildtrennung']),
            'header_image' => UploadedFile::fake()->image('title.jpg', 1600, 600),
            'content_images' => [UploadedFile::fake()->image('content.jpg', 1200, 700)],
        ]);

        $scene = Scene::query()
            ->where('campaign_id', $campaign->id)
            ->where('title', 'Bildtrennung')
            ->firstOrFail();

        $response->assertRedirect();
        $scene->refresh();

        $this->assertNotNull($scene->header_image_path);
        $this->assertCount(1, $scene->getMedia(Scene::CONTENT_IMAGES_COLLECTION));
        $this->assertDatabaseHas('media', [
            'model_type' => Scene::class,
            'model_id' => $scene->id,
            'collection_name' => Scene::CONTENT_IMAGES_COLLECTION,
        ]);
    }

    /**
     * @param  array<string, mixed>  $sceneAttributes
     * @return array{0: Campaign, 1: User, 2: Scene}
     */
    private function seedCampaignSceneContext(array $sceneAttributes = []): array
    {
        [$campaign, $gm] = $this->seedCampaignContext();

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'title' => 'Testszene',
            'slug' => 'testszene',
            'status' => 'open',
            'mood' => 'neutral',
            'allow_ooc' => true,
            ...$sceneAttributes,
        ]);

        return [$campaign, $gm, $scene];
    }

    /**
     * @return array{0: Campaign, 1: User}
     */
    private function seedCampaignContext(): array
    {
        $gm = User::factory()->gm()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
            'requires_post_moderation' => false,
        ]);

        return [$campaign, $gm];
    }

    /**
     * @param  array<string, mixed>|Scene  $overrides
     * @return array<string, mixed>
     */
    private function scenePayload(array|Scene $overrides = []): array
    {
        $base = [
            'title' => 'Neue Szene',
            'slug' => 'neue-szene',
            'previous_scene_id' => '',
            'summary' => 'Kurzer Teaser',
            'description' => "Szenerie.\n\nAusgangslage.",
            'status' => 'open',
            'mood' => 'neutral',
            'position' => 1,
            'allow_ooc' => '1',
            'opens_at' => '',
            'closes_at' => '',
        ];

        if ($overrides instanceof Scene) {
            return [
                ...$base,
                'title' => (string) $overrides->title,
                'slug' => (string) $overrides->slug,
                'summary' => (string) ($overrides->summary ?? ''),
                'description' => (string) ($overrides->description ?? ''),
                'status' => (string) $overrides->status,
                'mood' => (string) $overrides->mood,
                'position' => (int) $overrides->position,
                'allow_ooc' => $overrides->allow_ooc ? '1' : '0',
            ];
        }

        return [
            ...$base,
            ...$overrides,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, Media>
     */
    private function attachContentImages(Scene $scene, int $count)
    {
        for ($index = 1; $index <= $count; $index++) {
            $scene
                ->addMedia(UploadedFile::fake()->image("scene-content-{$scene->id}-{$index}.jpg", 1200, 700))
                ->withCustomProperties(['slot' => $index])
                ->toMediaCollection(Scene::CONTENT_IMAGES_COLLECTION);
        }

        return $scene->fresh()->getMedia(Scene::CONTENT_IMAGES_COLLECTION);
    }

    private function assertInlineImageSlotContains(string $html, int $slot, string $mediaUrl): void
    {
        $this->assertMatchesRegularExpression(
            '/<figure[^>]*data-scene-inline-image-slot="'.$slot.'"[^>]*>.*?<a[^>]*href="'.preg_quote($mediaUrl, '/').'"[^>]*target="_blank"[^>]*rel="noopener noreferrer"[^>]*>.*?<img[^>]*src="'.preg_quote($mediaUrl, '/').'"/s',
            $html,
        );
    }

    private function assertGalleryImageContains(string $html, int $mediaId, string $mediaUrl): void
    {
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="'.preg_quote($mediaUrl, '/').'"[^>]*target="_blank"[^>]*rel="noopener noreferrer"[^>]*>.*?<img[^>]*data-scene-content-gallery-image="1"[^>]*data-scene-media-id="'.$mediaId.'"[^>]*src="'.preg_quote($mediaUrl, '/').'"/s',
            $html,
        );
    }

    /**
     * @return array<int, Media>
     */
    private function mediaByPersistedSlot(Scene $scene): array
    {
        $mediaBySlot = [];

        foreach ($scene->getMedia(Scene::CONTENT_IMAGES_COLLECTION) as $media) {
            $slot = (int) $media->getCustomProperty('slot');

            if ($slot <= 0) {
                continue;
            }

            $mediaBySlot[$slot] = $media;
        }

        ksort($mediaBySlot);

        return $mediaBySlot;
    }
}
