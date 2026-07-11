<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Domain\Post\PostModerationService;
use App\Domain\Post\PostNotificationOrchestrator;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

final class ApplyPostModerationTransitionAction
{
    public function __construct(
        private readonly PostModerationService $postModerationService,
        private readonly DatabaseManager $db,
        private readonly PostNotificationOrchestrator $postNotificationOrchestrator,
    ) {}

    public function execute(
        Post $post,
        User $moderator,
        string $targetStatus,
        ?string $moderationNote,
        bool $dispatchAfterCommitEffects = true,
    ): PostModerationTransitionResult {
        $normalizedNote = $this->normalizeModerationNote($moderationNote);
        $result = $this->db->transaction(function () use ($post, $moderator, $targetStatus, $normalizedNote): PostModerationTransitionResult {
            /** @var Post $lockedPost */
            $lockedPost = Post::query()
                ->whereKey((int) $post->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedPost->loadMissing('scene.campaign.world');
            $previousStatus = (string) $lockedPost->moderation_status;

            if ($previousStatus === $targetStatus && $normalizedNote === null) {
                return new PostModerationTransitionResult(
                    post: $lockedPost,
                    changed: false,
                    previousStatus: $previousStatus,
                    moderationNote: $normalizedNote,
                );
            }

            $this->applyTargetStatus($lockedPost, $targetStatus, $moderator);
            $lockedPost->save();

            $this->postModerationService->synchronizePersistentState(
                post: $lockedPost,
                moderator: $moderator,
                previousStatus: $previousStatus,
                moderationNote: $normalizedNote,
            );

            return new PostModerationTransitionResult(
                post: $lockedPost,
                changed: true,
                previousStatus: $previousStatus,
                moderationNote: $normalizedNote,
            );
        }, 3);

        $post->setRawAttributes($result->post->getAttributes(), true);
        $post->setRelations($result->post->getRelations());

        if ($dispatchAfterCommitEffects) {
            $this->dispatchAfterCommitEffects($result, $moderator);
        }

        return $result;
    }

    public function dispatchAfterCommitEffects(PostModerationTransitionResult $result, User $moderator): void
    {
        if (! $result->changed) {
            return;
        }

        $this->postModerationService->dispatchAfterCommitEffects(
            post: $result->post,
            moderator: $moderator,
            previousStatus: $result->previousStatus,
            moderationNote: $result->moderationNote,
        );

        if ($result->previousStatus !== 'approved'
            && (string) $result->post->moderation_status === 'approved') {
            $this->publishApprovedPost($result->post);
        }
    }

    private function publishApprovedPost(Post $post): void
    {
        $post->loadMissing('user');
        $author = $post->user;

        if (! $author instanceof User) {
            return;
        }

        $this->postNotificationOrchestrator->notifySceneParticipantsWithRetry(
            $post,
            $author,
            'moderation_approved',
        );
        $this->postNotificationOrchestrator->notifyMentionsWithRetry(
            $post,
            $author,
            'moderation_approved',
        );
    }

    private function applyTargetStatus(Post $post, string $targetStatus, User $moderator): void
    {
        /** @var int<0, max> $moderatorId */
        $moderatorId = max(0, (int) $moderator->id);

        $post->moderation_status = $targetStatus;

        if ($targetStatus === 'approved') {
            $post->approved_at = now()->toDateTimeString();
            $post->approved_by = $moderatorId;

            return;
        }

        $post->approved_at = null;
        $post->approved_by = null;
    }

    private function normalizeModerationNote(?string $note): ?string
    {
        if ($note === null) {
            return null;
        }

        $normalized = trim($note);

        return $normalized !== '' ? $normalized : null;
    }
}
