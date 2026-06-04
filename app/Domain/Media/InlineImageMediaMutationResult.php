<?php

declare(strict_types=1);

namespace App\Domain\Media;

final readonly class InlineImageMediaMutationResult
{
    public function __construct(
        public int $attachedCount = 0,
        public int $removedCount = 0,
    ) {}
}
