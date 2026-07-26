<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use App\Models\World;
use App\Rules\WebPushEndpointAllowed;
use App\Support\Observability\StructuredLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class WebPushSubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_subscribe_webpush_endpoint(): void
    {
        $world = World::query()->where('slug', 'chroniken-der-asche')->firstOrFail();

        $this->post(route('api.webpush.subscribe'), [
            'world_slug' => $world->slug,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/subscription-abc',
            'public_key' => 'public-key-1',
            'auth_token' => 'auth-token-1',
            'content_encoding' => 'aes128gcm',
        ])->assertRedirect(route('login'));
    }

    public function test_user_can_subscribe_and_reassign_subscription_to_another_world(): void
    {
        $user = User::factory()->create();
        $defaultWorld = World::query()->where('slug', 'chroniken-der-asche')->firstOrFail();
        $otherWorld = World::factory()->create([
            'slug' => 'schattenhafen',
            'position' => 999,
            'is_active' => true,
        ]);

        $endpoint = 'https://fcm.googleapis.com/fcm/send/shared-endpoint';

        $this->actingAs($user)->postJson(route('api.webpush.subscribe'), [
            'world_slug' => $defaultWorld->slug,
            'endpoint' => $endpoint,
            'public_key' => 'public-key-1',
            'auth_token' => 'auth-token-1',
            'content_encoding' => 'aes128gcm',
        ])->assertOk();

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'world_id' => $defaultWorld->id,
            'endpoint' => $endpoint,
            'subscribable_type' => $user->getMorphClass(),
            'subscribable_id' => $user->id,
            'device_name' => null,
        ]);

        $this->assertNotNull(PushSubscription::query()->where('endpoint', $endpoint)->value('last_used_at'));

        $this->actingAs($user)->postJson(route('api.webpush.subscribe'), [
            'world_slug' => $otherWorld->slug,
            'endpoint' => $endpoint,
            'public_key' => 'public-key-2',
            'auth_token' => 'auth-token-2',
            'content_encoding' => 'aesgcm',
        ])->assertOk();

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'world_id' => $otherWorld->id,
            'endpoint' => $endpoint,
            'public_key' => 'public-key-2',
            'auth_token' => 'auth-token-2',
            'content_encoding' => 'aesgcm',
        ]);
    }

    public function test_user_can_unsubscribe_endpoint_in_world_context(): void
    {
        $user = User::factory()->create();
        $world = World::query()->where('slug', 'chroniken-der-asche')->firstOrFail();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/remove-me';

        $this->actingAs($user)->postJson(route('api.webpush.subscribe'), [
            'world_slug' => $world->slug,
            'endpoint' => $endpoint,
            'public_key' => 'public-key-1',
            'auth_token' => 'auth-token-1',
            'content_encoding' => 'aes128gcm',
        ])->assertOk();

        $this->actingAs($user)->postJson(route('api.webpush.unsubscribe'), [
            'world_slug' => $world->slug,
            'endpoint' => $endpoint,
        ])->assertOk()->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('push_subscriptions', [
            'user_id' => $user->id,
            'world_id' => $world->id,
            'endpoint' => $endpoint,
        ]);
    }

    public function test_inactive_world_is_rejected_for_subscription_updates(): void
    {
        $user = User::factory()->create();
        $inactiveWorld = World::factory()->create([
            'slug' => 'abgelegte-welt',
            'is_active' => false,
            'position' => 998,
        ]);

        $this->actingAs($user)->postJson(route('api.webpush.subscribe'), [
            'world_slug' => $inactiveWorld->slug,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/inactive-world',
            'public_key' => 'public-key-1',
            'auth_token' => 'auth-token-1',
            'content_encoding' => 'aes128gcm',
        ])->assertUnprocessable();
    }

    public function test_non_https_endpoint_is_rejected_for_subscription_updates(): void
    {
        $user = User::factory()->create();
        $world = World::query()->where('slug', 'chroniken-der-asche')->firstOrFail();

        $this->actingAs($user)->postJson(route('api.webpush.subscribe'), [
            'world_slug' => $world->slug,
            'endpoint' => 'http://fcm.googleapis.com/fcm/send/insecure-endpoint',
            'public_key' => 'public-key-1',
            'auth_token' => 'auth-token-1',
            'content_encoding' => 'aes128gcm',
        ])->assertUnprocessable()->assertJsonValidationErrors(['endpoint']);
    }

    public function test_unknown_push_endpoint_host_is_rejected_for_subscription_updates(): void
    {
        $user = User::factory()->create();
        $world = World::query()->where('slug', 'chroniken-der-asche')->firstOrFail();

        $this->actingAs($user)->postJson(route('api.webpush.subscribe'), [
            'world_slug' => $world->slug,
            'endpoint' => 'https://example.push.local/subscription/blocked-endpoint',
            'public_key' => 'public-key-1',
            'auth_token' => 'auth-token-1',
            'content_encoding' => 'aes128gcm',
        ])->assertUnprocessable()->assertJsonValidationErrors(['endpoint']);
    }

    public function test_empty_push_endpoint_allowlist_remains_permissive_in_testing(): void
    {
        config()->set('webpush.endpoint_allowed_hosts', []);

        $user = User::factory()->create();
        $world = World::query()->where('slug', 'chroniken-der-asche')->firstOrFail();

        $this->actingAs($user)->postJson(route('api.webpush.subscribe'), [
            'world_slug' => $world->slug,
            'endpoint' => 'https://example.push.local/subscription/testing-endpoint',
            'public_key' => 'public-key-testing',
            'auth_token' => 'auth-token-testing',
            'content_encoding' => 'aes128gcm',
        ])->assertOk();
    }

    public function test_empty_push_endpoint_allowlist_fails_closed_in_production(): void
    {
        config()->set('webpush.endpoint_allowed_hosts', []);

        $originalEnvironment = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $validator = Validator::make(
                ['endpoint' => 'https://fcm.googleapis.com/fcm/send/production-endpoint'],
                ['endpoint' => [new WebPushEndpointAllowed]],
            );

            $this->assertSame(
                ['Die Push-Endpunkt-Allowlist ist nicht konfiguriert.'],
                $validator->errors()->get('endpoint')
            );
        } finally {
            $this->app['env'] = $originalEnvironment;
        }
    }

    public function test_webpush_subscribe_log_context_contains_standardized_domain_event_fields(): void
    {
        $user = User::factory()->create();
        $world = World::query()->where('slug', 'chroniken-der-asche')->firstOrFail();
        $sawExpectedEvent = false;

        $logger = $this->createMock(StructuredLogger::class);
        $logger->expects($this->atLeastOnce())
            ->method('info')
            ->willReturnCallback(function (string $event, array $context) use (&$sawExpectedEvent, $user, $world): void {
                if ($event !== 'webpush.subscription_upserted') {
                    return;
                }

                $sawExpectedEvent = true;

                foreach ([
                    'event',
                    'event_version',
                    'occurred_at',
                    'request_id',
                    'world_slug',
                    'actor_user_id',
                    'target_type',
                    'target_id',
                    'outcome',
                ] as $requiredKey) {
                    $this->assertArrayHasKey($requiredKey, $context);
                }

                $this->assertSame('webpush.subscription_upserted', $context['event']);
                $this->assertSame($world->slug, $context['world_slug']);
                $this->assertSame((int) $user->id, $context['actor_user_id']);
                $this->assertSame('push_endpoint', $context['target_type']);
                $this->assertSame('succeeded', $context['outcome']);
            });

        $this->app->instance(StructuredLogger::class, $logger);

        $this->actingAs($user)->postJson(route('api.webpush.subscribe'), [
            'world_slug' => $world->slug,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/context-check',
            'public_key' => 'public-key-x',
            'auth_token' => 'auth-token-x',
            'content_encoding' => 'aes128gcm',
        ])->assertOk();

        $this->assertTrue($sawExpectedEvent);
    }

    public function test_verified_browser_credentials_transfer_device_on_account_switch(): void
    {
        $owner = User::factory()->create();
        $nextUser = User::factory()->create();
        $world = World::query()->where('slug', 'chroniken-der-asche')->firstOrFail();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/account-switch';

        PushSubscription::query()->create([
            'subscribable_type' => $owner->getMorphClass(),
            'subscribable_id' => $owner->id,
            'user_id' => $owner->id,
            'world_id' => $world->id,
            'endpoint' => $endpoint,
            'public_key' => 'same-browser-key',
            'auth_token' => 'same-browser-token',
            'content_encoding' => 'aes128gcm',
        ]);

        $this->actingAs($nextUser)->postJson(route('api.webpush.subscribe'), [
            'world_slug' => $world->slug,
            'endpoint' => $endpoint,
            'public_key' => 'same-browser-key',
            'auth_token' => 'same-browser-token',
            'content_encoding' => 'aes128gcm',
            'device_name' => 'Firefox auf Computer',
        ])->assertOk();

        $this->assertDatabaseMissing('push_subscriptions', [
            'user_id' => $owner->id,
            'endpoint' => $endpoint,
        ]);
        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $nextUser->id,
            'endpoint' => $endpoint,
            'device_name' => 'Firefox auf Computer',
        ]);
    }

    public function test_account_switch_cannot_take_over_device_with_wrong_credentials(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $world = World::query()->where('slug', 'chroniken-der-asche')->firstOrFail();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/account-switch-blocked';

        PushSubscription::query()->create([
            'subscribable_type' => $owner->getMorphClass(),
            'subscribable_id' => $owner->id,
            'user_id' => $owner->id,
            'world_id' => $world->id,
            'endpoint' => $endpoint,
            'public_key' => 'owner-key',
            'auth_token' => 'owner-token',
            'content_encoding' => 'aes128gcm',
        ]);

        $this->actingAs($otherUser)->postJson(route('api.webpush.subscribe'), [
            'world_slug' => $world->slug,
            'endpoint' => $endpoint,
            'public_key' => 'wrong-key',
            'auth_token' => 'wrong-token',
            'content_encoding' => 'aes128gcm',
        ])->assertUnprocessable()->assertJsonValidationErrors(['endpoint']);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $owner->id,
            'endpoint' => $endpoint,
        ]);
    }

    public function test_browser_credentials_can_release_previous_account_device_association(): void
    {
        $owner = User::factory()->create();
        $currentUser = User::factory()->create();
        $world = World::query()->where('slug', 'chroniken-der-asche')->firstOrFail();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/release-previous-account';

        PushSubscription::query()->create([
            'subscribable_type' => $owner->getMorphClass(),
            'subscribable_id' => $owner->id,
            'user_id' => $owner->id,
            'world_id' => $world->id,
            'endpoint' => $endpoint,
            'public_key' => 'browser-key',
            'auth_token' => 'browser-token',
            'content_encoding' => 'aes128gcm',
        ]);

        $this->actingAs($currentUser)->postJson(route('api.webpush.unsubscribe'), [
            'world_slug' => $world->slug,
            'endpoint' => $endpoint,
            'public_key' => 'browser-key',
            'auth_token' => 'browser-token',
        ])->assertOk()->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('push_subscriptions', [
            'endpoint' => $endpoint,
        ]);
    }

    public function test_user_can_list_and_remove_own_push_devices_but_not_foreign_devices(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $world = World::query()->where('slug', 'chroniken-der-asche')->firstOrFail();

        $ownDevice = PushSubscription::query()->create([
            'subscribable_type' => $user->getMorphClass(),
            'subscribable_id' => $user->id,
            'user_id' => $user->id,
            'world_id' => $world->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/own-device-a',
            'public_key' => 'own-key-a',
            'auth_token' => 'own-token-a',
            'content_encoding' => 'aes128gcm',
            'device_name' => 'Firefox auf Computer',
            'last_used_at' => now(),
        ]);
        $otherOwnDevice = PushSubscription::query()->create([
            'subscribable_type' => $user->getMorphClass(),
            'subscribable_id' => $user->id,
            'user_id' => $user->id,
            'world_id' => $world->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/own-device-b',
            'public_key' => 'own-key-b',
            'auth_token' => 'own-token-b',
            'content_encoding' => 'aes128gcm',
            'device_name' => 'Safari auf Mobilgerät',
            'last_used_at' => now(),
        ]);
        $foreignDevice = PushSubscription::query()->create([
            'subscribable_type' => $otherUser->getMorphClass(),
            'subscribable_id' => $otherUser->id,
            'user_id' => $otherUser->id,
            'world_id' => $world->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/foreign-device',
            'public_key' => 'foreign-key',
            'auth_token' => 'foreign-token',
            'content_encoding' => 'aes128gcm',
            'device_name' => 'Fremdes Gerät',
            'last_used_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('notifications.preferences'))
            ->assertOk()
            ->assertSee('Firefox auf Computer')
            ->assertSee('Safari auf Mobilgerät')
            ->assertDontSee('Fremdes Gerät')
            ->assertDontSee($ownDevice->endpoint);

        $this->actingAs($user)
            ->delete(route('notifications.preferences.push-devices.destroy', $foreignDevice))
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('notifications.preferences.push-devices.destroy', $ownDevice))
            ->assertRedirect(route('notifications.preferences'));

        $this->assertDatabaseMissing('push_subscriptions', ['id' => $ownDevice->id]);

        $this->actingAs($user)
            ->delete(route('notifications.preferences.push-devices.destroy-all'))
            ->assertRedirect(route('notifications.preferences'));

        $this->assertDatabaseMissing('push_subscriptions', ['id' => $otherOwnDevice->id]);
        $this->assertDatabaseHas('push_subscriptions', ['id' => $foreignDevice->id]);
    }
}
