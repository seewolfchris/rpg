<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Notification;

use App\Actions\Notification\UpdateNotificationPreferencesAction;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateNotificationPreferencesActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_notification_preferences_and_offline_queue_flag(): void
    {
        $user = User::factory()->create([
            'offline_queue_enabled' => false,
            'notification_preferences' => null,
        ]);
        $preferences = [
            'post_moderation' => [
                'database' => true,
                'mail' => false,
                'browser' => true,
            ],
            'scene_new_post' => [
                'database' => true,
                'mail' => true,
                'browser' => false,
            ],
            'campaign_invitation' => [
                'database' => true,
                'mail' => false,
                'browser' => true,
            ],
            'character_mention' => [
                'database' => true,
                'mail' => false,
            ],
        ];

        app(UpdateNotificationPreferencesAction::class)->execute($user, $preferences, true);

        $user->refresh();

        $this->assertTrue($user->offlineQueueEnabled());
        $this->assertSame($preferences, $user->notification_preferences);
    }

    public function test_it_throws_for_unknown_user(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $missingUser = new User;
        $missingUser->setAttribute('id', 999999);

        app(UpdateNotificationPreferencesAction::class)->execute(
            $missingUser,
            User::NOTIFICATION_PREFERENCE_DEFAULTS,
            false,
        );
    }
}
