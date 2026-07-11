<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Models\Post;

final readonly class PostModerationTransitionResult
{
    public function __construct(
        public Post $post,
        public bool $changed,
        public string $previousStatus,
        public ?string $moderationNote,
    ) {}
}
