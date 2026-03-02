<?php

namespace App\Notifications;

use App\Models\CampaignInvitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CampaignInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly CampaignInvitation $invitation,
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

        if ($notifiable->wantsNotificationChannel('campaign_invitation', 'database')) {
            $channels[] = 'database';
        }

        if ($notifiable->wantsNotificationChannel('campaign_invitation', 'mail')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $campaign = $this->invitation->campaign;
        $inviterName = $this->invitation->inviter?->name ?? 'Ein Spielleiter';

        return (new MailMessage)
            ->subject('Neue Kampagneneinladung')
            ->greeting('Hallo '.$notifiable->name.',')
            ->line($inviterName.' hat dich zur Kampagne "'.$campaign->title.'" eingeladen.')
            ->line('Angefragte Rolle: '.strtoupper($this->invitation->role))
            ->action('Einladungen anzeigen', route('campaign-invitations.index'))
            ->line('Du kannst die Einladung annehmen oder ablehnen.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $campaign = $this->invitation->campaign;

        return [
            'kind' => 'campaign_invitation',
            'title' => 'Neue Kampagneneinladung',
            'message' => ($this->invitation->inviter?->name ?? 'Ein Spielleiter')
                .' hat dich zu "'.$campaign->title.'" eingeladen.',
            'action_url' => route('campaign-invitations.index'),
            'campaign_id' => $campaign->id,
            'campaign_title' => $campaign->title,
            'invitation_id' => $this->invitation->id,
            'role' => $this->invitation->role,
            'invited_by' => $this->invitation->invited_by,
        ];
    }
}
