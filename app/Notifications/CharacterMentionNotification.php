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

class CharacterMentionNotification extends Notification
{
    use Queueable;

    /**
     * @param  list<string>  $mentionedCharacterNames
     */
    public function __construct(
        private readonly Post $post,
        private readonly User $author,
        private readonly array $mentionedCharacterNames,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if (method_exists($notifiable, 'wantsNotificationChannel')) {
            if ($notifiable->wantsNotificationChannel('character_mention', 'database')) {
                $channels[] = 'database';
            }

            if ($notifiable->wantsNotificationChannel('character_mention', 'mail')) {
                $channels[] = 'mail';
            }

            return $channels;
        }

        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $post = $this->post->loadMissing('scene.campaign.world');
        /** @var Scene $scene */
        $scene = $post->scene;
        /** @var Campaign $campaign */
        $campaign = $scene->campaign;
        /** @var World $world */
        $world = $campaign->world;
        $mentionedList = implode(', ', $this->mentionedCharacterNames);

        return [
            'kind' => 'character_mention',
            'title' => 'Erwaehnung in einer Szene',
            'message' => $this->author->name.' hat '.$mentionedList.' in "'.$scene->title.'" erwaehnt.',
            'post_id' => $post->id,
            'scene_id' => $scene->id,
            'campaign_id' => $campaign->id,
            'world_slug' => $world->slug,
            'author_name' => $this->author->name,
            'mentioned_characters' => $this->mentionedCharacterNames,
            'action_url' => route('campaigns.scenes.show', [
                'world' => $world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]).'#post-'.$post->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $post = $this->post->loadMissing('scene.campaign.world');
        /** @var Scene $scene */
        $scene = $post->scene;
        /** @var Campaign $campaign */
        $campaign = $scene->campaign;
        /** @var World $world */
        $world = $campaign->world;

        return (new MailMessage)
            ->subject('Erwaehnung in einer Szene')
            ->line('Du wurdest in einem Beitrag erwaehnt.')
            ->action('Zur Szene', route('campaigns.scenes.show', [
                'world' => $world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]).'#post-'.$post->id);
    }
}
