<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\World;
use App\Support\Observability\StructuredLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        ]);

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
}
