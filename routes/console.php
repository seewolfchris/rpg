<?php

use App\Support\Performance\WorldHotpathPerformanceReporter;
use App\Support\Performance\PostsLatestByIdBenchmarker;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

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
