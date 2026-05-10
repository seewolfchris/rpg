<?php

namespace Tests\Feature\MySqlConcurrency;

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\CampaignMembership;
use App\Models\CampaignRoleEvent;
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

    public function test_parallel_upserts_converge_to_single_pending_invitation_and_no_membership_side_effects(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL-only concurrency test.');
        }

        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => (int) $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);

        $workerScript = base_path('tests/Support/Concurrency/invitation_upsert_worker.php');
        $playerProcess = new Process([
            PHP_BINARY,
            $workerScript,
            (string) $campaign->id,
            (string) $invitee->id,
            (string) $owner->id,
            CampaignInvitation::ROLE_PLAYER,
            '0',
            '220',
        ]);
        $trustedProcess = new Process([
            PHP_BINARY,
            $workerScript,
            (string) $campaign->id,
            (string) $invitee->id,
            (string) $owner->id,
            CampaignInvitation::ROLE_TRUSTED_PLAYER,
            '0',
            '220',
        ]);

        $playerProcess->start();
        $trustedProcess->start();

        $playerProcess->wait();
        $trustedProcess->wait();

        $this->assertSame(0, $playerProcess->getExitCode(), $playerProcess->getErrorOutput());
        $this->assertSame(0, $trustedProcess->getExitCode(), $trustedProcess->getErrorOutput());

        $playerResult = $this->decodeWorkerOutput($playerProcess->getOutput(), $playerProcess->getErrorOutput());
        $trustedResult = $this->decodeWorkerOutput($trustedProcess->getOutput(), $trustedProcess->getErrorOutput());

        $this->assertSame('ok', $playerResult['status'] ?? null);
        $this->assertSame('ok', $trustedResult['status'] ?? null);

        $latestStart = max((float) ($playerResult['started_at'] ?? 0), (float) ($trustedResult['started_at'] ?? 0));
        $earliestFinish = min((float) ($playerResult['finished_at'] ?? 0), (float) ($trustedResult['finished_at'] ?? 0));
        $this->assertTrue($latestStart < $earliestFinish, 'Worker processes did not overlap in execution window.');

        $this->assertSame(
            1,
            CampaignInvitation::query()
                ->where('campaign_id', (int) $campaign->id)
                ->where('user_id', (int) $invitee->id)
                ->count()
        );

        $isNewCount = (($playerResult['is_new'] ?? false) ? 1 : 0)
            + (($trustedResult['is_new'] ?? false) ? 1 : 0);
        $this->assertSame(1, $isNewCount, 'Exactly one parallel upsert should create a new invitation row.');

        $invitation = CampaignInvitation::query()
            ->where('campaign_id', (int) $campaign->id)
            ->where('user_id', (int) $invitee->id)
            ->firstOrFail();

        $this->assertSame(CampaignInvitation::STATUS_PENDING, (string) $invitation->status);
        $this->assertContains((string) $invitation->role, [
            CampaignInvitation::ROLE_PLAYER,
            CampaignInvitation::ROLE_TRUSTED_PLAYER,
        ]);
        $this->assertNull($invitation->accepted_at);
        $this->assertNull($invitation->responded_at);
        $this->assertSame((int) $owner->id, (int) $invitation->invited_by);

        $membershipCount = CampaignMembership::query()
            ->where('campaign_id', (int) $campaign->id)
            ->where('user_id', (int) $invitee->id)
            ->count();
        $this->assertSame(0, $membershipCount, 'Pending invitation upserts must not create memberships.');

        $roleEventCount = CampaignRoleEvent::query()
            ->where('campaign_id', (int) $campaign->id)
            ->where('target_user_id', (int) $invitee->id)
            ->whereIn('event_type', [
                CampaignRoleEvent::EVENT_MEMBERSHIP_GRANTED,
                CampaignRoleEvent::EVENT_MEMBERSHIP_ROLE_CHANGED,
                CampaignRoleEvent::EVENT_MEMBERSHIP_REVOKED,
            ])
            ->count();
        $this->assertSame(0, $roleEventCount, 'Pending invitation upserts must not emit membership role events.');
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
