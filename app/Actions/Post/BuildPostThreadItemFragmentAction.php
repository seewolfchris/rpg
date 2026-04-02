<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use RuntimeException;

class BuildPostThreadItemFragmentAction
{
    public function execute(Post $post, ?User $user): View
    {
        $post->load(Post::THREAD_ITEM_RELATIONS);

        $scene = $this->resolveScene($post);
        $campaign = $this->resolveCampaign($scene);
        $bookmarkCountForNav = $this->visibleBookmarkCountForUser($user);

        return view('posts._thread-item', compact('post', 'scene', 'campaign', 'bookmarkCountForNav'));
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
                if ($user->isGmOrAdmin()) {
                    return;
                }

                $campaignQuery->where(function (Builder $innerQuery) use ($user): void {
                    $innerQuery
                        ->where('is_public', true)
                        ->orWhere('owner_id', $user->id)
                        ->orWhereHas('invitations', function (Builder $invitationQuery) use ($user): void {
                            $invitationQuery
                                ->where('user_id', $user->id)
                                ->where('status', CampaignInvitation::STATUS_ACCEPTED);
                        });
                });
            })
            ->count();
    }
}
