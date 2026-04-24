<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MutatingRoutesRateLimitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<string, string>
     */
    private const THROTTLE_EXEMPT_MUTATING_WEB_ROUTES = [
        'POST login' => 'Login is throttled inside LoginRequest by email and IP to keep auth failure handling centralized.',
    ];

    /**
     * @var array<string, string>
     */
    private const ACCESS_SCOPE_EXEMPT_MUTATING_WEB_ROUTES = [
        'POST welten/{world}/aktivieren' => 'Public session-only world switch; CSRF-protected by web middleware and limited by throttle:writes.',
    ];

    public function test_mutating_web_routes_have_documented_protection(): void
    {
        $violations = [];

        foreach (Route::getRoutes() as $route) {
            if (! $this->isMutatingRoute($route) || $this->isLocalTestingRoute($route)) {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            if (! $this->hasMiddleware($middleware, 'web')) {
                continue;
            }

            $signature = $this->routeSignature($route);

            if (
                ! $this->hasThrottleMiddleware($middleware)
                && ! array_key_exists($signature, self::THROTTLE_EXEMPT_MUTATING_WEB_ROUTES)
            ) {
                $violations[] = $signature.' is missing throttle middleware.';
            }

            if (
                ! $this->hasAnyMiddleware($middleware, ['auth', 'guest'])
                && ! array_key_exists($signature, self::ACCESS_SCOPE_EXEMPT_MUTATING_WEB_ROUTES)
            ) {
                $violations[] = $signature.' is missing auth/guest scope middleware.';
            }
        }

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function test_write_routes_are_rate_limited(): void
    {
        [$gm, $player, $post] = $this->seedPostContext();

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->actingAs($player)->patch(route('posts.update', ['world' => $post->scene->campaign->world, 'post' => $post]), [
                'post_type' => 'ic',
                'content_format' => 'markdown',
                'character_id' => $post->character_id,
                'content' => 'Aktualisierung #'.$attempt.' in den Aschelanden.',
            ])->assertStatus(302);
        }

        $this->actingAs($player)->patch(route('posts.update', ['world' => $post->scene->campaign->world, 'post' => $post]), [
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'character_id' => $post->character_id,
            'content' => 'Diese Anfrage muss wegen writes-Limit blockiert werden.',
        ])->assertStatus(429);
    }

    public function test_moderation_routes_are_rate_limited(): void
    {
        [$gm, $player, $post] = $this->seedPostContext();

        for ($attempt = 0; $attempt < 15; $attempt++) {
            $this->actingAs($gm)->patch(route('posts.moderate', ['world' => $post->scene->campaign->world, 'post' => $post]), [
                'moderation_status' => $attempt % 2 === 0 ? 'approved' : 'rejected',
            ])->assertStatus(302);
        }

        $this->actingAs($gm)->patch(route('posts.moderate', ['world' => $post->scene->campaign->world, 'post' => $post]), [
            'moderation_status' => 'approved',
        ])->assertStatus(429);
    }

    public function test_notification_routes_are_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->actingAs($user)->post(route('notifications.read-all'))
                ->assertStatus(302);
        }

        $this->actingAs($user)->post(route('notifications.read-all'))
            ->assertStatus(429);
    }

    public function test_webpush_subscription_routes_are_rate_limited(): void
    {
        $user = User::factory()->create();
        $world = World::query()->where('slug', 'chroniken-der-asche')->firstOrFail();

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->actingAs($user)->postJson(route('api.webpush.subscribe'), [
                'world_slug' => $world->slug,
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/rate-limit-'.$attempt,
                'public_key' => 'public-key-'.$attempt,
                'auth_token' => 'auth-token-'.$attempt,
                'content_encoding' => 'aes128gcm',
            ])->assertStatus(200);
        }

        $this->actingAs($user)->postJson(route('api.webpush.subscribe'), [
            'world_slug' => $world->slug,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/rate-limit-blocked',
            'public_key' => 'public-key-blocked',
            'auth_token' => 'auth-token-blocked',
            'content_encoding' => 'aes128gcm',
        ])->assertStatus(429);
    }

    public function test_webpush_subscription_rate_limit_cannot_be_bypassed_by_rotating_world_slug(): void
    {
        $user = User::factory()->create();
        $defaultWorld = World::query()->where('slug', 'chroniken-der-asche')->firstOrFail();
        $otherWorld = World::factory()->create([
            'slug' => 'rate-limit-nebenwelt',
            'is_active' => true,
            'position' => 1337,
        ]);

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $worldSlug = $attempt % 2 === 0
                ? $defaultWorld->slug
                : $otherWorld->slug;

            $this->actingAs($user)->postJson(route('api.webpush.subscribe'), [
                'world_slug' => $worldSlug,
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/rate-limit-rotate-'.$attempt,
                'public_key' => 'public-key-rotate-'.$attempt,
                'auth_token' => 'auth-token-rotate-'.$attempt,
                'content_encoding' => 'aes128gcm',
            ])->assertStatus(200);
        }

        $this->actingAs($user)->postJson(route('api.webpush.subscribe'), [
            'world_slug' => $otherWorld->slug,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/rate-limit-rotate-blocked',
            'public_key' => 'public-key-rotate-blocked',
            'auth_token' => 'auth-token-rotate-blocked',
            'content_encoding' => 'aes128gcm',
        ])->assertStatus(429);
    }

    /**
     * @return array{0: User, 1: User, 2: Post}
     */
    private function seedPostContext(): array
    {
        $gm = User::factory()->gm()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $character = Character::factory()->create([
            'user_id' => $player->id,
        ]);

        $post = Post::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'character_id' => $character->id,
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'content' => 'Der Wind traegt Funken durch die Halle.',
            'moderation_status' => 'pending',
        ]);

        return [$gm, $player, $post];
    }

    private function isMutatingRoute(LaravelRoute $route): bool
    {
        return array_intersect($this->mutatingMethods($route), ['POST', 'PUT', 'PATCH', 'DELETE']) !== [];
    }

    private function isLocalTestingRoute(LaravelRoute $route): bool
    {
        $name = (string) $route->getName();
        $uri = $route->uri();

        return str_starts_with($name, 'e2e.')
            || str_starts_with($uri, '_e2e/');
    }

    /**
     * @param  list<string>  $middleware
     */
    private function hasThrottleMiddleware(array $middleware): bool
    {
        foreach ($middleware as $entry) {
            if (str_starts_with($entry, 'throttle:')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $middleware
     */
    private function hasMiddleware(array $middleware, string $expected): bool
    {
        return in_array($expected, $middleware, true);
    }

    /**
     * @param  list<string>  $middleware
     * @param  list<string>  $expectedMiddleware
     */
    private function hasAnyMiddleware(array $middleware, array $expectedMiddleware): bool
    {
        foreach ($expectedMiddleware as $expected) {
            if ($this->hasMiddleware($middleware, $expected)) {
                return true;
            }
        }

        return false;
    }

    private function routeSignature(LaravelRoute $route): string
    {
        return implode('|', $this->mutatingMethods($route)).' '.$route->uri();
    }

    /**
     * @return list<string>
     */
    private function mutatingMethods(LaravelRoute $route): array
    {
        return array_values(array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']));
    }
}
