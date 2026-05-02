<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SceneNewPostNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationBackNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_read_rejects_external_action_url_and_keeps_explicit_return_to(): void
    {
        $user = User::factory()->create();
        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => SceneNewPostNotification::class,
            'data' => [
                'kind' => 'scene_new_post',
                'title' => 'Neue Nachricht',
                'message' => 'Externe URL soll verworfen werden.',
                'action_url' => 'https://evil.example/phishing',
            ],
            'read_at' => null,
        ]);

        $returnTo = '/notifications?page=2';

        $response = $this->actingAs($user)->post(route('notifications.read', $notification->id), [
            'return_to' => $returnTo,
        ]);

        $response->assertRedirect(route('notifications.index', ['return_to' => $returnTo]));
    }

    public function test_notification_preferences_back_link_uses_explicit_return_to(): void
    {
        $user = User::factory()->create();
        $returnTo = '/notifications?page=2';

        $response = $this->actingAs($user)->get(route('notifications.preferences', [
            'return_to' => $returnTo,
        ]));

        $response->assertOk();
        $response->assertSee('href="'.$returnTo.'"', false);
        $response->assertSee('name="return_to"', false);
        $response->assertSee('value="'.$returnTo.'"', false);
    }

    public function test_notification_hx_inbox_uses_explicit_return_to_in_hidden_field(): void
    {
        $user = User::factory()->create();
        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => SceneNewPostNotification::class,
            'data' => [
                'kind' => 'scene_new_post',
                'title' => 'Neue Nachricht',
                'message' => 'HX return_to soll stabil bleiben.',
                'action_url' => '/notifications',
            ],
            'read_at' => null,
        ]);

        $returnTo = '/notifications?page=2';

        $response = $this->actingAs($user)
            ->withHeader('HX-Request', 'true')
            ->post(route('notifications.read', $notification->id), [
                'return_to' => $returnTo,
            ]);

        $response->assertOk();
        $response->assertSee('name="return_to"', false);
        $response->assertSee('value="'.$returnTo.'"', false);
    }
}
