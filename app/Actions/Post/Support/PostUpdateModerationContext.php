<?php

declare(strict_types=1);

namespace App\Actions\Post\Support;

use Illuminate\Support\Carbon;

final readonly class PostUpdateModerationContext
{
    public function __construct(
        public bool $isModerator,
        public string $previousStatus,
        public ?string $moderationNote,
        public string $moderationStatus,
        public ?Carbon $approvedAt,
        public ?int $approvedBy,
    ) {}
}
