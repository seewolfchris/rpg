<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationPollTest extends TestCase
{
    use RefreshDatabase;

    public function test_poll_requires_authentication(): void
    {
        $this->get(route('notifications.poll'))
            ->assertRedirect(route('login'));
    }

    public function test_poll_filters_unread_notifications_to_browser_enabled_kinds(): void
    {
        $user = User::factory()->create([
            'notification_preferences' => [
                'post_moderation' => [
                    'database' => true,
                    'mail' => false,
                    'browser' => false,
                ],
                'scene_new_post' => [
                    'database' => true,
                    'mail' => false,
                    'browser' => true,
                ],
                'campaign_invitation' => [
                    'database' => true,
                    'mail' => false,
                    'browser' => false,
                ],
            ],
        ]);

        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\SceneNewPostNotification',
            'data' => [
                'kind' => 'scene_new_post',
                'title' => 'Szenen-Hinweis',
                'message' => 'Neue Aktivitaet in Szene Aschering.',
                'action_url' => route('notifications.index'),
            ],
            'read_at' => null,
        ]);

        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\PostModerationStatusNotification',
            'data' => [
                'kind' => 'post_moderation',
                'title' => 'Moderation',
                'message' => 'Dein Beitrag wurde freigegeben.',
                'action_url' => route('notifications.index'),
            ],
            'read_at' => null,
        ]);

        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\SceneNewPostNotification',
            'data' => [
                'kind' => 'scene_new_post',
                'title' => 'Gelesener Hinweis',
                'message' => 'Bereits gelesen.',
                'action_url' => route('notifications.index'),
            ],
            'read_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson(route('notifications.poll'));

        $response->assertOk();
        $response->assertJsonPath('browser_enabled_kinds.0', 'scene_new_post');
        $response->assertJsonPath('unread_count', 2);
        $response->assertJsonCount(1, 'notifications');
        $response->assertJsonPath('notifications.0.kind', 'scene_new_post');
    }

    public function test_poll_returns_empty_payload_when_browser_notifications_are_disabled(): void
    {
        $user = User::factory()->create([
            'notification_preferences' => [
                'post_moderation' => [
                    'database' => true,
                    'mail' => false,
                    'browser' => false,
                ],
                'scene_new_post' => [
                    'database' => true,
                    'mail' => false,
                    'browser' => false,
                ],
                'campaign_invitation' => [
                    'database' => true,
                    'mail' => false,
                    'browser' => false,
                ],
            ],
        ]);

        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\SceneNewPostNotification',
            'data' => [
                'kind' => 'scene_new_post',
                'title' => 'Szenen-Hinweis',
                'message' => 'Neue Aktivitaet in Szene Aschering.',
                'action_url' => route('notifications.index'),
            ],
            'read_at' => null,
        ]);

        $response = $this->actingAs($user)->getJson(route('notifications.poll'));

        $response->assertOk();
        $response->assertJsonPath('browser_enabled_kinds', []);
        $response->assertJsonPath('unread_count', 0);
        $response->assertJsonPath('notifications', []);
    }
}
