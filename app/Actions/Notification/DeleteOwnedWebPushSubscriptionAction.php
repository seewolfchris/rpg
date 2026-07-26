<?php

declare(strict_types=1);

namespace App\Actions\Notification;

use App\Models\PushSubscription;
use App\Models\User;
use App\Support\Observability\DomainEventLogger;
use Illuminate\Database\DatabaseManager;

final class DeleteOwnedWebPushSubscriptionAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly DomainEventLogger $logger,
    ) {}

    public function execute(User $user, PushSubscription $subscription): bool
    {
        $endpoint = (string) $subscription->endpoint;
        $subscriptionId = (int) $subscription->id;

        $deleted = $this->db->transaction(function () use ($user, $subscriptionId): bool {
            $lockedSubscription = PushSubscription::query()
                ->forUser($user)
                ->whereKey($subscriptionId)
                ->lockForUpdate()
                ->first();

            if (! $lockedSubscription instanceof PushSubscription) {
                return false;
            }

            $lockedSubscription->delete();

            return true;
        }, 3);

        $this->logger->info('webpush.device_deleted', [
            'actor_user_id' => (int) $user->id,
            'target_type' => 'push_device',
            'target_id' => $subscriptionId,
            'endpoint_hash' => hash('sha256', $endpoint),
            'deleted' => $deleted,
            'outcome' => $deleted ? 'succeeded' : 'skipped',
        ]);

        return $deleted;
    }
}
