<?php

namespace App\Notifications;

use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class SceneNewPostWebPush extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Post $post,
        private readonly User $author,
    ) {}

    /**
     * @return array<int, class-string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return [];
        }

        if (! $notifiable->wantsNotificationChannel('scene_new_post', 'browser')) {
            return [];
        }

        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        [$world, $campaign, $scene] = $this->resolveContext();

        $actionUrl = route('campaigns.scenes.show', [
            'world' => $world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]).'#post-'.$this->post->id;

        return (new WebPushMessage)
            ->title('Neue Szene-Aktivitaet')
            ->body($this->author->name.' schrieb in "'.$scene->title.'": '.Str::limit(trim($this->post->content), 110))
            ->icon((string) config('webpush.defaults.icon', '/images/icons/icon-192.png'))
            ->badge((string) config('webpush.defaults.badge', '/images/icons/icon-96.png'))
            ->tag('scene-new-post-'.$scene->id)
            ->action('Zur Szene', 'open_scene')
            ->data([
                'kind' => 'scene_new_post',
                'postId' => (int) $this->post->id,
                'sceneId' => (int) $scene->id,
                'campaignId' => (int) $campaign->id,
                'worldId' => (int) $world->id,
                'worldSlug' => (string) $world->slug,
                'canonicalUrl' => $actionUrl,
                'actionUrl' => $actionUrl,
            ])
            ->options([
                'TTL' => (int) config('webpush.defaults.ttl', 300),
            ]);
    }

    public function worldId(): int
    {
        [, $campaign] = $this->resolveContext();

        return (int) $campaign->world_id;
    }

    /**
     * @return array{World, Campaign, Scene}
     */
    private function resolveContext(): array
    {
        /** @var Scene $scene */
        $scene = $this->post->scene;
        /** @var Campaign $campaign */
        $campaign = $scene->campaign;
        /** @var World $world */
        $world = $campaign->world;

        return [$world, $campaign, $scene];
    }
}

