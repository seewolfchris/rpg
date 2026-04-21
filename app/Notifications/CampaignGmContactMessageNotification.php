<?php

namespace App\Notifications;

use App\Models\Campaign;
use App\Models\CampaignGmContactThread;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class CampaignGmContactMessageNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly CampaignGmContactThread $thread,
        private readonly User $author,
        private readonly string $content,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $campaign = $this->thread->campaign;

        if (! $campaign instanceof Campaign) {
            throw new \RuntimeException('CampaignGmContactMessageNotification requires thread campaign relation.');
        }

        return [
            'kind' => 'campaign_gm_contact_message',
            'title' => 'Neue Nachricht im SL-Kontakt',
            'message' => $this->author->name.': '.Str::limit(trim($this->content), 110),
            'action_url' => route('campaigns.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'gm_contact_thread' => $this->thread->id,
            ]).'#gm-contact-panel',
            'campaign_id' => (int) $campaign->id,
            'thread_id' => (int) $this->thread->id,
            'author_id' => (int) $this->author->id,
            'author_name' => (string) $this->author->name,
            'status' => (string) $this->thread->status,
        ];
    }
}
