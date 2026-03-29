<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use App\Notifications\CampaignInvitationNotification;
use App\Notifications\PostModerationStatusNotification;
use App\Notifications\SceneNewPostWebPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushNarrativeNotificationPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_scene_new_post_webpush_uses_chroniken_narrative_copy(): void
    {
        $world = World::resolveDefault();
        $author = User::factory()->create(['name' => 'Mara']);
        $receiver = User::factory()->create();
        $campaign = Campaign::factory()->create([
            'world_id' => $world->id,
            'owner_id' => User::factory()->gm()->create()->id,
            'status' => 'active',
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $campaign->owner_id,
            'title' => 'Staubtor',
            'status' => 'open',
        ]);
        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'post_type' => 'ooc',
            'content' => 'Die Asche faellt weiter, waehrend wir auf den Glockenschlag warten.',
            'moderation_status' => 'approved',
            'approved_by' => $campaign->owner_id,
            'approved_at' => now(),
        ]);

        $notification = new SceneNewPostWebPush($post, $author);
        $message = $notification->toWebPush($receiver, $notification)->toArray();

        $this->assertSame('Neues Fluestern aus der Asche', $message['title'] ?? null);
        $this->assertStringContainsString('Mara setzt in "Staubtor" den naechsten Satz:', (string) ($message['body'] ?? ''));
        $this->assertSame('Zur Lesespur', data_get($message, 'actions.0.title'));
    }

    public function test_post_moderation_webpush_uses_chroniken_narrative_copy(): void
    {
        $world = World::resolveDefault();
        $moderator = User::factory()->gm()->create(['name' => 'Wache']);
        $author = User::factory()->create();
        $campaign = Campaign::factory()->create([
            'world_id' => $world->id,
            'owner_id' => $moderator->id,
            'status' => 'active',
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $moderator->id,
            'title' => 'Der Hallensteg',
            'status' => 'open',
        ]);
        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'post_type' => 'ic',
            'content' => 'Staub steigt auf.',
            'moderation_status' => 'pending',
        ]);

        $notification = new PostModerationStatusNotification(
            post: $post,
            moderator: $moderator,
            previousStatus: 'pending',
            newStatus: 'approved',
            moderationNote: null,
        );
        $message = $notification->toWebPush($author, $notification)->toArray();

        $this->assertSame('Das Archiv der Asche wurde geaendert', $message['title'] ?? null);
        $this->assertStringContainsString('traegt nun den Stand "approved"', (string) ($message['body'] ?? ''));
        $this->assertSame('Zum Eintrag', data_get($message, 'actions.0.title'));
    }

    public function test_campaign_invitation_webpush_uses_default_copy_for_unknown_world(): void
    {
        $world = World::factory()->create([
            'name' => 'Nachtmeer',
            'slug' => 'nachtmeer',
        ]);
        $inviter = User::factory()->create(['name' => 'Ilyas']);
        $invitee = User::factory()->create();
        $campaign = Campaign::factory()->create([
            'world_id' => $world->id,
            'owner_id' => $inviter->id,
            'title' => 'Schattenkueste',
            'status' => 'active',
        ]);
        $invitation = CampaignInvitation::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $invitee->id,
            'invited_by' => $inviter->id,
            'status' => CampaignInvitation::STATUS_PENDING,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'created_at' => now(),
        ]);

        $notification = new CampaignInvitationNotification($invitation);
        $message = $notification->toWebPush($invitee, $notification)->toArray();

        $this->assertSame('Neue Kampagneneinladung', $message['title'] ?? null);
        $this->assertSame('Ilyas laedt dich zu "Schattenkueste" ein.', $message['body'] ?? null);
        $this->assertSame('Einladungen', data_get($message, 'actions.0.title'));
    }
}
