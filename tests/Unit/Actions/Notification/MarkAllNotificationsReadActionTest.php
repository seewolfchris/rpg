<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Notification;

use App\Actions\Notification\MarkAllNotificationsReadAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarkAllNotificationsReadActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_only_unread_notifications_of_given_user_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $firstUnread = $this->createDatabaseNotification($user, null);
        $secondUnread = $this->createDatabaseNotification($user, null);
        $alreadyRead = $this->createDatabaseNotification($user, now()->subDay());
        $foreignUnread = $this->createDatabaseNotification($otherUser, null);

        $updated = app(MarkAllNotificationsReadAction::class)->execute($user);

        $this->assertSame(2, $updated);
        $this->assertNotNull($user->fresh()->notifications()->whereKey($firstUnread->id)->value('read_at'));
        $this->assertNotNull($user->fresh()->notifications()->whereKey($secondUnread->id)->value('read_at'));
        $this->assertNotNull($user->fresh()->notifications()->whereKey($alreadyRead->id)->value('read_at'));
        $this->assertNull($otherUser->fresh()->notifications()->whereKey($foreignUnread->id)->value('read_at'));
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
