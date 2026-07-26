<?php

declare(strict_types=1);

namespace App\Actions\Notification;

use App\Models\PushSubscription;
use App\Models\User;
use App\Support\Observability\DomainEventLogger;
use Illuminate\Database\DatabaseManager;

final class DeleteAllWebPushSubscriptionsAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly DomainEventLogger $logger,
    ) {}

    public function execute(User $user): int
    {
        $deletedCount = $this->db->transaction(function () use ($user): int {
            $subscriptionIds = PushSubscription::query()
                ->forUser($user)
                ->lockForUpdate()
                ->pluck('id');

            if ($subscriptionIds->isEmpty()) {
                return 0;
            }

            return PushSubscription::query()
                ->whereIn('id', $subscriptionIds)
                ->delete();
        }, 3);

        $this->logger->info('webpush.devices_deleted_all', [
            'actor_user_id' => (int) $user->id,
            'target_type' => 'push_devices',
            'target_id' => (int) $user->id,
            'deleted_count' => $deletedCount,
            'outcome' => 'succeeded',
        ]);

        return $deletedCount;
    }
}
