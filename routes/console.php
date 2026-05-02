<?php

use App\Actions\Dev\SeedTestflightQaAction;
use App\Actions\Campaign\SyncCampaignMembershipFromInvitationAction;
use App\Enums\CampaignMembershipRole;
use App\Models\CampaignInvitation;
use App\Models\CampaignMembership;
use App\Models\World;
use App\Support\Performance\PostsLatestByIdBenchmarker;
use App\Support\Performance\WorldHotpathPerformanceReporter;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('perf:world-hotpaths {--world=} {--out=}', function (WorldHotpathPerformanceReporter $reporter): void {
    $worldOption = $this->option('world');
    $worldSlug = is_string($worldOption) && trim($worldOption) !== ''
        ? trim($worldOption)
        : null;

    $report = $reporter->generate($worldSlug);

    $this->info('World hotpath performance report generated.');
    $this->line('Connection: '.$report['connection'].' ('.$report['driver'].')');
    $this->line('World: '.$report['world']['slug'].' (id: '.($report['world']['id'] ?? 'n/a').')');
    $this->line('Samples: scene_id='.$report['samples']['scene_id'].', campaign_id='.$report['samples']['campaign_id'].', user_id='.$report['samples']['user_id']);

    foreach ($report['queries'] as $key => $query) {
        $this->line('');
        $this->line('['.$key.'] '.$query['title']);

        if ($query['rows'] === []) {
            $this->warn('  - no plan rows returned');

            continue;
        }

        foreach ($query['rows'] as $row) {
            $detail = $row['detail'] ?? json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $this->line('  - '.(string) $detail);
        }
    }

    $outOption = $this->option('out');
    $relativePath = is_string($outOption) && trim($outOption) !== ''
        ? trim($outOption)
        : 'storage/app/performance/world-hotpaths-'.now()->format('Ymd-His').'.md';

    $outputPath = str_starts_with($relativePath, DIRECTORY_SEPARATOR)
        ? $relativePath
        : base_path($relativePath);

    File::ensureDirectoryExists(dirname($outputPath));
    file_put_contents($outputPath, $reporter->toMarkdown($report));

    $this->info('');
    $this->info('Saved markdown report: '.$outputPath);
})->purpose('Run EXPLAIN checks for world-context hotpath queries and write a markdown report');

Artisan::command('perf:posts-latest-by-id-benchmark {--world=} {--iterations=300} {--out=}', function (PostsLatestByIdBenchmarker $benchmarker): void {
    $worldOption = $this->option('world');
    $worldSlug = is_string($worldOption) && trim($worldOption) !== ''
        ? trim($worldOption)
        : null;

    $iterationsOption = $this->option('iterations');
    $iterations = is_numeric($iterationsOption) ? (int) $iterationsOption : 300;

    $report = $benchmarker->generate($worldSlug, $iterations);

    $this->info('posts.latest_by_id benchmark generated.');
    $this->line('Connection: '.$report['connection'].' ('.$report['driver'].')');
    $this->line('World: '.$report['world']['slug'].' (id: '.($report['world']['id'] ?? 'n/a').')');
    $this->line('Sample scenes: '.implode(', ', $report['sample_scene_ids']));
    $this->line('Iterations per scenario: '.$report['iterations']);

    foreach ($report['scenarios'] as $key => $scenario) {
        $this->line('');
        $this->line('['.$key.'] '.$scenario['title']);
        $this->line('  avg='.number_format($scenario['stats']['avg_ms'], 3).'ms'
            .', p95='.number_format($scenario['stats']['p95_ms'], 3).'ms'
            .', min='.number_format($scenario['stats']['min_ms'], 3).'ms'
            .', max='.number_format($scenario['stats']['max_ms'], 3).'ms');
    }

    $outOption = $this->option('out');
    $relativePath = is_string($outOption) && trim($outOption) !== ''
        ? trim($outOption)
        : 'storage/app/performance/posts-latest-by-id-benchmark-'.now()->format('Ymd-His').'.md';

    $outputPath = str_starts_with($relativePath, DIRECTORY_SEPARATOR)
        ? $relativePath
        : base_path($relativePath);

    File::ensureDirectoryExists(dirname($outputPath));
    file_put_contents($outputPath, $benchmarker->toMarkdown($report));

    $this->info('');
    $this->info('Saved markdown report: '.$outputPath);
})->purpose('Benchmark posts.latest_by_id and compare planner/index strategies');

