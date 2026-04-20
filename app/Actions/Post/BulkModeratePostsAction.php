<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Domain\Post\PostModerationScope;
use App\Models\Post;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class BulkModeratePostsAction
{
    public function __construct(
        private readonly ApplyPostModerationFiltersAction $applyPostModerationFiltersAction,
        private readonly ApplyPostModerationTransitionAction $applyPostModerationTransitionAction,
        private readonly PostModerationScope $postModerationScope,
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function execute(BulkModeratePostsInput $input): BulkModeratePostsResult
    {
        $this->assertWorldQueueAccess($input);

        $posts = $this->resolveModerationCandidates($input);
        $this->assertSelectedPostsResolved($posts, $input->postIds);

        $affected = $this->applyBulkModeration($posts, $input);

        return new BulkModeratePostsResult(affected: $affected);
    }

    /**
     * @throws AuthorizationException
     */
    private function assertWorldQueueAccess(BulkModeratePostsInput $input): void
    {
        if (! $this->postModerationScope->canAccessWorldQueue($input->moderator, $input->world)) {
            throw new AuthorizationException('Du darfst in dieser Welt keine Bulk-Moderation ausführen.');
        }
    }

    /**
     * @return EloquentCollection<int, Post>
     */
    private function resolveModerationCandidates(BulkModeratePostsInput $input): EloquentCollection
    {
        $postsQuery = $this->postModerationScope
            ->baseQuery($input->moderator, $input->world)
            ->with(['scene.campaign', 'user']);

        $this->applyCandidateSelection($postsQuery, $input);

        /** @var EloquentCollection<int, Post> $posts */
        $posts = $postsQuery->get();

        return $posts;
    }

    /**
     * @param  Builder<Post>  $postsQuery
     */
    private function applyCandidateSelection(Builder $postsQuery, BulkModeratePostsInput $input): void
    {
        if ($input->postIds->isNotEmpty()) {
            $postsQuery->whereKey($input->postIds->all());
        } elseif ($input->isHtmxRequest && $input->sceneId > 0) {
            $postsQuery->whereRaw('1 = 0');
        } else {
            $this->applyPostModerationFiltersAction->execute($postsQuery, $input->statusFilter, $input->search);
        }

        if ($input->sceneId > 0) {
            $postsQuery->where('scene_id', $input->sceneId);
        }
    }

    /**
     * @param  EloquentCollection<int, Post>  $posts
     * @param  Collection<int, int<1, max>>  $requestedPostIds
     *
     * @throws AuthorizationException
     */
    private function assertSelectedPostsResolved(EloquentCollection $posts, Collection $requestedPostIds): void
    {
        if ($requestedPostIds->isEmpty()) {
            return;
        }

        /** @var Collection<int, int<1, max>> $resolvedPostIds */
        $resolvedPostIds = $posts
            ->pluck('id')
            ->map(static fn ($postId): int => (int) $postId)
            ->filter(static fn (int $postId): bool => $postId > 0)
            ->values();
        $unresolvedPostIds = $requestedPostIds->diff($resolvedPostIds);

        if ($unresolvedPostIds->isNotEmpty()) {
            throw new AuthorizationException('Mindestens ein angefragter Beitrag liegt außerhalb deiner Moderationsrechte.');
        }
    }

    /**
     * @param  EloquentCollection<int, Post>  $posts
     *
     * @throws AuthorizationException
     */
    private function applyBulkModeration(EloquentCollection $posts, BulkModeratePostsInput $input): int
    {
        return $this->db->transaction(function () use ($posts, $input): int {
            $affected = 0;

            foreach ($posts as $post) {
                $affected += $this->moderateSinglePost($post, $input);
            }

            return $affected;
        });
    }

    /**
     * @throws AuthorizationException
     */
    private function moderateSinglePost(Post $post, BulkModeratePostsInput $input): int
    {
        if (! $input->moderator->can('moderate', $post)) {
            throw new AuthorizationException('Mindestens ein Beitrag liegt außerhalb deiner Moderationsrechte.');
        }

        $previousStatus = (string) $post->moderation_status;

        if ($previousStatus === $input->targetStatus && $input->moderationNote === null) {
            return 0;
        }

        $this->applyPostModerationTransitionAction->execute(
            post: $post,
            moderator: $input->moderator,
            targetStatus: $input->targetStatus,
            moderationNote: $input->moderationNote,
        );

        return 1;
    }
}
