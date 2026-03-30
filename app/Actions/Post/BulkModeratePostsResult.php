<?php

declare(strict_types=1);

namespace App\Actions\Post;

final readonly class BulkModeratePostsResult
{
    public function __construct(
        public int $affected,
    ) {}
}
