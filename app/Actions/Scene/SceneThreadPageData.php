<?php

declare(strict_types=1);

namespace App\Actions\Scene;

use App\Models\Post;
use App\Models\SceneSubscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class SceneThreadPageData
{
    /**
     * @param  LengthAwarePaginator<int, Post>  $posts
     */
    public function __construct(
        public LengthAwarePaginator $posts,
        public ?SceneSubscription $subscription,
        public int $latestPostId,
        public int $unreadPostsCount,
        public bool $canModerateScene,
    ) {}
}
