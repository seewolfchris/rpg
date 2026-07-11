<?php

declare(strict_types=1);

namespace App\Domain\Scene;

use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class SceneThreadPostQuery
{
    public function __construct(
        private readonly ScenePostVisibility $scenePostVisibility,
    ) {}

    /**
     * @return Builder<Post>
     */
    public function visibleQuery(Scene $scene, User $user): Builder
    {
        $query = Post::query()
            ->withTrashed()
            ->where('scene_id', (int) $scene->id);

        return $this->scenePostVisibility->apply($query, $scene, $user);
    }

    /**
     * @return LengthAwarePaginator<int, Post>
     */
    public function paginate(Scene $scene, User $user): LengthAwarePaginator
    {
        return $this->visibleQuery($scene, $user)
            ->with(Post::THREAD_PAGE_RELATIONS)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(Post::THREAD_POSTS_PER_PAGE)
            ->withQueryString();
    }
}
