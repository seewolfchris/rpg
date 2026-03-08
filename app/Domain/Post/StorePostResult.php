<?php

namespace App\Domain\Post;

use App\Models\Post;

final readonly class StorePostResult
{
    public function __construct(
        public Post $post,
        public bool $probeCreated,
        public bool $inventoryAwardApplied,
    ) {}
}
