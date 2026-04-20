<?php

declare(strict_types=1);

namespace App\Actions\Notification;

use App\Models\PushSubscription;
use App\Models\User;
use App\Models\World;
use App\Support\Observability\DomainEventLogger;
use Illuminate\Database\DatabaseManager;

final class DeleteWebPushSubscriptionAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly DomainEventLogger $logger,
    ) {}

    public function execute(User $user, World $world, string $endpoint): bool
    {
        $deleted = $this->db->transaction(function () use ($user, $world, $endpoint): bool {
            $lockedWorld = $this->lockAndVerifyWorldContext($world);
            $lockedUser = $this->lockAndVerifyUserContext($user);
            $lockedSubscription = $this->lockExistingSubscription($lockedUser, $lockedWorld, $endpoint);

            if (! $lockedSubscription instanceof PushSubscription) {
                return false;
            }

            $lockedSubscription->delete();

            return true;
        }, 3);

        $this->logger->info('webpush.subscription_deleted', [
            'actor_user_id' => (int) $user->id,
            'subject_user_id' => (int) $user->id,
            'user_id' => (int) $user->id,
            'world_id' => (int) $world->id,
            'world_slug' => (string) $world->slug,
            'endpoint_hash' => sha1($endpoint),
            'target_type' => 'push_endpoint',
            'target_id' => sha1($endpoint),
            'deleted' => $deleted,
            'outcome' => $deleted ? 'succeeded' : 'skipped',
        ]);

        return $deleted;
    }

    private function lockAndVerifyWorldContext(World $world): World
    {
        /** @var World $lockedWorld */
        $lockedWorld = World::query()
            ->whereKey((int) $world->id)
            ->where('is_active', true)
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedWorld;
    }

    private function lockAndVerifyUserContext(User $user): User
    {
        /** @var User $lockedUser */
        $lockedUser = User::query()
            ->whereKey((int) $user->id)
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedUser;
    }

    private function lockExistingSubscription(User $user, World $world, string $endpoint): ?PushSubscription
    {
        /** @var PushSubscription|null $subscription */
        $subscription = PushSubscription::query()
            ->where('endpoint', $endpoint)
            ->where('user_id', (int) $user->id)
            ->where('world_id', (int) $world->id)
            ->where('subscribable_type', $user->getMorphClass())
            ->where('subscribable_id', (int) $user->id)
            ->lockForUpdate()
            ->first();

        return $subscription;
    }
}
