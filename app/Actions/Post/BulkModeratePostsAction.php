<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Domain\Post\PostModerationScope;
use App\Domain\Post\PostModerationService;
use App\Models\Post;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;

class BulkModeratePostsAction
{
    public function __construct(
        private readonly PostModerationService $postModerationService,
        private readonly PostModerationScope $postModerationScope,
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function execute(BulkModeratePostsInput $input): BulkModeratePostsResult
    {
        if (! $this->postModerationScope->canAccessWorldQueue($input->moderator, $input->world)) {
            throw new AuthorizationException('Du darfst in dieser Welt keine Bulk-Moderation ausführen.');
        }

        $postsQuery = $this->postModerationScope
            ->baseQuery($input->moderator, $input->world)
            ->with(['scene.campaign', 'user']);

        if ($input->postIds->isNotEmpty()) {
            $postsQuery->whereKey($input->postIds->all());
        } elseif ($input->isHtmxRequest && $input->sceneId > 0) {
            $postsQuery->whereRaw('1 = 0');
        } else {
            $this->applyFilters($postsQuery, $input->statusFilter, $input->search);
        }

        if ($input->sceneId > 0) {
            $postsQuery->where('scene_id', $input->sceneId);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Post> $posts */
        $posts = $postsQuery->get();
        if ($input->postIds->isNotEmpty()) {
            /** @var \Illuminate\Support\Collection<int, int<1, max>> $resolvedPostIds */
            $resolvedPostIds = $posts
                ->pluck('id')
                ->map(static fn ($postId): int => (int) $postId)
                ->filter(static fn (int $postId): bool => $postId > 0)
                ->values();
            $unresolvedPostIds = $input->postIds->diff($resolvedPostIds);

            if ($unresolvedPostIds->isNotEmpty()) {
                throw new AuthorizationException('Mindestens ein angefragter Beitrag liegt außerhalb deiner Moderationsrechte.');
            }
        }

        $affected = $this->db->transaction(function () use ($posts, $input): int {
            $affected = 0;

            foreach ($posts as $post) {
                if (! $input->moderator->can('moderate', $post)) {
                    throw new AuthorizationException('Mindestens ein Beitrag liegt außerhalb deiner Moderationsrechte.');
                }

                $previousStatus = (string) $post->moderation_status;

                if ($previousStatus === $input->targetStatus && $input->moderationNote === null) {
                    continue;
                }

                $post->moderation_status = $input->targetStatus;

                if ($input->targetStatus === 'approved') {
                    $post->approved_at = now()->toDateTimeString();
                    $post->approved_by = $input->moderator->id;
                } else {
                    $post->approved_at = null;
                    $post->approved_by = null;
                }

                $post->save();

                $this->postModerationService->synchronize(
                    post: $post,
                    moderator: $input->moderator,
                    previousStatus: $previousStatus,
                    moderationNote: $input->moderationNote,
                );

                $affected++;
            }

            return $affected;
        });

        return new BulkModeratePostsResult(affected: $affected);
    }

    /**
     * @param  Builder<Post>  $query
     */
    private function applyFilters(Builder $query, string $status, string $search): void
    {
        if ($status !== 'all') {
            $query->where('moderation_status', $status);
        }

        if ($search !== '') {
            $searchTerm = '%'.$search.'%';
            $query->where(function (Builder $innerQuery) use ($searchTerm, $search): void {
                $innerQuery->where('content', 'like', $searchTerm)
                    ->orWhereHas('user', function (Builder $userQuery) use ($searchTerm): void {
                        $userQuery->where('name', 'like', $searchTerm);
                    })
                    ->orWhereHas('scene', function (Builder $sceneQuery) use ($searchTerm): void {
                        $sceneQuery->where('title', 'like', $searchTerm);
                    })
                    ->orWhereHas('scene.campaign', function (Builder $campaignQuery) use ($searchTerm): void {
                        $campaignQuery->where('title', 'like', $searchTerm);
                    })
                    ->orWhereHas('latestModerationLog', function (Builder $logQuery) use ($searchTerm): void {
                        $logQuery->where('reason', 'like', $searchTerm);
                    });

                if (is_numeric($search)) {
                    $innerQuery->orWhere('id', (int) $search);
                }
            });
        }
    }
}