Artisan::command('dev:testflight:seed {--world=} {--campaign-slug=} {--password=}', function (SeedTestflightQaAction $seedTestflightQaAction): int {
    if (app()->isProduction() || strtolower((string) config('app.env')) === 'production') {
        $this->error('Blocked: dev:testflight:seed is disabled in production.');

        return 1;
    }

    $worldOption = $this->option('world');
    $worldSlug = is_string($worldOption) && trim($worldOption) !== ''
        ? trim($worldOption)
        : World::defaultSlug();

    $world = World::query()
        ->where('slug', $worldSlug)
        ->first();

    if (! $world instanceof World) {
        $this->error('Unknown world slug: '.$worldSlug);

        return 1;
    }

    if (! (bool) $world->is_active) {
        $this->error('World is inactive and cannot be used for testflight seeding: '.$worldSlug);

        return 1;
    }

    $campaignSlugOption = $this->option('campaign-slug');
    $rawCampaignSlug = is_string($campaignSlugOption) && trim($campaignSlugOption) !== ''
        ? trim($campaignSlugOption)
        : 'testflight-'.$world->slug.'-qa';

    $campaignSlug = Str::slug($rawCampaignSlug);

    if ($campaignSlug === '') {
        $this->error('Invalid campaign slug option.');

        return 1;
    }

    if (! Str::startsWith($campaignSlug, 'testflight-')) {
        $campaignSlug = 'testflight-'.$campaignSlug;
    }

    $passwordOption = $this->option('password');
    $passwordProvided = is_string($passwordOption) && trim($passwordOption) !== '';
    $password = $passwordProvided
        ? trim($passwordOption)
        : Str::random(24);

    if (mb_strlen($password) < 12) {
        $this->error('Password must be at least 12 characters.');

        return 1;
    }

    $seed = $seedTestflightQaAction->execute(
        world: $world,
        campaignSlug: $campaignSlug,
        plainPassword: $password,
    );

    $campaignUrl = route('campaigns.show', [
        'world' => $seed['world'],
        'campaign' => $seed['campaign'],
    ], false);

    $sceneUrl = route('campaigns.scenes.show', [
        'world' => $seed['world'],
        'campaign' => $seed['campaign'],
        'scene' => $seed['scene'],
    ], false);

    $this->info('Testflight seed completed.');
    $this->line('World: '.$seed['world']->slug.' ('.$seed['world']->name.')');
    $this->line('Campaign slug: '.$seed['campaign']->slug);
    $this->line('Campaign URL: '.$campaignUrl);
    $this->line('Scene URL: '.$sceneUrl);
    $this->line('Password source: '.($passwordProvided ? 'provided' : 'generated'));
    $this->line('Password: '.$password);
    $this->line('Accounts:');
    $this->line('- gm: '.$seed['accounts']['gm']->email);
    $this->line('- co_gm: '.$seed['accounts']['co_gm']->email);
    $this->line('- player_one: '.$seed['accounts']['player_one']->email);
    $this->line('- player_two: '.$seed['accounts']['player_two']->email);
    $this->line('- trusted_player: '.$seed['accounts']['trusted_player']->email);

    return 0;
})->purpose('Seed an idempotent testflight QA campaign with invitation matrix and test accounts');

