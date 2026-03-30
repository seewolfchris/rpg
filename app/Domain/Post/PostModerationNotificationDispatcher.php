<?php

declare(strict_types=1);

namespace App\Domain\Post;

use App\Jobs\Post\SendPostModerationStatusNotificationJob;
use App\Models\Post;
use App\Models\User;

class PostModerationNotificationDispatcher
{
    public function dispatch(
        Post $post,
        User $moderator,
        string $previousStatus,
        string $newStatus,
        ?string $moderationNote,
    ): void {
        SendPostModerationStatusNotificationJob::dispatch(
            postId: (int) $post->id,
            moderatorId: (int) $moderator->id,
            previousStatus: $previousStatus,
            newStatus: $newStatus,
            moderationNote: $moderationNote,
        );
    }
}
