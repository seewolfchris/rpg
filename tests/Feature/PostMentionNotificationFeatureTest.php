<?php

namespace Tests\Feature;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Notifications\CharacterMentionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PostMentionNotificationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_mention_notifies_mentioned_character_owner_when_enabled(): void
    {
        config(['features.wave4.mentions' => true]);

        [$campaign, $scene, $author, $mentionedOwner, $mentionCharacter] = $this->seedContext();

        $this->actingAs($author)->post(route('campaigns.scenes.posts.store', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]), [
            'post_type' => 'ooc',
            'content_format' => 'markdown',
            'content' => 'Bitte kurz abstimmen mit @'.$mentionCharacter->name.' zum naechsten Zug.',
        ])->assertRedirect();

        $notification = $mentionedOwner->fresh()->unreadNotifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame('character_mention', $notification->data['kind'] ?? null);
        $this->assertSame($mentionCharacter->name, ($notification->data['mentioned_characters'][0] ?? null));
    }

    public function test_mentions_are_ignored_when_feature_is_disabled(): void
    {
        config(['features.wave4.mentions' => false]);

        [$campaign, $scene, $author, $mentionedOwner, $mentionCharacter] = $this->seedContext();

        $this->actingAs($author)->post(route('campaigns.scenes.posts.store', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]), [
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'Ping an @'.$mentionCharacter->name,
        ])->assertRedirect();

        $this->assertSame(0, $mentionedOwner->fresh()->unreadNotifications()->count());
    }

    public function test_mentions_respect_user_preference_when_database_channel_is_disabled(): void
    {
        config(['features.wave4.mentions' => true]);

        [$campaign, $scene, $author, $mentionedOwner, $mentionCharacter] = $this->seedContext();
        $mentionedOwner->notification_preferences = [
            'character_mention' => [
                'database' => false,
                'mail' => false,
            ],
        ];
        $mentionedOwner->save();

        $this->actingAs($author)->post(route('campaigns.scenes.posts.store', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]), [
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'Ping an @'.$mentionCharacter->name,
        ])->assertRedirect();

        $this->assertSame(0, $mentionedOwner->fresh()->unreadNotifications()->count());
    }

    public function test_mentions_can_be_sent_via_mail_when_configured(): void
    {
        config(['features.wave4.mentions' => true]);
        Notification::fake();

        [$campaign, $scene, $author, $mentionedOwner, $mentionCharacter] = $this->seedContext();
        $mentionedOwner->notification_preferences = [
            'character_mention' => [
                'database' => false,
                'mail' => true,
            ],
        ];
        $mentionedOwner->save();

        $this->actingAs($author)->post(route('campaigns.scenes.posts.store', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]), [
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'Rufe @'.$mentionCharacter->name.' fuer die Szene.',
        ])->assertRedirect();

        Notification::assertSentTo(
            [$mentionedOwner],
            CharacterMentionNotification::class,
            function (CharacterMentionNotification $notification, array $channels): bool {
                return $channels === ['mail'];
            }
        );
    }

    public function test_post_update_with_same_mention_does_not_duplicate_notification(): void
    {
        config(['features.wave4.mentions' => true]);

        [$campaign, $scene, $author, $mentionedOwner, $mentionCharacter] = $this->seedContext();

        $createResponse = $this->actingAs($author)->post(route('campaigns.scenes.posts.store', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]), [
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'Start mit @'.$mentionCharacter->name,
        ]);
        $createResponse->assertRedirect();

        $post = Post::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', $author->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(1, $mentionedOwner->fresh()->unreadNotifications()->count());

        $this->actingAs($author)->patch(route('posts.update', [
            'world' => $campaign->world,
            'post' => $post,
        ]), [
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'Bearbeiteter Beitrag mit @'.$mentionCharacter->name.' und mehr Kontext.',
            'ic_quote' => '',
        ])->assertRedirect();

        $this->assertSame(1, $mentionedOwner->fresh()->unreadNotifications()->count());
        $this->assertDatabaseCount('post_mentions', 1);
    }

    /**
     * @return array{0: Campaign, 1: Scene, 2: User, 3: User, 4: Character}
     */
    private function seedContext(): array
    {
        $gm = User::factory()->gm()->create();
        $author = User::factory()->create();
        $mentionedOwner = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => false,
        ]);

        CampaignMembership::query()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $author->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => (int) $gm->id,
            'assigned_at' => now(),
        ]);

        CampaignMembership::query()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $mentionedOwner->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => (int) $gm->id,
            'assigned_at' => now(),
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $mentionCharacter = Character::factory()->create([
            'user_id' => $mentionedOwner->id,
            'world_id' => $campaign->world_id,
            'name' => 'ArinVale',
        ]);

        return [$campaign, $scene, $author, $mentionedOwner, $mentionCharacter];
    }
}
