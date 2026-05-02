<?php

namespace Tests\Feature\Console;

use App\Enums\CampaignMembershipRole;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\CampaignMembership;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TestflightSeedCommandTest extends TestCase
{
    use RefreshDatabase;

    private const COMMAND = 'dev:testflight:seed';

    public function test_create_on_first_run(): void
    {
        $world = $this->activeWorld();
        $password = 'ManualQaPassword123';

        $this->artisan(self::COMMAND, [
            '--world' => $world->slug,
            '--password' => $password,
        ])->assertExitCode(0);

        $campaign = Campaign::query()
            ->where('slug', $this->defaultCampaignSlug($world))
            ->firstOrFail();

        $this->assertSame((int) $world->id, (int) $campaign->world_id);
        $this->assertSame('[TESTFLIGHT] QA-Kampagne · '.$world->name, $campaign->title);
        $this->assertFalse((bool) $campaign->is_public);
        $this->assertTrue((bool) $campaign->requires_post_moderation);
        $this->assertSame('active', $campaign->status);

        $scene = Scene::query()
            ->where('campaign_id', (int) $campaign->id)
            ->where('slug', 'testflight-hub')
            ->firstOrFail();

        $this->assertSame('[TESTFLIGHT] QA-Hub', $scene->title);
        $this->assertSame('open', $scene->status);
        $this->assertTrue((bool) $scene->allow_ooc);

        $emails = $this->expectedAccountEmails($world);

        foreach ($emails as $email) {
            $this->assertDatabaseHas('users', [
                'email' => $email,
            ]);
        }

        $managedUserIds = User::query()
            ->whereIn('email', array_values($emails))
            ->pluck('id')
            ->all();

        $this->assertCount(5, $managedUserIds);
        $this->assertSame(
            4,
            CampaignInvitation::query()
                ->where('campaign_id', (int) $campaign->id)
                ->whereIn('user_id', $managedUserIds)
                ->count()
        );

        $this->assertSame(
            3,
            CampaignMembership::query()
                ->where('campaign_id', (int) $campaign->id)
                ->whereIn('user_id', $managedUserIds)
                ->count()
        );
    }

    public function test_no_duplicates_on_second_run(): void
    {
        $world = $this->activeWorld();
        $password = 'ManualQaPassword123';

        $this->artisan(self::COMMAND, [
            '--world' => $world->slug,
            '--password' => $password,
        ])->assertExitCode(0);

        $this->artisan(self::COMMAND, [
            '--world' => $world->slug,
            '--password' => $password,
        ])->assertExitCode(0);

        $emails = $this->expectedAccountEmails($world);

        foreach ($emails as $email) {
            $this->assertSame(1, User::query()->where('email', $email)->count());
        }

        $campaignSlug = $this->defaultCampaignSlug($world);
        $this->assertSame(1, Campaign::query()->where('slug', $campaignSlug)->count());

        $campaign = Campaign::query()
            ->where('slug', $campaignSlug)
            ->firstOrFail();

        $this->assertSame(
            1,
            Scene::query()
                ->where('campaign_id', (int) $campaign->id)
                ->where('slug', 'testflight-hub')
                ->count()
        );

        $managedUserIds = User::query()
            ->whereIn('email', array_values($emails))
            ->pluck('id')
            ->all();

        $this->assertSame(
            4,
            CampaignInvitation::query()
                ->where('campaign_id', (int) $campaign->id)
                ->whereIn('user_id', $managedUserIds)
                ->count()
        );

        $this->assertSame(
            3,
            CampaignMembership::query()
                ->where('campaign_id', (int) $campaign->id)
                ->whereIn('user_id', $managedUserIds)
                ->count()
        );
    }

    public function test_drift_healing_on_rerun(): void
    {
        $world = $this->activeWorld();
        $password = 'ManualQaPassword123';

        $this->artisan(self::COMMAND, [
            '--world' => $world->slug,
            '--password' => $password,
        ])->assertExitCode(0);

        $emails = $this->expectedAccountEmails($world);
        $campaign = Campaign::query()
            ->where('slug', $this->defaultCampaignSlug($world))
            ->firstOrFail();
        $scene = Scene::query()
            ->where('campaign_id', (int) $campaign->id)
            ->where('slug', 'testflight-hub')
            ->firstOrFail();

        $gm = User::query()->where('email', $emails['gm'])->firstOrFail();
        $coGm = User::query()->where('email', $emails['co_gm'])->firstOrFail();
        $playerTwo = User::query()->where('email', $emails['player_two'])->firstOrFail();

        $gm->forceFill([
            'name' => 'Broken Name',
            'role' => UserRole::PLAYER->value,
            'password' => 'WrongPassword123',
            'can_post_without_moderation' => true,
            'offline_queue_enabled' => false,
        ])->save();

        $campaign->forceFill([
            'title' => 'Broken Campaign',
            'is_public' => true,
            'requires_post_moderation' => false,
            'status' => 'archived',
        ])->save();

        $scene->forceFill([
            'title' => 'Broken Scene',
            'status' => 'archived',
            'allow_ooc' => false,
            'created_by' => (int) $playerTwo->id,
        ])->save();

        $coGmInvitation = CampaignInvitation::query()
            ->where('campaign_id', (int) $campaign->id)
            ->where('user_id', (int) $coGm->id)
            ->firstOrFail();

        $coGmInvitation->forceFill([
            'status' => CampaignInvitation::STATUS_DECLINED,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'accepted_at' => null,
            'responded_at' => now(),
        ])->save();

        $this->artisan(self::COMMAND, [
            '--world' => $world->slug,
            '--password' => $password,
        ])->assertExitCode(0);

        $gm->refresh();
        $campaign->refresh();
        $scene->refresh();
        $coGmInvitation->refresh();

        $this->assertSame('[TESTFLIGHT] Spielleitung '.$world->slug, $gm->name);
        $this->assertSame(UserRole::PLAYER->value, (string) $gm->role?->value ?? (string) $gm->role);
        $this->assertTrue(Hash::check($password, (string) $gm->password));
        $this->assertFalse((bool) $gm->can_post_without_moderation);
        $this->assertTrue((bool) $gm->can_create_campaigns);
        $this->assertTrue((bool) $gm->offline_queue_enabled);

        $this->assertSame('[TESTFLIGHT] QA-Kampagne · '.$world->name, $campaign->title);
        $this->assertFalse((bool) $campaign->is_public);
        $this->assertTrue((bool) $campaign->requires_post_moderation);
        $this->assertSame('active', $campaign->status);

        $this->assertSame('[TESTFLIGHT] QA-Hub', $scene->title);
        $this->assertSame('open', $scene->status);
        $this->assertTrue((bool) $scene->allow_ooc);
        $this->assertSame((int) $gm->id, (int) $scene->created_by);

        $this->assertSame(CampaignInvitation::ROLE_CO_GM, $coGmInvitation->role);
        $this->assertSame(CampaignInvitation::STATUS_ACCEPTED, $coGmInvitation->status);
        $this->assertNotNull($coGmInvitation->accepted_at);
        $this->assertNotNull($coGmInvitation->responded_at);
        $this->assertDatabaseHas('campaign_memberships', [
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $coGm->id,
            'role' => CampaignMembershipRole::GM->value,
        ]);
    }

    public function test_world_isolation(): void
    {
        $worldA = World::factory()->create([
            'slug' => 'testflight-world-a',
            'name' => 'Testflight World A',
            'is_active' => true,
        ]);
        $worldB = World::factory()->create([
            'slug' => 'testflight-world-b',
            'name' => 'Testflight World B',
            'is_active' => true,
        ]);

        $this->artisan(self::COMMAND, [
            '--world' => $worldA->slug,
            '--password' => 'ManualQaPassword123',
        ])->assertExitCode(0);

        $campaignA = Campaign::query()
            ->where('slug', $this->defaultCampaignSlug($worldA))
            ->firstOrFail();

        $this->assertSame((int) $worldA->id, (int) $campaignA->world_id);
        $this->assertDatabaseMissing('campaigns', [
            'slug' => $this->defaultCampaignSlug($worldB),
        ]);

        $invitationWorldIds = CampaignInvitation::query()
            ->join('campaigns', 'campaigns.id', '=', 'campaign_invitations.campaign_id')
            ->where('campaigns.slug', $campaignA->slug)
            ->distinct()
            ->pluck('campaigns.world_id')
            ->map(static fn ($value): int => (int) $value)
            ->values()
            ->all();

        $this->assertSame([(int) $worldA->id], $invitationWorldIds);
    }

    public function test_production_hard_block(): void
    {
        config()->set('app.env', 'production');

        $world = $this->activeWorld();

        $this->artisan(self::COMMAND, [
            '--world' => $world->slug,
            '--password' => 'ManualQaPassword123',
        ])->expectsOutputToContain('disabled in production')
            ->assertExitCode(1);

        $this->assertSame(0, Campaign::query()->where('slug', $this->defaultCampaignSlug($world))->count());
        $this->assertSame(0, User::query()->where('email', 'like', 'testflight.%@example.test')->count());
    }

    public function test_invitation_status_matrix_exact(): void
    {
        $world = $this->activeWorld();

        $this->artisan(self::COMMAND, [
            '--world' => $world->slug,
            '--password' => 'ManualQaPassword123',
        ])->assertExitCode(0);

        $campaign = Campaign::query()
            ->where('slug', $this->defaultCampaignSlug($world))
            ->firstOrFail();

        $emails = $this->expectedAccountEmails($world);

        $rows = CampaignInvitation::query()
            ->join('users', 'users.id', '=', 'campaign_invitations.user_id')
            ->where('campaign_invitations.campaign_id', (int) $campaign->id)
            ->whereIn('users.email', array_values($emails))
            ->get([
                'users.email as email',
                'campaign_invitations.role as role',
                'campaign_invitations.status as status',
                'campaign_invitations.accepted_at as accepted_at',
                'campaign_invitations.responded_at as responded_at',
            ]);

        $this->assertCount(4, $rows);

        $byEmail = [];
        foreach ($rows as $row) {
            $byEmail[(string) $row->email] = [
                'role' => (string) $row->role,
                'status' => (string) $row->status,
                'accepted_at' => $row->accepted_at,
                'responded_at' => $row->responded_at,
            ];
        }

        $this->assertSame(CampaignInvitation::ROLE_CO_GM, $byEmail[$emails['co_gm']]['role']);
        $this->assertSame(CampaignInvitation::STATUS_ACCEPTED, $byEmail[$emails['co_gm']]['status']);
        $this->assertNotNull($byEmail[$emails['co_gm']]['accepted_at']);
        $this->assertNotNull($byEmail[$emails['co_gm']]['responded_at']);

        $this->assertSame(CampaignInvitation::ROLE_PLAYER, $byEmail[$emails['player_one']]['role']);
        $this->assertSame(CampaignInvitation::STATUS_ACCEPTED, $byEmail[$emails['player_one']]['status']);
        $this->assertNotNull($byEmail[$emails['player_one']]['accepted_at']);
        $this->assertNotNull($byEmail[$emails['player_one']]['responded_at']);

        $this->assertSame(CampaignInvitation::ROLE_PLAYER, $byEmail[$emails['player_two']]['role']);
        $this->assertSame(CampaignInvitation::STATUS_PENDING, $byEmail[$emails['player_two']]['status']);
        $this->assertNull($byEmail[$emails['player_two']]['accepted_at']);
        $this->assertNull($byEmail[$emails['player_two']]['responded_at']);

        $this->assertSame(CampaignInvitation::ROLE_TRUSTED_PLAYER, $byEmail[$emails['trusted_player']]['role']);
        $this->assertSame(CampaignInvitation::STATUS_ACCEPTED, $byEmail[$emails['trusted_player']]['status']);
        $this->assertNotNull($byEmail[$emails['trusted_player']]['accepted_at']);
        $this->assertNotNull($byEmail[$emails['trusted_player']]['responded_at']);

        $userIdByEmail = User::query()
            ->whereIn('email', array_values($emails))
            ->pluck('id', 'email')
            ->all();

        $this->assertDatabaseHas('campaign_memberships', [
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) ($userIdByEmail[$emails['co_gm']] ?? 0),
            'role' => CampaignMembershipRole::GM->value,
        ]);
        $this->assertDatabaseHas('campaign_memberships', [
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) ($userIdByEmail[$emails['player_one']] ?? 0),
            'role' => CampaignMembershipRole::PLAYER->value,
        ]);
        $this->assertDatabaseHas('campaign_memberships', [
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) ($userIdByEmail[$emails['trusted_player']] ?? 0),
            'role' => CampaignMembershipRole::TRUSTED_PLAYER->value,
        ]);
        $this->assertDatabaseMissing('campaign_memberships', [
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) ($userIdByEmail[$emails['player_two']] ?? 0),
        ]);
    }

    public function test_output_contains_expected_accounts_and_urls(): void
    {
        $world = $this->activeWorld();
        $password = 'ManualQaPassword123';

        $exitCode = Artisan::call(self::COMMAND, [
            '--world' => $world->slug,
            '--password' => $password,
        ]);

        $output = Artisan::output();
        $emails = $this->expectedAccountEmails($world);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Campaign URL: /w/'.$world->slug.'/campaigns/', $output);
        $this->assertStringContainsString('Scene URL: /w/'.$world->slug.'/campaigns/', $output);
        $this->assertStringContainsString('Password source: provided', $output);

        foreach ($emails as $email) {
            $this->assertStringContainsString($email, $output);
        }
    }

    public function test_password_behavior_is_covered_for_provided_and_generated_modes(): void
    {
        $world = $this->activeWorld();
        $providedPassword = 'ProvidedQaPassword123';
        $gmEmail = $this->expectedAccountEmails($world)['gm'];

        $providedExitCode = Artisan::call(self::COMMAND, [
            '--world' => $world->slug,
            '--password' => $providedPassword,
        ]);
        $providedOutput = Artisan::output();

        $this->assertSame(0, $providedExitCode);
        $this->assertStringContainsString('Password source: provided', $providedOutput);
        $this->assertStringContainsString('Password: '.$providedPassword, $providedOutput);

        $gm = User::query()->where('email', $gmEmail)->firstOrFail();
        $this->assertTrue(Hash::check($providedPassword, (string) $gm->password));

        $generatedExitCode = Artisan::call(self::COMMAND, [
            '--world' => $world->slug,
        ]);
        $generatedOutput = Artisan::output();

        $this->assertSame(0, $generatedExitCode);
        $this->assertStringContainsString('Password source: generated', $generatedOutput);
        $this->assertMatchesRegularExpression('/Password:\s+([A-Za-z0-9]{24})/', $generatedOutput);

        preg_match('/Password:\s+([A-Za-z0-9]{24})/', $generatedOutput, $matches);
        $generatedPassword = (string) ($matches[1] ?? '');

        $this->assertNotSame('', $generatedPassword);
        $gm->refresh();
        $this->assertTrue(Hash::check($generatedPassword, (string) $gm->password));
    }

    private function activeWorld(): World
    {
        return World::query()
            ->active()
            ->where('slug', World::defaultSlug())
            ->firstOrFail();
    }

    private function defaultCampaignSlug(World $world): string
    {
        return 'testflight-'.$world->slug.'-qa';
    }

    /**
     * @return array{
     *     gm: string,
     *     co_gm: string,
     *     player_one: string,
     *     player_two: string,
     *     trusted_player: string
     * }
     */
    private function expectedAccountEmails(World $world): array
    {
        return [
            'gm' => sprintf('testflight.gm+%s@example.test', $world->slug),
            'co_gm' => sprintf('testflight.co-gm+%s@example.test', $world->slug),
            'player_one' => sprintf('testflight.player-one+%s@example.test', $world->slug),
            'player_two' => sprintf('testflight.player-two+%s@example.test', $world->slug),
            'trusted_player' => sprintf('testflight.trusted-player+%s@example.test', $world->slug),
        ];
    }
}
