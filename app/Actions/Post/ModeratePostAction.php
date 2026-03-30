<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Domain\Post\PostModerationService;
use App\Models\Post;
use App\Models\User;

class ModeratePostAction
{
    public function __construct(
        private readonly PostModerationService $postModerationService,
    ) {}

    public function execute(Post $post, User $moderator, string $status, string $moderationNote): void
    {
        $normalizedNote = $this->normalizeModerationNote($moderationNote);
        $previousModerationStatus = (string) $post->moderation_status;

        /** @var int<0, max> $moderatorId */
        $moderatorId = max(0, (int) $moderator->id);

        $post->moderation_status = $status;

        if ($status === 'approved') {
            $post->approved_at = now()->toDateTimeString();
            $post->approved_by = $moderatorId;
        } else {
            $post->approved_at = null;
            $post->approved_by = null;
        }

        $post->save();

        $this->postModerationService->synchronize(
            post: $post,
            moderator: $moderator,
            previousStatus: $previousModerationStatus,
            moderationNote: $normalizedNote,
        );
    }

    private function normalizeModerationNote(string $note): ?string
    {
        $normalized = trim($note);

        return $normalized !== '' ? $normalized : null;
    }
}
