<?php

namespace Tests\Feature\MySqlConcurrency;

use App\Models\PushSubscription;
use App\Models\User;
use App\Models\World;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[Group('mysql-concurrency')]
class WebPushSubscriptionDuplicateKeyMysqlTest extends TestCase
{
    public function test_upsert_subscription_recovers_from_mysql_duplicate_key_violation_1062(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL-only duplicate-key fallback test.');
        }

        $user = User::factory()->create();
        $world = World::factory()->create([
            'slug' => 'webpush-race-world',
            'is_active' => true,
        ]);
        $endpoint = 'https://fcm.googleapis.com/fcm/send/webpush-race-endpoint';

        $workerScript = base_path('tests/Support/Concurrency/webpush_upsert_worker.php');
        $process = new Process([
            PHP_BINARY,
            $workerScript,
            (string) $user->id,
            (string) $world->id,
            $endpoint,
            '1',
        ]);

        $process->mustRun();
        $result = $this->decodeWorkerOutput($process->getOutput(), $process->getErrorOutput());

        $this->assertSame('ok', $result['status'] ?? null);
        $this->assertTrue((bool) ($result['duplicate_injected'] ?? false), 'Duplicate path was not injected.');
        $this->assertSame(1, PushSubscription::query()
            ->where('endpoint', $endpoint)
            ->count());
        $this->assertDatabaseHas('push_subscriptions', [
            'endpoint' => $endpoint,
            'user_id' => $user->id,
            'world_id' => $world->id,
            'public_key' => 'worker-key-new',
            'auth_token' => 'worker-token-new',
            'content_encoding' => 'aes128gcm',
            'subscribable_type' => $user->getMorphClass(),
            'subscribable_id' => $user->id,
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

