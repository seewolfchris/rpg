<?php

namespace Tests\Feature\MySqlConcurrency;

use App\Models\Campaign;
use App\Models\Scene;
use App\Models\SceneBookmark;
use App\Models\User;
use App\Models\World;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[Group('mysql-concurrency')]
class SceneBookmarkDuplicateKeyMysqlTest extends TestCase
{
    public function test_create_bookmark_recovers_from_mysql_duplicate_key_violation_1062(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL-only duplicate-key fallback test.');
        }

        $world = World::factory()->create(['is_active' => true]);
        $owner = User::factory()->gm()->create();
        $user = User::factory()->create();
        $campaign = Campaign::factory()->create([
            'world_id' => $world->id,
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $workerScript = base_path('tests/Support/Concurrency/scene_bookmark_create_worker.php');
        $process = new Process([
            PHP_BINARY,
            $workerScript,
            (string) $world->id,
            (string) $campaign->id,
            (string) $scene->id,
            (string) $user->id,
            '0',
            '  Race Marker  ',
            '1',
        ]);

        $process->mustRun();
        $result = $this->decodeWorkerOutput($process->getOutput(), $process->getErrorOutput());

        $this->assertSame('ok', $result['status'] ?? null);
        $this->assertTrue((bool) ($result['duplicate_injected'] ?? false), 'Duplicate path was not injected.');
        $this->assertSame(1, SceneBookmark::query()
            ->where('user_id', $user->id)
            ->where('scene_id', $scene->id)
            ->count());
        $this->assertDatabaseHas('scene_bookmarks', [
            'user_id' => $user->id,
            'scene_id' => $scene->id,
            'label' => 'Race Marker',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeWorkerOutput(string $stdout, string $stderr): array
    {
        $payload = trim($stdout);
        $this->assertNotSame('', $payload, 'Worker stdout was empty. stderr: '.$stderr);

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($payload, true);
        $this->assertIsArray($decoded, 'Worker output is not valid JSON: '.$payload);

        return $decoded;
    }
}

