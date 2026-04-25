<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Tests\TestCase;

class MediaLibraryFoundationTest extends TestCase
{
    public function test_media_library_disk_name_config_is_defined(): void
    {
        $diskName = config('media-library.disk_name');

        $this->assertIsString($diskName);
        $this->assertNotSame('', trim($diskName));
    }

    public function test_media_library_migration_is_published(): void
    {
        $migrationFiles = glob(database_path('migrations/*_create_media_table.php')) ?: [];

        $this->assertNotEmpty($migrationFiles, 'Expected published media library migration file.');
    }
}
