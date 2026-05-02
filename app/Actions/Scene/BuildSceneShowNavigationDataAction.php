<?php

declare(strict_types=1);

namespace App\Actions\Scene;

use App\Domain\Scene\ScenePostAnchorUrlService;
use App\Domain\Scene\SceneThreadReadState;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneBookmark;
use App\Models\User;
use App\Models\World;

class BuildSceneShowNavigationDataAction
{
    public function __construct(
        private readonly ScenePostAnchorUrlService $scenePostAnchorUrlService,
    ) {}

    /**
     * @param  list<int>  $pinnedPostIds
     * @param  \Illuminate\Database\Eloquent\Collection<int, Post>  $pinnedPosts
     * @return array{
     *     pinnedPostJumpUrls: array<int, string|null>,
     *     jumpToLastReadUrl: string|null,
     *     jumpToFirstUnreadUrl: string|null,
     *     jumpToLatestPostUrl: string|null,
     *     userBookmark: SceneBookmark|null,
     *     bookmarkJumpUrl: string|null
     * }
     */
    public function execute(
        World $world,
        Campaign $campaign,
        Scene $scene,
        User $user,
        SceneThreadReadState $threadReadState,
        array $pinnedPostIds,
        \Illuminate\Database\Eloquent\Collection $pinnedPosts,
    ): array {
        $userBookmark = SceneBookmark::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', $user->id)
            ->with('post')
            ->first();
        $bookmarkPostId = $userBookmark instanceof SceneBookmark
            ? (int) $userBookmark->post_id
            : 0;

        $anchorTargetIds = array_values(array_filter(
            array_merge(
                $pinnedPostIds,
                [
                    $threadReadState->lastReadPostIdBeforeOpen,
                    $threadReadState->firstUnreadPostId,
                    $threadReadState->latestPostId,
                    $bookmarkPostId,
                ]
            ),
            static fn (int $postId): bool => $postId > 0
        ));
        $postAnchorUrls = $this->scenePostAnchorUrlService->build($world, $campaign, $scene, $anchorTargetIds);

        $pinnedPostJumpUrls = [];
        foreach ($pinnedPosts as $pinnedPost) {
            $pinnedPostJumpUrls[(int) $pinnedPost->id] = $postAnchorUrls[(int) $pinnedPost->id] ?? null;
        }

        $jumpToLastReadUrl = $threadReadState->lastReadPostIdBeforeOpen > 0
            ? ($postAnchorUrls[$threadReadState->lastReadPostIdBeforeOpen] ?? null)
            : null;

        $jumpToFirstUnreadUrl = $threadReadState->firstUnreadPostId > 0
            ? ($postAnchorUrls[$threadReadState->firstUnreadPostId] ?? null)
            : null;

        $jumpToLatestPostUrl = $threadReadState->latestPostId > 0
            ? ($postAnchorUrls[$threadReadState->latestPostId] ?? null)
            : null;

        $bookmarkJumpUrl = $bookmarkPostId > 0
            ? ($postAnchorUrls[$bookmarkPostId] ?? null)
            : null;

        return [
            'pinnedPostJumpUrls' => $pinnedPostJumpUrls,
            'jumpToLastReadUrl' => $jumpToLastReadUrl,
            'jumpToFirstUnreadUrl' => $jumpToFirstUnreadUrl,
            'jumpToLatestPostUrl' => $jumpToLatestPostUrl,
            'userBookmark' => $userBookmark,
            'bookmarkJumpUrl' => $bookmarkJumpUrl,
        ];
    }
}
