<?php

declare(strict_types=1);

namespace App\Actions\Scene;

use App\Domain\Scene\SceneThreadReadStateService;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Support\CharacterViewPermissionResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BuildSceneThreadPageDataAction
{
    public function __construct(
        private readonly SceneThreadReadStateService $sceneThreadReadStateService,
        private readonly CharacterViewPermissionResolver $characterViewPermissionResolver,
    ) {}

    public function execute(Scene $scene, Campaign $campaign, User $user): SceneThreadPageData
    {
        $posts = $this->threadPostsPaginator($scene);
        $viewableCharacterIds = $this->resolveViewableCharacterIds($posts, $user);
        $threadReadState = $this->sceneThreadReadStateService->resolveForThreadRender(
            scene: $scene,
            user: $user,
        );
        $canModerateScene = $this->canModerateScene($user, $campaign);

        return new SceneThreadPageData(
            posts: $posts,
            viewableCharacterIds: $viewableCharacterIds,
            subscription: $threadReadState->subscription,
            latestPostId: $threadReadState->latestPostId,
            unreadPostsCount: $threadReadState->unreadPostsCount,
            canModerateScene: $canModerateScene,
        );
    }

    /**
     * @return LengthAwarePaginator<int, Post>
     */
    private function threadPostsPaginator(Scene $scene): LengthAwarePaginator
    {
        return Post::query()
            ->withTrashed()
            ->where('scene_id', $scene->id)
            ->with(Post::THREAD_PAGE_RELATIONS)
            ->latestByIdHotpath()
            ->paginate(Post::THREAD_POSTS_PER_PAGE)
            ->withQueryString();
    }

    private function canModerateScene(User $user, Campaign $campaign): bool
    {
        return $campaign->canModeratePosts($user);
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
