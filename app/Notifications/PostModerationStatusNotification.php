<?php

namespace App\Notifications;

use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use App\Support\PushNarrativeTextResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class PostModerationStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Post $post,
        private readonly User $moderator,
        private readonly string $previousStatus,
        private readonly string $newStatus,
        private readonly ?string $moderationNote = null,
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

        if ($notifiable->wantsNotificationChannel('post_moderation', 'database')) {
            $channels[] = 'database';
        }

        if ($notifiable->wantsNotificationChannel('post_moderation', 'mail')) {
            $channels[] = 'mail';
        }

        if ($notifiable->wantsNotificationChannel('post_moderation', 'browser')) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$world, $campaign, $scene] = $this->resolveContext();

        $mailMessage = (new MailMessage)
            ->subject('Moderationsstatus geändert')
            ->greeting('Hallo '.$notifiable->name.',')
            ->line('Dein Beitrag wurde von '.$this->moderator->name.' moderiert.')
            ->line('Neuer Status: '.$this->newStatus)
            ->action(
                'Beitrag öffnen',
                route('campaigns.scenes.show', [
                    'world' => $world,
                    'campaign' => $campaign,
                    'scene' => $scene,
                ]).'#post-'.$this->post->id,
            )
            ->line('C76-RPG informiert dich automatisch über relevante Änderungen.');

        if ($this->moderationNote !== null) {
            $mailMessage->line('Hinweis: '.$this->moderationNote);
        }

        return $mailMessage;
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        [$world, $campaign, $scene] = $this->resolveContext();
        $actionUrl = route('campaigns.scenes.show', [
            'world' => $world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]).'#post-'.$this->post->id;
        $narrative = app(PushNarrativeTextResolver::class)->resolve(
            kind: 'post_moderation',
            worldSlug: (string) $world->slug,
            context: [
                'status' => $this->newStatus,
                'previous_status' => $this->previousStatus,
                'moderator' => $this->moderator->name,
                'campaign' => $campaign->title,
                'scene' => $scene->title,
            ],
            fallback: [
                'title' => 'Moderationsstatus geaendert',
                'body' => 'Dein Beitrag wurde auf "'.$this->newStatus.'" gesetzt.',
                'action_label' => 'Beitrag oeffnen',
            ],
        );

        return (new WebPushMessage)
            ->title($narrative['title'])
            ->body($narrative['body'])
            ->icon((string) config('webpush.defaults.icon', '/images/icons/icon-192.png'))
            ->badge((string) config('webpush.defaults.badge', '/images/icons/icon-96.png'))
            ->tag('post-moderation-'.$this->post->id)
            ->action($narrative['action_label'], 'open_post')
            ->data([
                'kind' => 'post_moderation',
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

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        [$world, $campaign, $scene] = $this->resolveContext();

        return [
            'kind' => 'post_moderation',
            'title' => 'Moderationsstatus geändert',
            'message' => 'Dein Beitrag wurde von '.$this->moderator->name.' auf "'.$this->newStatus.'" gesetzt.'
                .($this->moderationNote ? ' Grund: '.$this->moderationNote : ''),
            'action_url' => route('campaigns.scenes.show', [
                'world' => $world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]).'#post-'.$this->post->id,
            'post_id' => $this->post->id,
            'scene_id' => $scene->id,
            'campaign_id' => $campaign->id,
            'previous_status' => $this->previousStatus,
            'new_status' => $this->newStatus,
            'moderator_id' => $this->moderator->id,
            'moderation_note' => $this->moderationNote,
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

    public function worldId(): int
    {
        [, $campaign] = $this->resolveContext();

        return (int) $campaign->world_id;
    }
}
