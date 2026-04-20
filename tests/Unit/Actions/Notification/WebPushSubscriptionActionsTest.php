<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Notification;

use App\Actions\Notification\DeleteWebPushSubscriptionAction;
use App\Actions\Notification\UpsertWebPushSubscriptionAction;
use App\Models\PushSubscription;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebPushSubscriptionActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_upsert_creates_subscription_with_world_context(): void
    {
        $user = User::factory()->create();
        $world = World::factory()->create(['is_active' => true]);

        $subscription = app(UpsertWebPushSubscriptionAction::class)->execute(
            user: $user,
            world: $world,
            endpoint: 'https://fcm.googleapis.com/fcm/send/unit-upsert',
            publicKey: 'public-key-a',
            authToken: 'auth-token-a',
            contentEncoding: 'aes128gcm',
        );

        $this->assertDatabaseHas('push_subscriptions', [
            'id' => $subscription->id,
            'user_id' => $user->id,
            'world_id' => $world->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/unit-upsert',
            'subscribable_type' => $user->getMorphClass(),
            'subscribable_id' => $user->id,
        ]);
    }

    public function test_upsert_reassigns_foreign_owned_endpoint_to_current_user(): void
    {
        $world = World::factory()->create(['is_active' => true]);
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/reassign-me';

        PushSubscription::query()->create([
            'subscribable_type' => $ownerA->getMorphClass(),
            'subscribable_id' => $ownerA->id,
            'user_id' => $ownerA->id,
            'world_id' => $world->id,
            'endpoint' => $endpoint,
            'public_key' => 'old-key',
            'auth_token' => 'old-token',
            'content_encoding' => 'aesgcm',
        ]);

        app(UpsertWebPushSubscriptionAction::class)->execute(
            user: $ownerB,
            world: $world,
            endpoint: $endpoint,
            publicKey: 'new-key',
            authToken: 'new-token',
            contentEncoding: 'aes128gcm',
        );

        $this->assertDatabaseMissing('push_subscriptions', [
            'user_id' => $ownerA->id,
            'endpoint' => $endpoint,
        ]);
        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $ownerB->id,
            'endpoint' => $endpoint,
            'public_key' => 'new-key',
            'auth_token' => 'new-token',
            'content_encoding' => 'aes128gcm',
        ]);
    }

    public function test_delete_only_removes_subscription_from_matching_world(): void
    {
        $user = User::factory()->create();
        $worldA = World::factory()->create(['is_active' => true]);
        $worldB = World::factory()->create(['is_active' => true]);
        $endpoint = 'https://fcm.googleapis.com/fcm/send/world-scope';

        PushSubscription::query()->create([
            'subscribable_type' => $user->getMorphClass(),
            'subscribable_id' => $user->id,
            'user_id' => $user->id,
            'world_id' => $worldA->id,
            'endpoint' => $endpoint,
            'public_key' => 'key',
            'auth_token' => 'token',
            'content_encoding' => 'aes128gcm',
        ]);

        $deletedWrongWorld = app(DeleteWebPushSubscriptionAction::class)->execute($user, $worldB, $endpoint);
        $deletedRightWorld = app(DeleteWebPushSubscriptionAction::class)->execute($user, $worldA, $endpoint);

        $this->assertFalse($deletedWrongWorld);
        $this->assertTrue($deletedRightWorld);
        $this->assertDatabaseMissing('push_subscriptions', [
            'user_id' => $user->id,
            'world_id' => $worldA->id,
            'endpoint' => $endpoint,
        ]);
    }

    public function test_delete_is_idempotent_for_same_user_world_endpoint(): void
    {
        $user = User::factory()->create();
        $world = World::factory()->create(['is_active' => true]);
        $endpoint = 'https://fcm.googleapis.com/fcm/send/delete-idempotent';

        PushSubscription::query()->create([
            'subscribable_type' => $user->getMorphClass(),
            'subscribable_id' => $user->id,
            'user_id' => $user->id,
            'world_id' => $world->id,
            'endpoint' => $endpoint,
            'public_key' => 'key',
            'auth_token' => 'token',
            'content_encoding' => 'aes128gcm',
        ]);

        $action = app(DeleteWebPushSubscriptionAction::class);
        $firstDelete = $action->execute($user, $world, $endpoint);
        $secondDelete = $action->execute($user, $world, $endpoint);

        $this->assertTrue($firstDelete);
        $this->assertFalse($secondDelete);
        $this->assertDatabaseMissing('push_subscriptions', [
            'user_id' => $user->id,
            'world_id' => $world->id,
            'endpoint' => $endpoint,
        ]);
    }
}
