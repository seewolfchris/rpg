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
class InvitationResponseParallelMysqlTest extends TestCase
{
    public function test_parallel_accept_and_decline_keep_invitation_state_consistent(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL-only concurrency test.');
        }

        $owner = User::factory()->gm()->create();
        $invitee = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);

        $invitation = CampaignInvitation::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $invitee->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_PENDING,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'accepted_at' => null,
            'responded_at' => null,
            'created_at' => now(),
        ]);

        $workerScript = base_path('tests/Support/Concurrency/invitation_response_worker.php');

        $acceptProcess = new Process([
            PHP_BINARY,
            $workerScript,
            (string) $invitation->id,
            (string) $invitee->id,
            CampaignInvitation::STATUS_ACCEPTED,
            '220',
        ]);
        $declineProcess = new Process([
            PHP_BINARY,
            $workerScript,
            (string) $invitation->id,
            (string) $invitee->id,
            CampaignInvitation::STATUS_DECLINED,
            '220',
        ]);

        $acceptProcess->start();
        $declineProcess->start();

        $acceptProcess->wait();
        $declineProcess->wait();

        $this->assertSame(0, $acceptProcess->getExitCode(), $acceptProcess->getErrorOutput());
        $this->assertSame(0, $declineProcess->getExitCode(), $declineProcess->getErrorOutput());

        $acceptResult = $this->decodeWorkerOutput($acceptProcess->getOutput(), $acceptProcess->getErrorOutput());
        $declineResult = $this->decodeWorkerOutput($declineProcess->getOutput(), $declineProcess->getErrorOutput());

        $this->assertSame(302, (int) ($acceptResult['http_status'] ?? 0), 'Accept worker did not traverse HTTP redirect flow.');
        $this->assertSame(302, (int) ($declineResult['http_status'] ?? 0), 'Decline worker did not traverse HTTP redirect flow.');
        $this->assertNotSame('', (string) ($acceptResult['location'] ?? ''), 'Accept worker missing redirect location.');
        $this->assertNotSame('', (string) ($declineResult['location'] ?? ''), 'Decline worker missing redirect location.');
        $this->assertStringNotContainsString('/login', (string) ($acceptResult['location'] ?? ''), 'Accept worker was redirected to login instead of invitation flow.');
        $this->assertStringNotContainsString('/login', (string) ($declineResult['location'] ?? ''), 'Decline worker was redirected to login instead of invitation flow.');

        $updatedCount = (
            (($acceptResult['status'] ?? '') === 'updated' ? 1 : 0)
            + (($declineResult['status'] ?? '') === 'updated' ? 1 : 0)
        );
        $alreadyClosedCount = (
            (($acceptResult['status'] ?? '') === 'already_closed' ? 1 : 0)
            + (($declineResult['status'] ?? '') === 'already_closed' ? 1 : 0)
        );

        $this->assertSame(1, $updatedCount, 'Exactly one parallel invitation response may persist.');
        $this->assertSame(1, $alreadyClosedCount, 'The second parallel response must observe a closed invitation.');

        $latestStart = max((float) $acceptResult['started_at'], (float) $declineResult['started_at']);
        $earliestFinish = min((float) $acceptResult['finished_at'], (float) $declineResult['finished_at']);
        $this->assertTrue($latestStart < $earliestFinish, 'Worker processes did not overlap in execution window.');

        $invitation->refresh();

        $this->assertContains($invitation->status, [
            CampaignInvitation::STATUS_ACCEPTED,
            CampaignInvitation::STATUS_DECLINED,
        ]);
        $this->assertNotNull($invitation->responded_at);

        if ($invitation->status === CampaignInvitation::STATUS_ACCEPTED) {
            $this->assertNotNull($invitation->accepted_at);
        } else {
            $this->assertNull($invitation->accepted_at);
        }
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
