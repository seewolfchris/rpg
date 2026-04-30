<?php

declare(strict_types=1);

namespace App\Actions\Scene;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Domain\Scene\ScenePostAnchorUrlService;
use App\Domain\Scene\SceneThreadReadStateService;
use App\Models\Campaign;
use App\Models\Handout;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneBookmark;
use App\Models\StoryLogEntry;
use App\Models\User;
use App\Models\World;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class BuildSceneShowDataAction
{
    public function __construct(
        private readonly SceneThreadReadStateService $sceneThreadReadStateService,
        private readonly ScenePostAnchorUrlService $scenePostAnchorUrlService,
        private readonly CampaignParticipantResolver $campaignParticipantResolver,
    ) {}

    public function execute(World $world, Campaign $campaign, Scene $scene, User $user): SceneShowData
    {
        $scene->load(['campaign.owner', 'creator', 'previousScene']);
        $scene->loadCount('subscriptions');

        $threadReadState = $this->sceneThreadReadStateService->resolveForShowAndMarkRead(
            scene: $scene,
            user: $user,
        );

        $subscription = $threadReadState->subscription;
        $latestPostId = $threadReadState->latestPostId;
        $unreadPostsCount = $threadReadState->unreadPostsCount;
        $newPostsSinceLastRead = $threadReadState->newPostsSinceLastRead;
        $hasUnreadPosts = $threadReadState->hasUnreadPosts;
        $firstUnreadPostId = $threadReadState->firstUnreadPostId;
        $lastReadPostIdBeforeOpen = $threadReadState->lastReadPostIdBeforeOpen;

        $posts = $this->threadPostsPaginator($scene);

        /** @var \Illuminate\Database\Eloquent\Collection<int, Post> $pinnedPosts */
        $pinnedPosts = Post::query()
            ->where('scene_id', $scene->id)
            ->where('is_pinned', true)
            ->with(['user', 'character', 'pinnedBy'])
            ->orderByDesc('pinned_at')
            ->limit(12)
            ->get();
        $pinnedPostIds = $pinnedPosts
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Character> $characters */
        $characters = $user
            ->characters()
            ->where('world_id', $campaign->world_id)
            ->orderBy('name')
            ->get();

        $canModerateScene = $this->canModerateScene($user, $campaign);
        $probeCharacters = $canModerateScene
            ? $this->campaignParticipantResolver->probeCharacters($campaign)
            : collect();
        $sceneHandouts = $this->sceneHandouts($campaign, $scene, $user);
        $sceneChronicleCount = $this->sceneChronicleCount($campaign, $scene, $user);

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
                [$lastReadPostIdBeforeOpen, $firstUnreadPostId, $latestPostId, $bookmarkPostId]
            ),
            static fn (int $postId): bool => $postId > 0
        ));
        $postAnchorUrls = $this->scenePostAnchorUrlService->build($world, $campaign, $scene, $anchorTargetIds);

        $pinnedPostJumpUrls = [];
        foreach ($pinnedPosts as $pinnedPost) {
            $pinnedPostJumpUrls[(int) $pinnedPost->id] = $postAnchorUrls[(int) $pinnedPost->id] ?? null;
        }

        $jumpToLastReadUrl = $lastReadPostIdBeforeOpen > 0
            ? ($postAnchorUrls[$lastReadPostIdBeforeOpen] ?? null)
            : null;

        $jumpToFirstUnreadUrl = $firstUnreadPostId > 0
            ? ($postAnchorUrls[$firstUnreadPostId] ?? null)
            : null;

        $jumpToLatestPostUrl = $latestPostId > 0
            ? ($postAnchorUrls[$latestPostId] ?? null)
            : null;

        $bookmarkJumpUrl = $bookmarkPostId > 0
            ? ($postAnchorUrls[$bookmarkPostId] ?? null)
            : null;

        return new SceneShowData(
            posts: $posts,
            pinnedPosts: $pinnedPosts,
            pinnedPostJumpUrls: $pinnedPostJumpUrls,
            characters: $characters,
            probeCharacters: $probeCharacters,
            sceneHandouts: $sceneHandouts,
            sceneChronicleCount: $sceneChronicleCount,
            canModerateScene: $canModerateScene,
            subscription: $subscription,
            latestPostId: $latestPostId,
            unreadPostsCount: $unreadPostsCount,
            newPostsSinceLastRead: $newPostsSinceLastRead,
            hasUnreadPosts: $hasUnreadPosts,
            jumpToLastReadUrl: $jumpToLastReadUrl,
            jumpToFirstUnreadUrl: $jumpToFirstUnreadUrl,
            jumpToLatestPostUrl: $jumpToLatestPostUrl,
            userBookmark: $userBookmark,
            bookmarkJumpUrl: $bookmarkJumpUrl,
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
     * @return Collection<int, Handout>
     */
    private function sceneHandouts(Campaign $campaign, Scene $scene, User $user): Collection
    {
        $canManageCampaign = $campaign->canManageCampaign($user);

        /** @var Collection<int, Handout> $handouts */
        $handouts = Handout::query()
            ->where('campaign_id', (int) $campaign->id)
            ->where(function ($query) use ($scene): void {
                $query
                    ->whereNull('scene_id')
                    ->orWhere('scene_id', (int) $scene->id);
            })
            ->when(
                ! $canManageCampaign,
                fn ($query) => $query->whereNotNull('revealed_at')
            )
            ->orderByRaw('sort_order IS NULL')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get([
                'id',
                'campaign_id',
                'scene_id',
                'title',
                'revealed_at',
                'sort_order',
                'created_at',
            ]);

        return $handouts;
    }

    private function sceneChronicleCount(Campaign $campaign, Scene $scene, User $user): int
    {
        $canManageCampaign = $campaign->canManageCampaign($user);

        return StoryLogEntry::query()
            ->where('campaign_id', (int) $campaign->id)
            ->where(function ($query) use ($scene): void {
                $query
                    ->whereNull('scene_id')
                    ->orWhere('scene_id', (int) $scene->id);
            })
            ->when(
                ! $canManageCampaign,
                fn ($query) => $query->whereNotNull('revealed_at')
            )
            ->count();
    }
}
