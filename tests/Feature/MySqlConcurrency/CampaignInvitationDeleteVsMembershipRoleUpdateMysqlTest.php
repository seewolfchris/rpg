<?php

declare(strict_types=1);

namespace Tests\Feature\MySqlConcurrency;

use App\Enums\CampaignMembershipRole;
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
class CampaignInvitationDeleteVsMembershipRoleUpdateMysqlTest extends TestCase
{
    public function test_parallel_delete_of_accepted_invitation_and_membership_role_update_converges_consistently(): void
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

        $invitation = CampaignInvitation::query()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $invitee->id,
            'invited_by' => (int) $owner->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'accepted_at' => now()->subMinute(),
            'responded_at' => now()->subMinute(),
            'created_at' => now()->subDay(),
        ]);

        $membership = CampaignMembership::factory()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $invitee->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => (int) $owner->id,
            'assigned_at' => now()->subMinute(),
        ]);

        $workerScript = base_path('tests/Support/Concurrency/campaign_invitation_delete_membership_update_worker.php');
        $worldSlug = (string) ($campaign->world?->slug ?? '');
        $this->assertNotSame('', $worldSlug, 'Campaign world slug is required for worker routing.');

        $deleteProcess = new Process([
            PHP_BINARY,
            $workerScript,
            'delete',
            $worldSlug,
            (string) $campaign->id,
            (string) $invitation->id,
            (string) $membership->id,
            (string) $owner->id,
            CampaignMembershipRole::TRUSTED_PLAYER->value,
            '240',
        ]);

        $updateProcess = new Process([
            PHP_BINARY,
            $workerScript,
            'update',
            $worldSlug,
            (string) $campaign->id,
            (string) $invitation->id,
            (string) $membership->id,
            (string) $owner->id,
            CampaignMembershipRole::TRUSTED_PLAYER->value,
            '240',
        ]);

        $deleteProcess->start();
        $updateProcess->start();

        $deleteProcess->wait();
        $updateProcess->wait();

        $this->assertSame(0, $deleteProcess->getExitCode(), $deleteProcess->getErrorOutput());
        $this->assertSame(0, $updateProcess->getExitCode(), $updateProcess->getErrorOutput());

        $deleteResult = $this->decodeWorkerOutput($deleteProcess->getOutput(), $deleteProcess->getErrorOutput());
        $updateResult = $this->decodeWorkerOutput($updateProcess->getOutput(), $updateProcess->getErrorOutput());

        $this->assertSame(302, (int) ($deleteResult['http_status'] ?? 0), 'Delete worker did not complete expected redirect flow.');
        $this->assertContains((int) ($updateResult['http_status'] ?? 0), [302, 404], 'Update worker returned unexpected HTTP status.');

        $this->assertNotSame('', (string) ($deleteResult['location'] ?? ''), 'Delete worker missing redirect location.');
        $this->assertStringNotContainsString('/login', (string) ($deleteResult['location'] ?? ''), 'Delete worker was redirected to login.');

        if ((int) ($updateResult['http_status'] ?? 0) === 302) {
            $this->assertNotSame('', (string) ($updateResult['location'] ?? ''), 'Update worker missing redirect location on successful mutation path.');
            $this->assertStringNotContainsString('/login', (string) ($updateResult['location'] ?? ''), 'Update worker was redirected to login.');
        }

        $latestStart = max((float) $deleteResult['started_at'], (float) $updateResult['started_at']);
        $earliestFinish = min((float) $deleteResult['finished_at'], (float) $updateResult['finished_at']);
        $this->assertTrue($latestStart < $earliestFinish, 'Worker processes did not overlap in execution window.');

        $this->assertSame(
            0,
            CampaignInvitation::query()
                ->where('campaign_id', (int) $campaign->id)
                ->where('user_id', (int) $invitee->id)
                ->count(),
            'Accepted invitation should be deleted after revocation flow.'
        );

        $membershipCount = CampaignMembership::query()
            ->where('campaign_id', (int) $campaign->id)
            ->where('user_id', (int) $invitee->id)
            ->count();

        $this->assertLessThanOrEqual(1, $membershipCount, 'Race must not produce duplicate memberships.');
        $this->assertSame(0, $membershipCount, 'Final membership should be revoked once accepted invitation deletion succeeds.');

        $roleEvents = CampaignRoleEvent::query()
            ->where('campaign_id', (int) $campaign->id)
            ->where('target_user_id', (int) $invitee->id)
            ->whereIn('event_type', [
                CampaignRoleEvent::EVENT_MEMBERSHIP_ROLE_CHANGED,
                CampaignRoleEvent::EVENT_MEMBERSHIP_REVOKED,
                CampaignRoleEvent::EVENT_MEMBERSHIP_GRANTED,
            ])
            ->orderBy('id')
            ->get();

        $membershipGrantedCount = $roleEvents
            ->where('event_type', CampaignRoleEvent::EVENT_MEMBERSHIP_GRANTED)
            ->count();
        $this->assertSame(0, $membershipGrantedCount, 'No membership_granted event expected in delete-vs-update race.');

        $roleChangedEvents = $roleEvents
            ->where('event_type', CampaignRoleEvent::EVENT_MEMBERSHIP_ROLE_CHANGED)
            ->where('source', 'campaign_membership_role_update')
            ->values();
        $this->assertLessThanOrEqual(1, $roleChangedEvents->count(), 'Role update event should be present at most once.');

        if ((int) ($updateResult['http_status'] ?? 0) === 302) {
            $this->assertSame(1, $roleChangedEvents->count(), 'Successful role update should emit exactly one role_changed event.');
            $roleChangedEvent = $roleChangedEvents->first();
            $this->assertNotNull($roleChangedEvent);
            $this->assertSame(CampaignMembershipRole::PLAYER->value, (string) $roleChangedEvent->old_role);
            $this->assertSame(CampaignMembershipRole::TRUSTED_PLAYER->value, (string) $roleChangedEvent->new_role);
        } else {
            $this->assertSame(0, $roleChangedEvents->count(), '404 role update should not emit role_changed event.');
        }

        $revocationEvents = $roleEvents
            ->where('event_type', CampaignRoleEvent::EVENT_MEMBERSHIP_REVOKED)
            ->where('source', 'invitation_delete_accepted')
            ->values();
        $this->assertSame(1, $revocationEvents->count(), 'Revocation event should be emitted exactly once.');

        $revocationEvent = $revocationEvents->first();
        $this->assertNotNull($revocationEvent);
        $this->assertSame($owner->id, (int) $revocationEvent->actor_user_id);
        $this->assertContains((string) $revocationEvent->old_role, [
            CampaignMembershipRole::PLAYER->value,
            CampaignMembershipRole::TRUSTED_PLAYER->value,
        ]);
        $this->assertNull($revocationEvent->new_role);
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
