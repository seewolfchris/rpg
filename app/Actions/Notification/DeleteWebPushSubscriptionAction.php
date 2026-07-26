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

    public function execute(
        User $user,
        World $world,
        string $endpoint,
        ?string $publicKey = null,
        ?string $authToken = null,
    ): bool {
        $deleted = $this->db->transaction(function () use (
            $user,
            $world,
            $endpoint,
            $publicKey,
            $authToken,
        ): bool {
            $this->lockAndVerifyWorldContext($world);
            $lockedUser = $this->lockAndVerifyUserContext($user);
            $lockedSubscription = $this->lockExistingSubscription($endpoint);

            if (! $lockedSubscription instanceof PushSubscription) {
                return false;
            }

            if (
                ! $this->isOwnedByUser($lockedSubscription, $lockedUser)
                && ! $this->credentialsMatch($lockedSubscription, $publicKey, $authToken)
            ) {
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
            'endpoint_hash' => hash('sha256', $endpoint),
            'target_type' => 'push_endpoint',
            'target_id' => hash('sha256', $endpoint),
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

    private function lockExistingSubscription(string $endpoint): ?PushSubscription
    {
        /** @var PushSubscription|null $subscription */
        $subscription = PushSubscription::query()
            ->where('endpoint', $endpoint)
            ->lockForUpdate()
            ->first();

        return $subscription;
    }

    private function isOwnedByUser(PushSubscription $subscription, User $user): bool
    {
        return (int) $subscription->user_id === (int) $user->id
            && (int) $subscription->subscribable_id === (int) $user->id
            && (string) $subscription->subscribable_type === (string) $user->getMorphClass();
    }

    private function credentialsMatch(
        PushSubscription $subscription,
        ?string $publicKey,
        ?string $authToken,
    ): bool {
        return is_string($publicKey)
            && $publicKey !== ''
            && is_string($authToken)
            && $authToken !== ''
            && is_string($subscription->public_key)
            && is_string($subscription->auth_token)
            && hash_equals($subscription->public_key, $publicKey)
            && hash_equals($subscription->auth_token, $authToken);
    }
}
