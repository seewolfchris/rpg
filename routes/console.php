<?php

use App\Support\Performance\WorldHotpathPerformanceReporter;
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
