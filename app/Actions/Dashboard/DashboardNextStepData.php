<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

final readonly class DashboardNextStepData
{
    public function __construct(
        public string $eyebrow,
        public string $title,
        public string $description,
        public string $primaryLabel,
        public string $primaryUrl,
        public ?string $secondaryLabel = null,
        public ?string $secondaryUrl = null,
        public ?string $meta = null,
    ) {}
}
