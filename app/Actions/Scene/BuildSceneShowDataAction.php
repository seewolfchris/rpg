<?php

declare(strict_types=1);

namespace App\Actions\Scene;

use App\Domain\Scene\SceneThreadReadStateService;
use App\Models\Campaign;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;

class BuildSceneShowDataAction
{
    public function __construct(
        private readonly SceneThreadReadStateService $sceneThreadReadStateService,
        private readonly BuildSceneShowThreadDataAction $buildSceneShowThreadDataAction,
        private readonly BuildSceneShowPanelDataAction $buildSceneShowPanelDataAction,
        private readonly BuildSceneShowNavigationDataAction $buildSceneShowNavigationDataAction,
    ) {}

    public function execute(World $world, Campaign $campaign, Scene $scene, User $user): SceneShowData
    {
        $scene->load(['campaign.owner', 'creator', 'previousScene']);
        $scene->loadCount('subscriptions');

        $threadReadState = $this->sceneThreadReadStateService->resolveForShowAndMarkRead(
            scene: $scene,
            user: $user,
        );

        $threadData = $this->buildSceneShowThreadDataAction->execute($scene, $user);
        $panelData = $this->buildSceneShowPanelDataAction->execute($campaign, $scene, $user);
        $navigationData = $this->buildSceneShowNavigationDataAction->execute(
            world: $world,
            campaign: $campaign,
            scene: $scene,
            user: $user,
            threadReadState: $threadReadState,
            pinnedPostIds: $threadData['pinnedPostIds'],
            pinnedPosts: $threadData['pinnedPosts'],
        );

        return new SceneShowData(
            posts: $threadData['posts'],
            pinnedPosts: $threadData['pinnedPosts'],
            pinnedPostJumpUrls: $navigationData['pinnedPostJumpUrls'],
            characters: $panelData['characters'],
            probeCharacters: $panelData['probeCharacters'],
            sceneHandouts: $panelData['sceneHandouts'],
            viewableCharacterIds: $threadData['viewableCharacterIds'],
            sceneChronicleCount: $panelData['sceneChronicleCount'],
            scenePlayerNotesCount: $panelData['scenePlayerNotesCount'],
            canModerateScene: $panelData['canModerateScene'],
            subscription: $threadReadState->subscription,
            latestPostId: $threadReadState->latestPostId,
            unreadPostsCount: $threadReadState->unreadPostsCount,
            newPostsSinceLastRead: $threadReadState->newPostsSinceLastRead,
            hasUnreadPosts: $threadReadState->hasUnreadPosts,
            jumpToLastReadUrl: $navigationData['jumpToLastReadUrl'],
            jumpToFirstUnreadUrl: $navigationData['jumpToFirstUnreadUrl'],
            jumpToLatestPostUrl: $navigationData['jumpToLatestPostUrl'],
            userBookmark: $navigationData['userBookmark'],
            bookmarkJumpUrl: $navigationData['bookmarkJumpUrl'],
        );
    }
}
