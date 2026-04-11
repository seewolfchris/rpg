<?php

use App\Actions\Dev\SeedTestflightQaAction;
use App\Models\World;
use App\Support\Performance\PostsLatestByIdBenchmarker;
use App\Support\Performance\WorldHotpathPerformanceReporter;
use Illuminate\Foundation\Inspiring;
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
