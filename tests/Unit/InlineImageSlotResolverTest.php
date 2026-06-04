<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Support\InlineImageSlotResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InlineImageSlotResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);
    }

    public function test_first_valid_slot_wins_and_invalid_or_duplicate_slots_fall_back_for_display_only(): void
    {
        $post = $this->createPost();
        $firstSlotTwo = $this->attachMedia($post, 'first-slot-two.jpg', ['slot' => 2]);
        $duplicateSlotTwo = $this->attachMedia($post, 'duplicate-slot-two.jpg', ['slot' => 2]);
        $invalidSlot = $this->attachMedia($post, 'invalid-slot.jpg', ['slot' => 9]);
        $withoutSlot = $this->attachMedia($post, 'without-slot.jpg');

        $resolution = app(InlineImageSlotResolver::class)
            ->resolve($post->fresh()->getMedia(Post::IMMERSIVE_IMAGES_COLLECTION));

        $this->assertSame(2, $resolution->slotFor($firstSlotTwo));
        $this->assertSame(1, $resolution->slotFor($duplicateSlotTwo));
        $this->assertSame(3, $resolution->slotFor($invalidSlot));
        $this->assertSame(4, $resolution->slotFor($withoutSlot));

        $duplicateSlotTwo->refresh();
        $invalidSlot->refresh();
        $withoutSlot->refresh();

        $this->assertSame(2, $duplicateSlotTwo->getCustomProperty('slot'));
        $this->assertSame(9, $invalidSlot->getCustomProperty('slot'));
        $this->assertNull($withoutSlot->getCustomProperty('slot'));
    }

    public function test_media_beyond_four_slots_remain_without_display_slot(): void
    {
        $post = $this->createPost();
        $this->attachMedia($post, 'one.jpg');
        $this->attachMedia($post, 'two.jpg');
        $this->attachMedia($post, 'three.jpg');
        $this->attachMedia($post, 'four.jpg');
        $fifth = $this->attachMedia($post, 'five.jpg');

        $resolution = app(InlineImageSlotResolver::class)
            ->resolve($post->fresh()->getMedia(Post::IMMERSIVE_IMAGES_COLLECTION));

        $this->assertSame([1, 2, 3, 4], $resolution->occupiedSlots());
        $this->assertNull($resolution->slotFor($fifth));
    }

    private function createPost(): Post
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

        return Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
            'character_id' => null,
            'post_type' => 'ic',
            'content_format' => 'plain',
            'content' => 'Die Spielleitung beschreibt die Umgebung.',
            'meta' => ['author_role' => 'gm'],
            'moderation_status' => 'approved',
        ]);
    }

    /**
     * @param  array<string, mixed>  $customProperties
     */
    private function attachMedia(Post $post, string $fileName, array $customProperties = []): \Spatie\MediaLibrary\MediaCollections\Models\Media
    {
        $adder = $post->addMedia(UploadedFile::fake()->image($fileName, 1200, 700));

        if ($customProperties !== []) {
            $adder->withCustomProperties($customProperties);
        }

        return $adder->toMediaCollection(Post::IMMERSIVE_IMAGES_COLLECTION);
    }
}
