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
     * @param  list<int>  $viewableCharacterIds
     */
    public function __construct(
        public LengthAwarePaginator $posts,
        public array $viewableCharacterIds,
        public ?SceneSubscription $subscription,
        public int $latestPostId,
        public int $unreadPostsCount,
        public bool $canModerateScene,
    ) {}
}
