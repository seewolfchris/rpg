<?php

declare(strict_types=1);

namespace App\Domain\Scene;

use App\Models\Post;
use App\Models\Scene;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SceneThreadPostQuery
{
    /**
     * @return LengthAwarePaginator<int, Post>
     */
    public function paginate(Scene $scene): LengthAwarePaginator
    {
        return Post::query()
            ->withTrashed()
            ->where('scene_id', (int) $scene->id)
            ->with(Post::THREAD_PAGE_RELATIONS)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(Post::THREAD_POSTS_PER_PAGE)
            ->withQueryString();
    }
}
