<?php

declare(strict_types=1);

namespace App\Actions\Post\Support;

use App\Models\Post;

final readonly class PostUpdateTransactionResult
{
    public function __construct(
        public Post $post,
        public PostUpdateModerationContext $moderationContext,
        public bool $hasContentChange,
    ) {}
}
