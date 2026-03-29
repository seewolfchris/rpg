<?php

namespace App\Notifications;

use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use App\Support\PushNarrativeTextResolver;
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
        $excerpt = Str::limit(trim($this->post->content), 110);

        $actionUrl = route('campaigns.scenes.show', [
            'world' => $world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]).'#post-'.$this->post->id;
        $narrative = app(PushNarrativeTextResolver::class)->resolve(
            kind: 'scene_new_post',
            worldSlug: (string) $world->slug,
            context: [
                'author' => $this->author->name,
                'campaign' => $campaign->title,
                'scene' => $scene->title,
                'excerpt' => $excerpt,
            ],
            fallback: [
                'title' => 'Neue Szene-Aktivitaet',
                'body' => $this->author->name.' schrieb in "'.$scene->title.'": '.$excerpt,
                'action_label' => 'Zur Szene',
            ],
        );

        return (new WebPushMessage)
            ->title($narrative['title'])
            ->body($narrative['body'])
            ->icon((string) config('webpush.defaults.icon', '/images/icons/icon-192.png'))
            ->badge((string) config('webpush.defaults.badge', '/images/icons/icon-96.png'))
            ->tag('scene-new-post-'.$scene->id)
            ->action($narrative['action_label'], 'open_scene')
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
