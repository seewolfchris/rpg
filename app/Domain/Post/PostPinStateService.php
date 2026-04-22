<?php

declare(strict_types=1);

namespace App\Domain\Post;

use App\Models\Post;

final class PostPinStateService
{
    public function setPinState(Post $post, bool $isPinned, ?int $pinnedByUserId = null): void
    {
        if ($isPinned) {
            $post->is_pinned = true;
            $post->pinned_at = now()->toDateTimeString();
            $post->pinned_by = $pinnedByUserId === null ? null : max(0, $pinnedByUserId);
            $post->save();

            return;
        }

        $post->is_pinned = false;
        $post->pinned_at = null;
        $post->pinned_by = null;
        $post->save();
    }
}
