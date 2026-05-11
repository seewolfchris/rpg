<?php

declare(strict_types=1);

namespace Tests\Feature\MySqlConcurrency;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\Post;
use App\Models\PostModerationLog;
use App\Models\PostRevision;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[Group('mysql-concurrency')]
class PostModerationCollisionMysqlTest extends TestCase
{
    public function test_parallel_single_and_bulk_moderation_on_same_post_converge_without_scope_leaks(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL-only concurrency test.');
        }

        $owner = User::factory()->create();
        $author = User::factory()->create();
        $foreignOwner = User::factory()->create();

        $world = World::factory()->create([
            'slug' => 'mysql-post-moderation-collision',
            'is_active' => true,
        ]);

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

        CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $author->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => $owner->id,
        ]);

        $targetPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'content' => 'Concurrency target post.',
            'content_format' => 'plain',
            'post_type' => 'ic',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $controlPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'content' => 'Control post should remain untouched.',
            'content_format' => 'plain',
            'post_type' => 'ic',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $foreignCampaign = Campaign::factory()->create([
            'world_id' => $world->id,
            'owner_id' => $foreignOwner->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $foreignScene = Scene::factory()->create([
            'campaign_id' => $foreignCampaign->id,
            'created_by' => $foreignOwner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $foreignPost = Post::factory()->create([
            'scene_id' => $foreignScene->id,
            'user_id' => $foreignOwner->id,
            'content' => 'Foreign campaign post must remain untouched.',
            'content_format' => 'plain',
            'post_type' => 'ic',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $workerScript = base_path('tests/Support/Concurrency/post_moderation_collision_worker.php');

        $singleProcess = new Process([
            PHP_BINARY,
            $workerScript,
            'single',
            (string) $world->slug,
            (string) $targetPost->id,
            (string) $owner->id,
            'approved',
            'single-race-approve',
            '240',
        ]);

        $bulkProcess = new Process([
            PHP_BINARY,
            $workerScript,
            'bulk',
            (string) $world->slug,
            (string) $targetPost->id,
            (string) $owner->id,
            'rejected',
            'bulk-race-reject',
            '240',
        ]);

        $singleProcess->start();
        $bulkProcess->start();

        $singleProcess->wait();
        $bulkProcess->wait();

        $this->assertSame(0, $singleProcess->getExitCode(), $singleProcess->getErrorOutput());
        $this->assertSame(0, $bulkProcess->getExitCode(), $bulkProcess->getErrorOutput());

        $singleResult = $this->decodeWorkerOutput($singleProcess->getOutput(), $singleProcess->getErrorOutput());
        $bulkResult = $this->decodeWorkerOutput($bulkProcess->getOutput(), $bulkProcess->getErrorOutput());

        $this->assertSame('ok', $singleResult['status'] ?? null);
        $this->assertSame('ok', $bulkResult['status'] ?? null);

        $this->assertSame(302, (int) ($singleResult['http_status'] ?? 0), 'Single moderation worker returned unexpected HTTP status.');
        $this->assertSame(302, (int) ($bulkResult['http_status'] ?? 0), 'Bulk moderation worker returned unexpected HTTP status.');
        $this->assertStringNotContainsString('/login', (string) ($singleResult['location'] ?? ''), 'Single moderation worker was redirected to login.');
        $this->assertStringNotContainsString('/login', (string) ($bulkResult['location'] ?? ''), 'Bulk moderation worker was redirected to login.');

        $latestStart = max((float) ($singleResult['started_at'] ?? 0), (float) ($bulkResult['started_at'] ?? 0));
        $earliestFinish = min((float) ($singleResult['finished_at'] ?? 0), (float) ($bulkResult['finished_at'] ?? 0));
        $this->assertTrue($latestStart < $earliestFinish, 'Worker processes did not overlap in execution window.');

        $targetPost->refresh();
        $controlPost->refresh();
        $foreignPost->refresh();

        $this->assertContains($targetPost->moderation_status, ['approved', 'rejected']);

        if ((string) $targetPost->moderation_status === 'approved') {
            $this->assertSame((int) $owner->id, (int) $targetPost->approved_by);
            $this->assertNotNull($targetPost->approved_at);
        } else {
            // Characterization: in single-vs-bulk races, rejected may still carry prior approval metadata.
            $this->assertContains((int) ($targetPost->approved_by ?? 0), [0, (int) $owner->id]);
            if ((int) ($targetPost->approved_by ?? 0) === 0) {
                $this->assertNull($targetPost->approved_at);
            } else {
                $this->assertNotNull($targetPost->approved_at);
            }
        }

        $this->assertSame('pending', (string) $controlPost->moderation_status);
        $this->assertNull($controlPost->approved_by);
        $this->assertNull($controlPost->approved_at);

        $this->assertSame('pending', (string) $foreignPost->moderation_status);
        $this->assertNull($foreignPost->approved_by);
        $this->assertNull($foreignPost->approved_at);

        $targetLogs = PostModerationLog::query()
            ->where('post_id', (int) $targetPost->id)
            ->orderBy('id')
            ->get();

        $this->assertGreaterThanOrEqual(1, $targetLogs->count());
        $this->assertLessThanOrEqual(2, $targetLogs->count());
        $this->assertTrue(
            $targetLogs->contains(fn (PostModerationLog $log): bool => (string) $log->new_status === (string) $targetPost->moderation_status),
            'Final moderation status must be represented in moderation logs.'
        );

        foreach ($targetLogs as $log) {
            $this->assertSame((int) $owner->id, (int) $log->moderator_id);
            $this->assertContains((string) $log->new_status, ['approved', 'rejected']);
        }

        $duplicateTransitionCount = PostModerationLog::query()
            ->selectRaw('COUNT(*) as transition_count')
            ->where('post_id', (int) $targetPost->id)
            ->groupBy('previous_status', 'new_status', 'moderator_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();
        $this->assertSame(0, $duplicateTransitionCount, 'Duplicate moderation transition logs should not be emitted.');

        $this->assertSame(0, PostModerationLog::query()->where('post_id', (int) $controlPost->id)->count());
        $this->assertSame(0, PostModerationLog::query()->where('post_id', (int) $foreignPost->id)->count());

        $this->assertSame(0, PostRevision::query()->where('post_id', (int) $targetPost->id)->count());
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
