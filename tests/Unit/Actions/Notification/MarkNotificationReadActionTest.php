<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Notification;

use App\Actions\Notification\MarkNotificationReadAction;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarkNotificationReadActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_unread_notification_as_read_for_owner(): void
    {
        $user = User::factory()->create();
        $notification = $this->createDatabaseNotification($user, null);

        $result = app(MarkNotificationReadAction::class)->execute($user, (string) $notification->id);

        $this->assertSame((string) $notification->id, (string) $result->id);
        $this->assertNotNull($result->read_at);
        $this->assertNotNull(
            $user->fresh()->notifications()->whereKey($notification->id)->value('read_at')
        );
    }

    public function test_it_keeps_existing_read_timestamp_for_already_read_notification(): void
    {
        $user = User::factory()->create();
        $readAt = now()->subMinutes(15);
        $notification = $this->createDatabaseNotification($user, $readAt);

        $result = app(MarkNotificationReadAction::class)->execute($user, (string) $notification->id);

        $this->assertNotNull($result->read_at);
        $this->assertSame(
            $readAt->toDateTimeString(),
            $result->read_at?->toDateTimeString(),
        );
    }

    public function test_it_throws_when_notification_does_not_belong_to_user(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $notification = $this->createDatabaseNotification($otherUser, null);

        $this->expectException(ModelNotFoundException::class);

        try {
            app(MarkNotificationReadAction::class)->execute($owner, (string) $notification->id);
        } finally {
            $this->assertNull(
                $otherUser->fresh()->notifications()->whereKey($notification->id)->value('read_at')
            );
        }
    }

    private function createDatabaseNotification(User $user, \DateTimeInterface|null $readAt): DatabaseNotification
    {
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'tests.notification',
            'data' => [
                'kind' => 'unit',
                'title' => 'Unit Notification',
                'message' => 'Payload',
            ],
            'read_at' => $readAt,
        ]);

        return $notification;
    }
}
