<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Handout;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class AuditOrphanMediaCommandTest extends TestCase
{
    use RefreshDatabase;

    private const COMMAND = 'rpg:audit-orphan-media';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_detects_database_orphan_for_post_media(): void
    {
        $orphan = $this->createMediaRow([
            'model_type' => Post::class,
            'model_id' => 999_001,
            'disk' => 'public',
            'file_name' => 'post-orphan.jpg',
        ]);

        $payload = $this->runJsonAudit();

        $this->assertSame(1, $payload['summary']['orphan_post_media_rows_count']);
        $this->assertSame(0, $payload['summary']['orphan_handout_media_rows_count']);
        $this->assertSame((int) $orphan->id, $payload['orphans']['post'][0]['media_id']);
        $this->assertSame(Post::class, $payload['orphans']['post'][0]['model_type']);
        $this->assertSame('no', $payload['orphans']['post'][0]['physical_file_exists']);
    }

    public function test_detects_database_orphan_for_handout_media(): void
    {
        $orphan = $this->createMediaRow([
            'model_type' => Handout::class,
            'model_id' => 999_002,
            'disk' => 'local',
            'file_name' => 'handout-orphan.jpg',
        ]);

        $payload = $this->runJsonAudit();

        $this->assertSame(0, $payload['summary']['orphan_post_media_rows_count']);
        $this->assertSame(1, $payload['summary']['orphan_handout_media_rows_count']);
        $this->assertSame((int) $orphan->id, $payload['orphans']['handout'][0]['media_id']);
        $this->assertSame(Handout::class, $payload['orphans']['handout'][0]['model_type']);
        $this->assertSame('no', $payload['orphans']['handout'][0]['physical_file_exists']);
    }

    public function test_media_rows_with_existing_post_and_handout_are_not_counted_as_orphans(): void
    {
        $post = Post::factory()->create();
        $handout = Handout::factory()->create();

        $this->createMediaRow([
            'model_type' => Post::class,
            'model_id' => (int) $post->id,
            'disk' => 'public',
            'file_name' => 'post-linked.jpg',
        ]);
        $this->createMediaRow([
            'model_type' => Handout::class,
            'model_id' => (int) $handout->id,
            'disk' => 'local',
            'file_name' => 'handout-linked.jpg',
        ]);

        $payload = $this->runJsonAudit();

        $this->assertSame(0, $payload['summary']['orphan_post_media_rows_count']);
        $this->assertSame(0, $payload['summary']['orphan_handout_media_rows_count']);
        $this->assertSame(0, $payload['summary']['missing_physical_file_count']);
        $this->assertSame(0, $payload['summary']['existing_physical_orphan_file_count']);
    }

    public function test_command_does_not_modify_media_rows(): void
    {
        $orphan = $this->createMediaRow([
            'model_type' => Post::class,
            'model_id' => 999_003,
            'disk' => 'public',
            'file_name' => 'immutable-orphan.jpg',
        ]);

        $before = DB::table('media')
            ->where('id', (int) $orphan->id)
            ->first();
        $this->assertNotNull($before);

        $this->artisan(self::COMMAND)->assertExitCode(0);

        $after = DB::table('media')
            ->where('id', (int) $orphan->id)
            ->first();
        $this->assertNotNull($after);

        $this->assertEquals($before, $after);
        $this->assertSame(1, Media::query()->count());
    }

    public function test_command_does_not_delete_physical_files(): void
    {
        $orphan = $this->createMediaRow([
            'model_type' => Post::class,
            'model_id' => 999_004,
            'disk' => 'public',
            'file_name' => 'keep-me.jpg',
        ]);

        $path = $orphan->getPathRelativeToRoot();
        Storage::disk('public')->put($path, 'keep');
        $this->assertTrue(Storage::disk('public')->exists($path));

        $payload = $this->runJsonAudit();

        $this->assertTrue(Storage::disk('public')->exists($path));
        $this->assertSame(1, $payload['summary']['orphan_post_media_rows_count']);
        $this->assertSame(0, $payload['summary']['missing_physical_file_count']);
        $this->assertSame(1, $payload['summary']['existing_physical_orphan_file_count']);
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
            'name' => 'Audit Test Media',
            'file_name' => 'audit-test.jpg',
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
     * @return array{
     *   generated_at: string,
     *   orphans: array{
     *     post: array<int, array<string, mixed>>,
     *     handout: array<int, array<string, mixed>>
     *   },
     *   summary: array<string, mixed>,
     *   note: string
     * }
     */
    private function runJsonAudit(): array
    {
        $exitCode = Artisan::call(self::COMMAND, [
            '--json' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $decoded = json_decode(Artisan::output(), true);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
