<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Models\Post;
use App\Models\User;

class ModeratePostAction
{
    public function __construct(
        private readonly ApplyPostModerationTransitionAction $applyPostModerationTransitionAction,
    ) {}

    public function execute(Post $post, User $moderator, string $status, string $moderationNote): void
    {
        $this->applyPostModerationTransitionAction->execute(
            post: $post,
            moderator: $moderator,
            targetStatus: $status,
            moderationNote: $moderationNote,
        );
    }
}
