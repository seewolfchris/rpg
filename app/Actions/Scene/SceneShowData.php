<?php

declare(strict_types=1);

namespace App\Actions\Scene;

use App\Models\Character;
use App\Models\Handout;
use App\Models\Post;
use App\Models\SceneBookmark;
use App\Models\SceneSubscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class SceneShowData
{
    /**
     * @param  LengthAwarePaginator<int, Post>  $posts
     * @param  Collection<int, Post>  $pinnedPosts
     * @param  array<int, string|null>  $pinnedPostJumpUrls
     * @param  Collection<int, Character>  $characters
     * @param  Collection<int, Character>  $probeCharacters
     * @param  Collection<int, Handout>  $sceneHandouts
     */
    public function __construct(
        public LengthAwarePaginator $posts,
        public Collection $pinnedPosts,
        public array $pinnedPostJumpUrls,
        public Collection $characters,
        public Collection $probeCharacters,
        public Collection $sceneHandouts,
        public bool $canModerateScene,
        public ?SceneSubscription $subscription,
        public int $latestPostId,
        public int $unreadPostsCount,
        public int $newPostsSinceLastRead,
        public bool $hasUnreadPosts,
        public ?string $jumpToLastReadUrl,
        public ?string $jumpToFirstUnreadUrl,
        public ?string $jumpToLatestPostUrl,
        public ?SceneBookmark $userBookmark,
        public ?string $bookmarkJumpUrl,
    ) {}
}
