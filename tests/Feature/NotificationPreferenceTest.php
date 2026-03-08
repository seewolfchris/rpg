<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Notifications\PostModerationStatusNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_notification_preferences(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch(route('notifications.preferences.update'), [
            'post_moderation_database' => '1',
            'post_moderation_mail' => '1',
            'post_moderation_browser' => '0',
            'scene_new_post_database' => '0',
            'scene_new_post_mail' => '0',
            'scene_new_post_browser' => '1',
            'campaign_invitation_database' => '1',
            'campaign_invitation_mail' => '0',
            'campaign_invitation_browser' => '0',
        ]);

        $response->assertRedirect(route('notifications.preferences'));

        $preferences = $user->fresh()->resolvedNotificationPreferences();
        $this->assertTrue((bool) data_get($preferences, 'post_moderation.database'));
        $this->assertTrue((bool) data_get($preferences, 'post_moderation.mail'));
        $this->assertFalse((bool) data_get($preferences, 'post_moderation.browser'));
        $this->assertTrue((bool) data_get($preferences, 'scene_new_post.database'));
        $this->assertFalse((bool) data_get($preferences, 'scene_new_post.mail'));
        $this->assertTrue((bool) data_get($preferences, 'scene_new_post.browser'));
        $this->assertTrue((bool) data_get($preferences, 'campaign_invitation.database'));
        $this->assertFalse((bool) data_get($preferences, 'campaign_invitation.mail'));
        $this->assertFalse((bool) data_get($preferences, 'campaign_invitation.browser'));
    }

    public function test_enabling_browser_channel_keeps_database_channel_enabled_for_storage(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch(route('notifications.preferences.update'), [
            'post_moderation_database' => '0',
            'post_moderation_mail' => '0',
            'post_moderation_browser' => '1',
            'scene_new_post_database' => '0',
            'scene_new_post_mail' => '0',
            'scene_new_post_browser' => '0',
            'campaign_invitation_database' => '0',
            'campaign_invitation_mail' => '0',
            'campaign_invitation_browser' => '0',
        ]);

        $response->assertRedirect(route('notifications.preferences'));

        $preferences = $user->fresh()->resolvedNotificationPreferences();

        $this->assertTrue((bool) data_get($preferences, 'post_moderation.database'));
        $this->assertTrue((bool) data_get($preferences, 'post_moderation.browser'));
        $this->assertFalse((bool) data_get($preferences, 'post_moderation.mail'));
    }

    public function test_disabled_in_app_preference_prevents_database_notification(): void
    {
        [$gm, $player, $campaign, $scene, $character] = $this->seedSceneContext();

        $player->notification_preferences = [
            'post_moderation' => [
                'database' => false,
                'mail' => false,
            ],
            'scene_new_post' => [
                'database' => true,
                'mail' => false,
            ],
        ];
        $player->save();

        $post = Post::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'character_id' => $character->id,
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'content' => 'Im Mondlicht knirscht der Stein.',
            'moderation_status' => 'pending',
        ]);

        $this->actingAs($gm)->patch(route('posts.moderate', $post), [
            'moderation_status' => 'approved',
        ])->assertRedirect();

        $this->assertSame(0, $player->fresh()->notifications()->count());
    }

    public function test_mail_channel_is_used_when_enabled_and_database_is_off(): void
    {
        [$gm, $player, $campaign, $scene, $character] = $this->seedSceneContext();

        $player->notification_preferences = [
            'post_moderation' => [
                'database' => false,
                'mail' => true,
            ],
            'scene_new_post' => [
                'database' => true,
                'mail' => false,
            ],
        ];
        $player->save();

        $post = Post::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'character_id' => $character->id,
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'content' => 'Ein Funken im Schweigen.',
            'moderation_status' => 'pending',
        ]);

        Notification::fake();

        $this->actingAs($gm)->patch(route('posts.moderate', $post), [
            'moderation_status' => 'approved',
        ])->assertRedirect();

        Notification::assertSentTo(
            [$player],
            PostModerationStatusNotification::class,
            function (PostModerationStatusNotification $notification, array $channels): bool {
                return $channels === ['mail'];
            },
        );
    }

    /**
     * @return array{0: User, 1: User, 2: Campaign, 3: Scene, 4: Character}
     */
    private function seedSceneContext(): array
    {
        $gm = User::factory()->gm()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $character = Character::factory()->create([
            'user_id' => $player->id,
        ]);

        return [$gm, $player, $campaign, $scene, $character];
    }
}
