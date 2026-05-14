<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Handout;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class CleanupOrphanMediaCommandTest extends TestCase
{
    use RefreshDatabase;

    private const COMMAND = 'rpg:cleanup-orphan-media';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_dry_run_does_not_delete_media_rows(): void
    {
        $postOrphan = $this->createMediaRow([
            'model_type' => Post::class,
            'model_id' => 900_001,
            'disk' => 'public',
        ]);
        $handoutOrphan = $this->createMediaRow([
            'model_type' => Handout::class,
            'model_id' => 900_002,
            'disk' => 'local',
        ]);

        $payload = $this->runCommand();

        $this->assertDatabaseHas('media', ['id' => (int) $postOrphan->id]);
        $this->assertDatabaseHas('media', ['id' => (int) $handoutOrphan->id]);
        $this->assertSame('dry-run', $payload['summary']['mode']);
        $this->assertSame(0, $payload['summary']['deleted_count']);
    }

    public function test_dry_run_does_not_delete_files(): void
    {
        $postOrphan = $this->createMediaRow([
            'model_type' => Post::class,
            'model_id' => 900_003,
            'disk' => 'public',
            'file_name' => 'dry-run-keep-post.jpg',
        ]);
        $handoutOrphan = $this->createMediaRow([
            'model_type' => Handout::class,
            'model_id' => 900_004,
            'disk' => 'local',
            'file_name' => 'dry-run-keep-handout.jpg',
        ]);

        $postPath = $postOrphan->getPathRelativeToRoot();
        $handoutPath = $handoutOrphan->getPathRelativeToRoot();

        Storage::disk('public')->put($postPath, 'keep-post');
        Storage::disk('local')->put($handoutPath, 'keep-handout');

        $this->assertTrue(Storage::disk('public')->exists($postPath));
        $this->assertTrue(Storage::disk('local')->exists($handoutPath));

        $this->runCommand(['--dry-run' => true]);

        $this->assertTrue(Storage::disk('public')->exists($postPath));
        $this->assertTrue(Storage::disk('local')->exists($handoutPath));
    }

    public function test_execute_deletes_orphan_post_media_row_and_file(): void
    {
        $orphan = $this->createMediaRow([
            'model_type' => Post::class,
            'model_id' => 900_005,
            'disk' => 'public',
            'file_name' => 'delete-post.jpg',
        ]);
        $path = $orphan->getPathRelativeToRoot();
        Storage::disk('public')->put($path, 'delete-me-post');

        $this->assertDatabaseHas('media', ['id' => (int) $orphan->id]);
        $this->assertTrue(Storage::disk('public')->exists($path));

        $payload = $this->runCommand(['--execute' => true]);

        $this->assertDatabaseMissing('media', ['id' => (int) $orphan->id]);
        $this->assertFalse(Storage::disk('public')->exists($path));
        $this->assertSame('execute', $payload['summary']['mode']);
        $this->assertSame(1, $payload['summary']['deleted_count']);
        $this->assertSame(0, $payload['summary']['failed_count']);
    }

    public function test_execute_deletes_orphan_handout_media_row_and_file(): void
    {
        $orphan = $this->createMediaRow([
            'model_type' => Handout::class,
            'model_id' => 900_006,
            'disk' => 'local',
            'file_name' => 'delete-handout.jpg',
        ]);
        $path = $orphan->getPathRelativeToRoot();
        Storage::disk('local')->put($path, 'delete-me-handout');

        $this->assertDatabaseHas('media', ['id' => (int) $orphan->id]);
        $this->assertTrue(Storage::disk('local')->exists($path));

        $payload = $this->runCommand(['--execute' => true]);

        $this->assertDatabaseMissing('media', ['id' => (int) $orphan->id]);
        $this->assertFalse(Storage::disk('local')->exists($path));
        $this->assertSame('execute', $payload['summary']['mode']);
        $this->assertSame(1, $payload['summary']['deleted_count']);
        $this->assertSame(0, $payload['summary']['failed_count']);
    }

    public function test_execute_does_not_delete_media_with_existing_post(): void
    {
        $post = Post::factory()->create();
        $media = $this->createMediaRow([
            'model_type' => Post::class,
            'model_id' => (int) $post->id,
            'disk' => 'public',
            'file_name' => 'linked-post.jpg',
        ]);
        $path = $media->getPathRelativeToRoot();
        Storage::disk('public')->put($path, 'keep-linked-post');

        $payload = $this->runCommand(['--execute' => true]);

        $this->assertDatabaseHas('media', ['id' => (int) $media->id]);
        $this->assertTrue(Storage::disk('public')->exists($path));
        $this->assertSame(0, $payload['summary']['found_orphan_post_media_count']);
        $this->assertSame(0, $payload['summary']['deleted_count']);
    }

    public function test_execute_does_not_delete_media_with_existing_handout(): void
    {
        $handout = Handout::factory()->create();
        $media = $this->createMediaRow([
            'model_type' => Handout::class,
            'model_id' => (int) $handout->id,
            'disk' => 'local',
            'file_name' => 'linked-handout.jpg',
        ]);
        $path = $media->getPathRelativeToRoot();
        Storage::disk('local')->put($path, 'keep-linked-handout');

        $payload = $this->runCommand(['--execute' => true]);

        $this->assertDatabaseHas('media', ['id' => (int) $media->id]);
        $this->assertTrue(Storage::disk('local')->exists($path));
        $this->assertSame(0, $payload['summary']['found_orphan_handout_media_count']);
        $this->assertSame(0, $payload['summary']['deleted_count']);
    }

    public function test_execute_ignores_other_model_types(): void
    {
        $foreign = $this->createMediaRow([
            'model_type' => 'App\\Models\\Campaign',
            'model_id' => 900_007,
            'disk' => 'public',
            'file_name' => 'foreign-type.jpg',
        ]);
        $path = $foreign->getPathRelativeToRoot();
        Storage::disk('public')->put($path, 'keep-foreign');

        $payload = $this->runCommand(['--execute' => true]);

        $this->assertDatabaseHas('media', ['id' => (int) $foreign->id]);
        $this->assertTrue(Storage::disk('public')->exists($path));
        $this->assertSame(0, $payload['summary']['found_orphan_post_media_count']);
        $this->assertSame(0, $payload['summary']['found_orphan_handout_media_count']);
        $this->assertSame(0, $payload['summary']['deleted_count']);
    }

    public function test_execute_is_idempotent_on_second_run(): void
    {
        $postOrphan = $this->createMediaRow([
            'model_type' => Post::class,
            'model_id' => 900_008,
            'disk' => 'public',
            'file_name' => 'idempotent-post.jpg',
        ]);
        $handoutOrphan = $this->createMediaRow([
            'model_type' => Handout::class,
            'model_id' => 900_009,
            'disk' => 'local',
            'file_name' => 'idempotent-handout.jpg',
        ]);

        Storage::disk('public')->put($postOrphan->getPathRelativeToRoot(), 'delete-idempotent-post');
        Storage::disk('local')->put($handoutOrphan->getPathRelativeToRoot(), 'delete-idempotent-handout');

        $first = $this->runCommand(['--execute' => true]);
        $second = $this->runCommand(['--execute' => true]);

        $this->assertSame(2, $first['summary']['deleted_count']);
        $this->assertSame(0, $second['summary']['found_orphan_post_media_count']);
        $this->assertSame(0, $second['summary']['found_orphan_handout_media_count']);
        $this->assertSame(0, $second['summary']['deleted_count']);
        $this->assertSame(0, Media::query()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createMediaRow(array $overrides = []): Media
    {
        return Media::query()->create(array_merge([
            'model_type' => Post::class,
            'model_id' => 123_456,
            'collection_name' => 'audit_test',
            'name' => 'Cleanup Test Media',
            'file_name' => 'cleanup-test.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => 1024,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{
     *   generated_at: string,
     *   summary: array{
     *     found_orphan_post_media_count: int,
     *     found_orphan_handout_media_count: int,
     *     deleted_count: int,
     *     failed_count: int,
     *     skipped_count: int,
     *     mode: string
     *   },
     *   orphans: array{
     *     post: array<int, array<string, mixed>>,
     *     handout: array<int, array<string, mixed>>
     *   },
     *   actions: array<int, array<string, mixed>>,
     *   note: string
     * }
     */
    private function runCommand(array $options = []): array
    {
        $exitCode = Artisan::call(self::COMMAND, array_merge([
            '--json' => true,
        ], $options));

        $this->assertSame(0, $exitCode);

        $decoded = json_decode(Artisan::output(), true);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
