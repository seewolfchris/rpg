<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Domain\Post\PostModerationService;
use App\Models\Post;
use App\Models\User;

final class ApplyPostModerationTransitionAction
{
    public function __construct(
        private readonly PostModerationService $postModerationService,
    ) {}

    public function execute(Post $post, User $moderator, string $targetStatus, ?string $moderationNote): void
    {
        $normalizedNote = $this->normalizeModerationNote($moderationNote);
        $previousStatus = (string) $post->moderation_status;

        $this->applyTargetStatus($post, $targetStatus, $moderator);
        $post->save();

        $this->postModerationService->synchronize(
            post: $post,
            moderator: $moderator,
            previousStatus: $previousStatus,
            moderationNote: $normalizedNote,
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
