<?php

namespace Tests\Feature\MySqlConcurrency;

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[Group('mysql-concurrency')]
class CampaignInvitationDuplicateKeyMysqlTest extends TestCase
{
    public function test_invitation_upsert_recovers_from_mysql_duplicate_key_violation_1062(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL-only duplicate-key fallback test.');
        }

        $owner = User::factory()->gm()->create();
        $invitee = User::factory()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);

        $workerScript = base_path('tests/Support/Concurrency/invitation_upsert_worker.php');
        $process = new Process([
            PHP_BINARY,
            $workerScript,
            (string) $campaign->id,
            (string) $invitee->id,
            (string) $owner->id,
            CampaignInvitation::ROLE_CO_GM,
            '1',
        ]);

        $process->mustRun();
        $result = $this->decodeWorkerOutput($process->getOutput(), $process->getErrorOutput());

        $this->assertSame('ok', $result['status'] ?? null);
        $this->assertTrue((bool) ($result['duplicate_injected'] ?? false), 'Duplicate path was not injected.');
        $this->assertSame(1, CampaignInvitation::query()
            ->where('campaign_id', $campaign->id)
            ->where('user_id', $invitee->id)
            ->count());
        $this->assertDatabaseHas('campaign_invitations', [
            'campaign_id' => $campaign->id,
            'user_id' => $invitee->id,
            'status' => CampaignInvitation::STATUS_PENDING,
            'role' => CampaignInvitation::ROLE_CO_GM,
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
