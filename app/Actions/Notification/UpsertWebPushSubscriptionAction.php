<?php

declare(strict_types=1);

namespace App\Actions\Notification;

use App\Models\PushSubscription;
use App\Models\User;
use App\Models\World;
use App\Support\Observability\DomainEventLogger;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

final class UpsertWebPushSubscriptionAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly DomainEventLogger $logger,
    ) {}

    public function execute(
        User $user,
        World $world,
        string $endpoint,
        string $publicKey,
        string $authToken,
        string $contentEncoding,
        ?string $deviceName = null,
    ): PushSubscription {
        try {
            $subscription = $this->runUpsertTransaction(
                user: $user,
                world: $world,
                endpoint: $endpoint,
                publicKey: $publicKey,
                authToken: $authToken,
                contentEncoding: $contentEncoding,
                deviceName: $deviceName,
            );
        } catch (QueryException $exception) {
            if (! $this->isDuplicateEndpointKey($exception)) {
                throw $exception;
            }

            $subscription = $this->runUpsertTransaction(
                user: $user,
                world: $world,
                endpoint: $endpoint,
                publicKey: $publicKey,
                authToken: $authToken,
                contentEncoding: $contentEncoding,
                deviceName: $deviceName,
            );
        }

        $this->logger->info('webpush.subscription_upserted', [
            'actor_user_id' => (int) $user->id,
            'subject_user_id' => (int) $user->id,
            'user_id' => (int) $user->id,
            'world_id' => (int) $world->id,
            'world_slug' => (string) $world->slug,
            'endpoint_hash' => hash('sha256', $endpoint),
            'target_type' => 'push_endpoint',
            'target_id' => hash('sha256', $endpoint),
            'outcome' => 'succeeded',
        ]);

        return $subscription;
    }

    private function runUpsertTransaction(
        User $user,
        World $world,
        string $endpoint,
        string $publicKey,
        string $authToken,
        string $contentEncoding,
        ?string $deviceName,
    ): PushSubscription {
        /** @var PushSubscription $subscription */
        $subscription = $this->db->transaction(function () use (
            $user,
            $world,
            $endpoint,
            $publicKey,
            $authToken,
            $contentEncoding,
            $deviceName,
        ): PushSubscription {
            $lockedWorld = $this->lockAndVerifyWorldContext($world);
            $lockedUser = $this->lockAndVerifyUserContext($user);
            $lockedSubscription = $this->lockExistingSubscription($endpoint);

            if ($lockedSubscription instanceof PushSubscription && ! $this->isOwnedByUser($lockedSubscription, $lockedUser)) {
                if (! $this->credentialsMatch($lockedSubscription, $publicKey, $authToken)) {
                    throw ValidationException::withMessages([
                        'endpoint' => 'Dieses Push-Gerät ist bereits mit einem anderen Konto verknüpft.',
                    ]);
                }

                $lockedSubscription->delete();
                $lockedSubscription = null;
            }

            return $this->persistSubscription(
                user: $lockedUser,
                world: $lockedWorld,
                endpoint: $endpoint,
                publicKey: $publicKey,
                authToken: $authToken,
                contentEncoding: $contentEncoding,
                deviceName: $deviceName,
                subscription: $lockedSubscription,
            );
        }, 3);

        return $subscription;
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
        string $publicKey,
        string $authToken,
    ): bool {
        return is_string($subscription->public_key)
            && is_string($subscription->auth_token)
            && hash_equals($subscription->public_key, $publicKey)
            && hash_equals($subscription->auth_token, $authToken);
    }

    private function persistSubscription(
        User $user,
        World $world,
        string $endpoint,
        string $publicKey,
        string $authToken,
        string $contentEncoding,
        ?string $deviceName,
        ?PushSubscription $subscription,
    ): PushSubscription {
        $target = $subscription ?? new PushSubscription;

        $target->subscribable_type = $user->getMorphClass();
        $target->subscribable_id = (int) $user->id;
        $target->user_id = (int) $user->id;
        $target->world_id = (int) $world->id;
        $target->endpoint = $endpoint;
        $target->public_key = $publicKey;
        $target->auth_token = $authToken;
        $target->content_encoding = $contentEncoding;
        $target->device_name = $deviceName;
        $target->last_used_at = now();
        $target->save();

        return $target;
    }

    private function isDuplicateEndpointKey(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo;
        $driverCode = is_array($errorInfo) && isset($errorInfo[1])
            ? (int) $errorInfo[1]
            : 0;
        $message = strtolower($exception->getMessage());

        if ($driverCode === 1062) {
            return true;
        }

        if (str_contains($message, 'duplicate entry')) {
            return true;
        }

        return str_contains($message, 'unique constraint failed');
    }
}
