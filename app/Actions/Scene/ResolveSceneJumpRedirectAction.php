<?php

declare(strict_types=1);

namespace App\Actions\Scene;

use App\Domain\Scene\ScenePostAnchorUrlService;
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
            'latest' => $this->latestScenePostId($scene),
            'first_unread' => $subscription instanceof SceneSubscription
                ? $this->firstUnreadPostId($scene, $lastReadPostIdBeforeOpen)
                : 0,
            default => 0,
        };

        if ($jumpPostId <= 0) {
            return null;
        }

        return $this->scenePostAnchorUrlService->build($world, $campaign, $scene, [$jumpPostId])[$jumpPostId] ?? null;
    }

    private function latestScenePostId(Scene $scene): int
    {
        return (int) Post::query()
            ->withTrashed()
            ->where('scene_id', $scene->id)
            ->max('id');
    }

    private function firstUnreadPostId(Scene $scene, int $lastReadPostId): int
    {
        return (int) Post::query()
            ->withTrashed()
            ->where('scene_id', $scene->id)
            ->when(
                $lastReadPostId > 0,
                fn ($query) => $query->where('id', '>', $lastReadPostId),
            )
            ->orderBy('id')
            ->value('id');
    }
}
