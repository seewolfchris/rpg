<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\World;
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
            'endpoint' => 'https://example.push.local/subscription/abc',
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

        $endpoint = 'https://example.push.local/subscription/shared-endpoint';

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
        $endpoint = 'https://example.push.local/subscription/remove-me';

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
            'endpoint' => 'https://example.push.local/subscription/inactive-world',
            'public_key' => 'public-key-1',
            'auth_token' => 'auth-token-1',
            'content_encoding' => 'aes128gcm',
        ])->assertUnprocessable();
    }
}

