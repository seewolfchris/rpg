<?php

namespace App\Notifications;

use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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
}
