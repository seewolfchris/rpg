<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Domain\Campaign\CampaignAccess;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Support\CharacterViewPermissionResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use RuntimeException;

class BuildPostThreadItemFragmentAction
{
    public function __construct(
        private readonly CampaignAccess $campaignAccess,
        private readonly CharacterViewPermissionResolver $characterViewPermissionResolver,
    ) {}

    public function execute(Post $post, ?User $user): View
    {
        $post->load(Post::THREAD_ITEM_RELATIONS);

        $scene = $this->resolveScene($post);
        $campaign = $this->resolveCampaign($scene);
        $bookmarkCountForNav = $this->visibleBookmarkCountForUser($user);
        $viewableCharacterIds = $this->resolveViewableCharacterIds($post, $user);

        return view('posts._thread-item', compact('post', 'scene', 'campaign', 'bookmarkCountForNav', 'viewableCharacterIds'));
    }

    private function resolveScene(Post $post): Scene
    {
        $scene = $post->scene;

        if (! $scene instanceof Scene) {
            throw new RuntimeException('Post without valid scene relation cannot render thread item.');
        }

        return $scene;
    }

    private function resolveCampaign(Scene $scene): Campaign
    {
        $campaign = $scene->campaign;

        if (! $campaign instanceof Campaign) {
            throw new RuntimeException('Scene without valid campaign relation cannot render thread item.');
        }

        return $campaign;
    }

    private function visibleBookmarkCountForUser(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        return (int) $user->sceneBookmarks()
            ->whereHas('scene.campaign', function (Builder $campaignQuery) use ($user): void {
                $this->campaignAccess->applyVisibleCampaignConstraint($campaignQuery, $user);
            })
            ->count();
    }

    /**
     * @return list<int>
     */
    private function resolveViewableCharacterIds(Post $post, ?User $user): array
    {
        if (! $user) {
            return [];
        }

        return $this->characterViewPermissionResolver->resolveViewableIdsForUser(
            characterIds: [(int) ($post->character_id ?? 0)],
            user: $user,
        );
    }
}
