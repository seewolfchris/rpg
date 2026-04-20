<?php

namespace Tests\Feature\MySqlConcurrency;

use App\Models\Campaign;
use App\Models\Post;
use App\Models\PostReaction;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[Group('mysql-concurrency')]
class PostReactionDuplicateKeyMysqlTest extends TestCase
{
    public function test_create_reaction_recovers_from_mysql_duplicate_key_violation_1062(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL-only duplicate-key fallback test.');
        }

        $owner = User::factory()->gm()->create();
        $reactor = User::factory()->create();
        $world = World::factory()->create(['is_active' => true]);
        $campaign = Campaign::factory()->create([
            'world_id' => $world->id,
            'owner_id' => $owner->id,
            'status' => 'active',
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $owner->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'Race test post',
            'moderation_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $owner->id,
        ]);

        $workerScript = base_path('tests/Support/Concurrency/post_reaction_create_worker.php');
        $process = new Process([
            PHP_BINARY,
            $workerScript,
            (string) $world->id,
            (string) $post->id,
            (string) $reactor->id,
            'heart',
            '1',
        ]);

        $process->mustRun();
        $result = $this->decodeWorkerOutput($process->getOutput(), $process->getErrorOutput());

        $this->assertSame('ok', $result['status'] ?? null);
        $this->assertTrue((bool) ($result['duplicate_injected'] ?? false), 'Duplicate path was not injected.');
        $this->assertSame(1, PostReaction::query()
            ->where('post_id', $post->id)
            ->where('user_id', $reactor->id)
            ->where('emoji', 'heart')
            ->count());
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

