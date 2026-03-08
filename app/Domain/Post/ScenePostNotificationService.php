<?php

namespace App\Domain\Post;

use App\Models\Post;
use App\Models\SceneSubscription;
use App\Models\User;
use App\Notifications\SceneNewPostNotification;
use Illuminate\Support\Facades\Notification;

class ScenePostNotificationService
{
    public function notifySceneParticipants(Post $post, User $author): int
    {
        $post->loadMissing(['scene.campaign']);

        $recipientIds = SceneSubscription::query()
            ->where('scene_id', $post->scene_id)
            ->where('user_id', '!=', $author->id)
            ->where('is_muted', false)
            ->pluck('user_id')
            ->unique()
            ->values();

        if ($recipientIds->isEmpty()) {
            return 0;
        }

        $recipients = User::query()->whereIn('id', $recipientIds)->get();

        if ($recipients->isEmpty()) {
            return 0;
        }

        Notification::send($recipients, new SceneNewPostNotification(
            post: $post,
            author: $author,
        ));

        return $recipients->count();
    }
}
