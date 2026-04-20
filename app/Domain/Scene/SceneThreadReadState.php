<?php

declare(strict_types=1);

namespace App\Domain\Scene;

use App\Models\SceneSubscription;

final readonly class SceneThreadReadState
{
    public function __construct(
        public ?SceneSubscription $subscription,
        public int $latestPostId,
        public int $unreadPostsCount,
        public int $newPostsSinceLastRead,
        public bool $hasUnreadPosts,
        public int $firstUnreadPostId,
        public int $lastReadPostIdBeforeOpen,
    ) {}
}

