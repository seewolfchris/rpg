<?php

declare(strict_types=1);

namespace App\Actions\Scene;

use App\Domain\Scene\SceneThreadPostQuery;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Support\CharacterViewPermissionResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BuildSceneShowThreadDataAction
{
    public function __construct(
        private readonly SceneThreadPostQuery $sceneThreadPostQuery,
        private readonly CharacterViewPermissionResolver $characterViewPermissionResolver,
    ) {}

    /**
     * @return array{
     *     posts: LengthAwarePaginator<int, Post>,
     *     pinnedPosts: \Illuminate\Database\Eloquent\Collection<int, Post>,
     *     pinnedPostIds: list<int>,
     *     viewableCharacterIds: list<int>
     * }
     */
    public function execute(Scene $scene, User $user): array
    {
        $posts = $this->sceneThreadPostQuery->paginate($scene);
        $viewableCharacterIds = $this->resolveViewableCharacterIds($posts, $user);

        /** @var \Illuminate\Database\Eloquent\Collection<int, Post> $pinnedPosts */
        $pinnedPosts = Post::query()
            ->where('scene_id', $scene->id)
            ->where('is_pinned', true)
            ->with(['user', 'character', 'pinnedBy'])
            ->orderByDesc('pinned_at')
            ->limit(12)
            ->get();

        /** @var list<int> $pinnedPostIds */
        $pinnedPostIds = $pinnedPosts
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();

        return [
            'posts' => $posts,
            'pinnedPosts' => $pinnedPosts,
            'pinnedPostIds' => $pinnedPostIds,
            'viewableCharacterIds' => $viewableCharacterIds,
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, Post>  $posts
     * @return list<int>
     */
    private function resolveViewableCharacterIds(LengthAwarePaginator $posts, User $user): array
    {
        $characterIds = collect($posts->items())
            ->pluck('character_id')
            ->all();

        return $this->characterViewPermissionResolver->resolveViewableIdsForUser(
            characterIds: $characterIds,
            user: $user,
        );
    }
}
