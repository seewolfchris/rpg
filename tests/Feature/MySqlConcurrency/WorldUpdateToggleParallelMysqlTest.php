<?php

namespace Tests\Feature\MySqlConcurrency;

use App\Models\World;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[Group('mysql-concurrency')]
class WorldUpdateToggleParallelMysqlTest extends TestCase
{
    public function test_parallel_world_update_and_toggle_keep_invariant_of_at_least_one_active_world(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL-only concurrency test.');
        }

        World::query()->update(['is_active' => false]);

        $worldA = World::factory()->create([
            'slug' => 'parallel-update-target',
            'is_active' => true,
            'position' => 3100,
        ]);
        $worldB = World::factory()->create([
            'slug' => 'parallel-toggle-target',
            'is_active' => true,
            'position' => 3110,
        ]);

        $workerScript = base_path('tests/Support/Concurrency/world_update_worker.php');
        $updatePayload = json_encode([
            'name' => (string) $worldA->name,
            'slug' => (string) $worldA->slug,
            'tagline' => (string) ($worldA->tagline ?? ''),
            'description' => (string) ($worldA->description ?? ''),
            'position' => (int) $worldA->position,
            'is_active' => false,
        ], JSON_THROW_ON_ERROR);

        $updateProcess = new Process([PHP_BINARY, $workerScript, 'update', (string) $worldA->id, $updatePayload, '250']);
        $toggleProcess = new Process([PHP_BINARY, $workerScript, 'toggle', (string) $worldB->id, '{}', '250']);

        $updateProcess->start();
        $toggleProcess->start();

        $updateProcess->wait();
        $toggleProcess->wait();

        $updateExitCode = (int) $updateProcess->getExitCode();
        $toggleExitCode = (int) $toggleProcess->getExitCode();
        $updateResult = $this->decodeWorkerOutput($updateProcess->getOutput(), $updateProcess->getErrorOutput());
        $toggleResult = $this->decodeWorkerOutput($toggleProcess->getOutput(), $toggleProcess->getErrorOutput());

        $this->assertContains($updateExitCode, [0, 20], 'Update worker failed unexpectedly: '.$updateProcess->getErrorOutput());
        $this->assertContains($toggleExitCode, [0, 20], 'Toggle worker failed unexpectedly: '.$toggleProcess->getErrorOutput());
        $this->assertSame(
            1,
            ($updateExitCode === 0 ? 1 : 0) + ($toggleExitCode === 0 ? 1 : 0),
            'Exactly one concurrent mutation must succeed to preserve invariants.'
        );

        $this->assertSame(1, World::query()->where('is_active', true)->count());

        $latestStart = max((float) $updateResult['started_at'], (float) $toggleResult['started_at']);
        $earliestFinish = min((float) $updateResult['finished_at'], (float) $toggleResult['finished_at']);
        $this->assertTrue(
            $latestStart < $earliestFinish,
            'Worker processes did not overlap in execution window.'
        );
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