Artisan::command('campaigns:backfill-memberships-from-invitations {--dry-run}', function (SyncCampaignMembershipFromInvitationAction $syncMembershipFromInvitationAction): int {
    $dryRun = (bool) $this->option('dry-run');
    $source = $dryRun
        ? 'campaigns:backfill-memberships-from-invitations:dry-run'
        : 'campaigns:backfill-memberships-from-invitations';

    $report = [
        'mode' => $dryRun ? 'dry-run' : 'apply',
        'scanned_invitations' => 0,
        'accepted_invitations' => 0,
        'touched_campaigns' => 0,
        'touched_users' => 0,
        'memberships_created' => 0,
        'memberships_updated' => 0,
        'memberships_unchanged' => 0,
        'skipped_invalid_rows' => 0,
        'skipped_errors' => 0,
        'campaign_ids' => [],
        'user_ids' => [],
    ];

    $membershipSnapshot = static function (int $campaignId, int $userId): ?array {
        $membership = CampaignMembership::query()
            ->where('campaign_id', $campaignId)
            ->where('user_id', $userId)
            ->first();

        if (! $membership instanceof CampaignMembership) {
            return null;
        }

        $role = $membership->role;
        $roleValue = $role instanceof CampaignMembershipRole
            ? $role->value
            : (string) $role;

        return [
            'id' => (int) $membership->id,
            'role' => $roleValue,
        ];
    };

    $acceptedInvitations = CampaignInvitation::query()
        ->where('status', CampaignInvitation::STATUS_ACCEPTED)
        ->orderBy('id')
        ->get();

    foreach ($acceptedInvitations as $invitation) {
        $report['scanned_invitations']++;

        $campaignId = (int) $invitation->campaign_id;
        $userId = (int) $invitation->user_id;

        if ($campaignId <= 0 || $userId <= 0) {
            $report['skipped_invalid_rows']++;

            continue;
        }

        $report['accepted_invitations']++;
        $report['campaign_ids'][$campaignId] = true;
        $report['user_ids'][$userId] = true;

        $before = $membershipSnapshot($campaignId, $userId);

        if ($dryRun) {
            DB::beginTransaction();
        }

        try {
            $syncMembershipFromInvitationAction->syncAcceptedInvitation(
                invitation: $invitation,
                actorUserId: null,
                source: $source,
            );

            $after = $membershipSnapshot($campaignId, $userId);

            if ($dryRun) {
                DB::rollBack();
            }
        } catch (\Throwable $throwable) {
            if ($dryRun && DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            $report['skipped_errors']++;
            report($throwable);

            continue;
        }

        if ($before === null && $after !== null) {
            $report['memberships_created']++;

            continue;
        }

        if (
            $before !== null
            && $after !== null
            && (string) ($before['role'] ?? '') !== (string) ($after['role'] ?? '')
        ) {
            $report['memberships_updated']++;

            continue;
        }

        $report['memberships_unchanged']++;
    }

    $report['touched_campaigns'] = count($report['campaign_ids']);
    $report['touched_users'] = count($report['user_ids']);
    unset($report['campaign_ids'], $report['user_ids']);

    $this->info('campaign_membership_backfill completed');
    $this->line('mode: '.$report['mode']);
    $this->line('scanned_invitations: '.$report['scanned_invitations']);
    $this->line('accepted_invitations: '.$report['accepted_invitations']);
    $this->line('touched_campaigns: '.$report['touched_campaigns']);
    $this->line('touched_users: '.$report['touched_users']);
    $this->line('memberships_created: '.$report['memberships_created']);
    $this->line('memberships_updated: '.$report['memberships_updated']);
    $this->line('memberships_unchanged: '.$report['memberships_unchanged']);
    $this->line('skipped_invalid_rows: '.$report['skipped_invalid_rows']);
    $this->line('skipped_errors: '.$report['skipped_errors']);
    $this->line('report_json: '.json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    return 0;
})->purpose('Backfill campaign_memberships from accepted campaign_invitations');
