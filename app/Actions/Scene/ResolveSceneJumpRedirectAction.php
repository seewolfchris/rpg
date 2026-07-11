<?php

declare(strict_types=1);

namespace App\Actions\Scene;

use App\Domain\Scene\ScenePostAnchorUrlService;
use App\Domain\Scene\ScenePostVisibility;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;
use App\Models\World;

class ResolveSceneJumpRedirectAction
{
    public function __construct(
        private readonly ScenePostAnchorUrlService $scenePostAnchorUrlService,
        private readonly ScenePostVisibility $scenePostVisibility,
    ) {}

    public function execute(World $world, Campaign $campaign, Scene $scene, User $user, string $jump): ?string
    {
        $normalizedJump = trim($jump);
        if ($normalizedJump === '') {
            return null;
        }

        $subscription = SceneSubscription::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', $user->id)
            ->first();

        $lastReadPostIdBeforeOpen = $subscription instanceof SceneSubscription
            ? (int) $subscription->last_read_post_id
            : 0;

        $jumpPostId = match ($normalizedJump) {
            'last_read' => $lastReadPostIdBeforeOpen,
            'latest' => $this->latestScenePostId($scene, $user),
            'first_unread' => $subscription instanceof SceneSubscription
                ? $this->firstUnreadPostId($scene, $user, $lastReadPostIdBeforeOpen)
                : 0,
            default => 0,
        };

        if ($jumpPostId <= 0) {
            return null;
        }

        return $this->scenePostAnchorUrlService->build($world, $campaign, $scene, $user, [$jumpPostId])[$jumpPostId] ?? null;
    }

    private function latestScenePostId(Scene $scene, User $user): int
    {
        return (int) $this->visiblePostQuery($scene, $user)
            ->max('id');
    }

    private function firstUnreadPostId(Scene $scene, User $user, int $lastReadPostId): int
    {
        return (int) $this->visiblePostQuery($scene, $user)
            ->when(
                $lastReadPostId > 0,
                fn ($query) => $query->where('id', '>', $lastReadPostId),
            )
            ->orderBy('id')
            ->value('id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Post>
     */
    private function visiblePostQuery(Scene $scene, User $user): \Illuminate\Database\Eloquent\Builder
    {
        $query = Post::query()
            ->withTrashed()
            ->where('scene_id', (int) $scene->id);

        return $this->scenePostVisibility->apply($query, $scene, $user);
    }
}
