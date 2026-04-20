<?php

declare(strict_types=1);

namespace App\Actions\Notification;

use App\Models\User;
use Illuminate\Database\DatabaseManager;

final class UpdateNotificationPreferencesAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array<string, array<string, bool>>  $preferences
     */
    public function execute(User $user, array $preferences, bool $offlineQueueEnabled): void
    {
        $this->db->transaction(function () use ($user, $preferences, $offlineQueueEnabled): void {
            $lockedUser = $user
                ->newQuery()
                ->whereKey((int) $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedUser->forceFill([
                'notification_preferences' => $preferences,
                'offline_queue_enabled' => $offlineQueueEnabled,
            ]);
            $lockedUser->save();
        }, 3);
    }
}
