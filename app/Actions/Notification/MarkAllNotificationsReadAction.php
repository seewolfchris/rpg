<?php

declare(strict_types=1);

namespace App\Actions\Notification;

use App\Models\User;
use Illuminate\Database\DatabaseManager;

final class MarkAllNotificationsReadAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function execute(User $user): int
    {
        return $this->db->transaction(function () use ($user): int {
            return $user
                ->unreadNotifications()
                ->update(['read_at' => now()]);
        }, 3);
    }
}
