<?php

declare(strict_types=1);

namespace App\Actions\Scene;

use App\Models\Scene;

final readonly class SceneMutationResult
{
    public function __construct(
        public Scene $scene,
        public ?string $mediaWarning = null,
    ) {}
}
