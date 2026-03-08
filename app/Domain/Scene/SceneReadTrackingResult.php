<?php

namespace App\Domain\Scene;

final readonly class SceneReadTrackingResult
{
    public function __construct(
        public int $latestPostId,
        public int $lastReadPostIdBeforeOpen,
        public int $newPostsSinceLastRead,
        public bool $hasUnreadPosts,
        public int $firstUnreadPostId,
    ) {}
}
