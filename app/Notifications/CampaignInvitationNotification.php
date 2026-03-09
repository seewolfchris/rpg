<?php

namespace App\Notifications;

use App\Models\CampaignInvitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

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

        if ($notifiable->wantsNotificationChannel('campaign_invitation', 'browser')) {
            $channels[] = WebPushChannel::class;
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

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $payload = $this->toArray($notifiable);
        $worldId = $this->worldId();
        $worldSlug = $this->worldSlug($worldId);
        $actionUrl = route('campaign-invitations.index');

        return (new WebPushMessage)
            ->title('Neue Kampagneneinladung')
            ->body((string) data_get($payload, 'message', 'Neue Einladung'))
            ->icon((string) config('webpush.defaults.icon', '/images/icons/icon-192.png'))
            ->badge((string) config('webpush.defaults.badge', '/images/icons/icon-96.png'))
            ->tag('campaign-invitation-'.(int) data_get($payload, 'invitation_id', 0))
            ->action('Einladungen', 'open_invitations')
            ->data([
                'kind' => 'campaign_invitation',
                'invitationId' => (int) data_get($payload, 'invitation_id', 0),
                'campaignId' => (int) data_get($payload, 'campaign_id', 0),
                'worldId' => $worldId,
                'worldSlug' => $worldSlug,
                'canonicalUrl' => $actionUrl,
                'actionUrl' => $actionUrl,
            ])
            ->options([
                'TTL' => (int) config('webpush.defaults.ttl', 300),
            ]);
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

    public function worldId(): int
    {
        $worldId = DB::table('campaigns')
            ->where('id', (int) $this->invitation->campaign_id)
            ->value('world_id');

        return is_numeric($worldId) ? (int) $worldId : 0;
    }

    private function worldSlug(int $worldId): string
    {
        if ($worldId <= 0) {
            return 'chroniken-der-asche';
        }

        $worldSlug = DB::table('worlds')
            ->where('id', $worldId)
            ->value('slug');

        return is_string($worldSlug) && $worldSlug !== ''
            ? $worldSlug
            : 'chroniken-der-asche';
    }
}
