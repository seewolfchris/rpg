<?php

namespace App\Notifications;

use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use InvalidArgumentException;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class SceneNewPostNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Post $post,
        private readonly User $author,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['database'];
        }

        $channels = [];

        if ($notifiable->wantsNotificationChannel('scene_new_post', 'database')) {
            $channels[] = 'database';
        }

        if ($notifiable->wantsNotificationChannel('scene_new_post', 'mail')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        if (! $notifiable instanceof User) {
            throw new InvalidArgumentException('Scene new post mail notification requires a User notifiable.');
        }

        [$world, $campaign, $scene] = $this->resolveContext();

        $actionUrl = route('campaigns.scenes.show', [
            'world' => $world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]).'#post-'.$this->post->id;

        return (new MailMessage)
            ->subject('Neuer Szenenbeitrag')
            ->greeting('Hallo '.$notifiable->name.',')
            ->line($this->author->name.' hat einen neuen Beitrag in der Szene "'.$scene->title.'" verfasst.')
            ->line(Str::limit(trim($this->post->content), 180))
            ->action('Zur Szene', $actionUrl)
            ->line('Du kannst deine Benachrichtigungspräferenzen jederzeit in deinem Profil anpassen.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        [$world, $campaign, $scene] = $this->resolveContext();

        return [
            'kind' => 'scene_new_post',
            'title' => 'Neuer Beitrag in einer Szene',
            'message' => $this->author->name.' hat in "'.$scene->title.'" gepostet: '
                .Str::limit(trim($this->post->content), 110),
            'action_url' => route('campaigns.scenes.show', [
                'world' => $world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]).'#post-'.$this->post->id,
            'post_id' => $this->post->id,
            'scene_id' => $scene->id,
            'campaign_id' => $campaign->id,
            'author_id' => $this->author->id,
            'author_name' => $this->author->name,
        ];
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
