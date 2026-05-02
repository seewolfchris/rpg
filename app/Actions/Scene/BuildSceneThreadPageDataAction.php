<?php

declare(strict_types=1);

namespace App\Actions\Scene;

use App\Domain\Scene\SceneThreadPostQuery;
use App\Domain\Scene\SceneThreadReadStateService;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Support\CharacterViewPermissionResolver;

class BuildSceneThreadPageDataAction
{
    public function __construct(
        private readonly SceneThreadReadStateService $sceneThreadReadStateService,
        private readonly SceneThreadPostQuery $sceneThreadPostQuery,
        private readonly CharacterViewPermissionResolver $characterViewPermissionResolver,
    ) {}

    public function execute(Scene $scene, Campaign $campaign, User $user): SceneThreadPageData
    {
        $posts = $this->sceneThreadPostQuery->paginate($scene);
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

    private function canModerateScene(User $user, Campaign $campaign): bool
    {
        return $campaign->canModeratePosts($user);
    }

    /**
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, Post>  $posts
     * @return list<int>
     */
    private function resolveViewableCharacterIds(\Illuminate\Contracts\Pagination\LengthAwarePaginator $posts, User $user): array
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
