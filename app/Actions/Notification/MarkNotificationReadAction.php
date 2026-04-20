<?php

declare(strict_types=1);

namespace App\Actions\Notification;

use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Notifications\DatabaseNotification;

final class MarkNotificationReadAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function execute(User $user, string $notificationId): DatabaseNotification
    {
        /** @var DatabaseNotification $notification */
        $notification = $this->db->transaction(function () use ($user, $notificationId): DatabaseNotification {
            /** @var DatabaseNotification $lockedNotification */
            $lockedNotification = $user
                ->notifications()
                ->whereKey($notificationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedNotification->read_at === null) {
                $lockedNotification->forceFill([
                    'read_at' => now(),
                ]);
                $lockedNotification->save();
            }

            return $lockedNotification;
        }, 3);

        return $notification;
    }
}
