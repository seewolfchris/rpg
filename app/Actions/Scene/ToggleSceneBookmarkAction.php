<?php

declare(strict_types=1);

namespace App\Actions\Scene;

use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneBookmark;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

final class ToggleSceneBookmarkAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function create(
        World $world,
        Campaign $campaign,
        Scene $scene,
        User $user,
        ?int $requestedPostId = null,
        ?string $label = null,
    ): SceneBookmark {
        try {
            return $this->runCreateTransaction($world, $campaign, $scene, $user, $requestedPostId, $label);
        } catch (QueryException $exception) {
            if (! $this->isDuplicateBookmarkKey($exception)) {
                throw $exception;
            }

            return $this->runCreateTransaction($world, $campaign, $scene, $user, $requestedPostId, $label);
        }
    }

    public function delete(World $world, Campaign $campaign, Scene $scene, User $user): void
    {
        $this->db->transaction(function () use ($world, $campaign, $scene, $user): void {
            $lockedScene = $this->lockAndVerifyContext($world, $campaign, $scene);
            $existingBookmark = $this->lockExistingBookmark($lockedScene, $user);

            if ($existingBookmark instanceof SceneBookmark) {
                $existingBookmark->delete();
            }
        }, 3);
    }

    private function runCreateTransaction(
        World $world,
        Campaign $campaign,
        Scene $scene,
        User $user,
        ?int $requestedPostId,
        ?string $label,
    ): SceneBookmark {
        /** @var SceneBookmark $bookmark */
        $bookmark = $this->db->transaction(function () use (
            $world,
            $campaign,
            $scene,
            $user,
            $requestedPostId,
            $label,
        ): SceneBookmark {
            $lockedScene = $this->lockAndVerifyContext($world, $campaign, $scene);
            $existingBookmark = $this->lockExistingBookmark($lockedScene, $user);
            $resolvedPostId = $this->resolveAndValidatePostId($lockedScene, $requestedPostId);

            return $this->persistBookmark(
                bookmark: $existingBookmark,
                scene: $lockedScene,
                user: $user,
                postId: $resolvedPostId,
                label: $label,
            );
        }, 3);

        return $bookmark;
    }

    private function lockAndVerifyContext(World $world, Campaign $campaign, Scene $scene): Scene
    {
        /** @var Scene $lockedScene */
        $lockedScene = $scene
            ->newQuery()
            ->whereKey((int) $scene->id)
            ->where('campaign_id', (int) $campaign->id)
            ->whereHas('campaign', static function (Builder $campaignQuery) use ($world): void {
                $campaignQuery->where('world_id', (int) $world->id);
            })
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedScene;
    }

    private function lockExistingBookmark(Scene $scene, User $user): ?SceneBookmark
    {
        /** @var SceneBookmark|null $bookmark */
        $bookmark = SceneBookmark::query()
            ->where('user_id', (int) $user->id)
            ->where('scene_id', (int) $scene->id)
            ->lockForUpdate()
            ->first();

        return $bookmark;
    }

    private function resolveAndValidatePostId(Scene $scene, ?int $requestedPostId): ?int
    {
        if ($requestedPostId !== null && $requestedPostId > 0) {
            $post = Post::query()
                ->where('scene_id', (int) $scene->id)
                ->whereKey($requestedPostId)
                ->lockForUpdate()
                ->first();

            if (! $post instanceof Post) {
                throw ValidationException::withMessages([
                    'post_id' => 'Der gewählte Post gehört nicht zu dieser Szene.',
                ]);
            }

            return (int) $post->id;
        }

        $latestPostId = (int) Post::query()
            ->where('scene_id', (int) $scene->id)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('id');

        return $latestPostId > 0 ? $latestPostId : null;
    }

    private function persistBookmark(
        ?SceneBookmark $bookmark,
        Scene $scene,
        User $user,
        ?int $postId,
        ?string $label,
    ): SceneBookmark {
        $targetBookmark = $bookmark ?? new SceneBookmark;
        $targetBookmark->user_id = max(0, (int) $user->id);
        $targetBookmark->scene_id = max(0, (int) $scene->id);
        $targetBookmark->post_id = $postId !== null ? max(0, $postId) : null;
        $targetBookmark->label = $this->normalizeLabel($label);
        $targetBookmark->save();

        return $targetBookmark;
    }

    private function normalizeLabel(?string $label): ?string
    {
        $normalized = trim((string) $label);

        return $normalized !== '' ? $normalized : null;
    }

    private function isDuplicateBookmarkKey(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo;
        $driverCode = is_array($errorInfo) && isset($errorInfo[1])
            ? (int) $errorInfo[1]
            : 0;
        $message = strtolower($exception->getMessage());

        if ($driverCode === 1062) {
            return true;
        }

        if (str_contains($message, 'duplicate entry')) {
            return true;
        }

        return str_contains($message, 'unique constraint failed');
    }
}
