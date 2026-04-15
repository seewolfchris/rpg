<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_response_contains_security_headers_and_hardened_csp(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertHeader('Content-Security-Policy');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader(
            'Permissions-Policy',
            'accelerometer=(), autoplay=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()'
        );

        $policy = (string) $response->headers->get('Content-Security-Policy', '');
        $this->assertStringContainsString("frame-ancestors 'self'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString("script-src 'self'", $policy);

        preg_match('/script-src[^;]*/', $policy, $scriptDirectiveMatches);
        $scriptDirective = (string) ($scriptDirectiveMatches[0] ?? '');

        $this->assertStringNotContainsString("'unsafe-inline'", $scriptDirective);
    }

    public function test_secure_requests_receive_hsts_header(): void
    {
        $response = $this->get('https://example.test/');

        $response->assertOk();
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_forwarded_https_requests_receive_hsts_header_when_proxy_is_trusted(): void
    {
        config([
            'trustedproxy.proxies' => '*',
        ]);

        $response = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_X_FORWARDED_FOR' => '127.0.0.1',
                'HTTP_X_FORWARDED_PROTO' => 'https',
                'HTTP_X_FORWARDED_HOST' => 'example.test',
                'HTTP_X_FORWARDED_PORT' => '443',
            ])
            ->get('http://example.test/');

        $response->assertOk();
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_health_endpoint_contains_security_headers(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
        $response->assertHeader('Content-Security-Policy');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader(
            'Permissions-Policy',
            'accelerometer=(), autoplay=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()'
        );
    }

    public function test_existing_csp_header_is_extended_with_frame_ancestors_when_missing(): void
    {
        Route::middleware('web')->get('/_security-csp-merge-test', static function () {
            return response('ok')->header('Content-Security-Policy', "default-src 'self'; script-src 'self'");
        });

        $response = $this->get('/_security-csp-merge-test');

        $response->assertOk();
        $policy = (string) $response->headers->get('Content-Security-Policy', '');

        $this->assertStringContainsString("default-src 'self'; script-src 'self'", $policy);
        $this->assertStringContainsString("frame-ancestors 'self'", $policy);
    }

    public function test_authenticated_html_responses_stay_no_store_private_by_default(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $cacheControl = Str::lower((string) $response->headers->get('Cache-Control', ''));
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $response->assertHeader('Pragma', 'no-cache');
        $response->assertHeader('Expires', '0');
        $response->assertHeaderMissing('X-C76-Offline-Cache');
    }

    public function test_offline_private_cache_opt_in_header_is_limited_to_explicit_read_routes(): void
    {
        $user = User::factory()->create();
        $character = Character::factory()
            ->for($user)
            ->create();

        $dashboardResponse = $this->actingAs($user)->get(route('dashboard'));
        $dashboardResponse->assertOk();
        $dashboardResponse->assertHeaderMissing('X-C76-Offline-Cache');

        $characterResponse = $this->actingAs($user)->get(route('characters.show', $character));
        $characterResponse->assertOk();
        $characterCacheControl = Str::lower((string) $characterResponse->headers->get('Cache-Control', ''));
        $this->assertStringContainsString('no-store', $characterCacheControl);
        $this->assertStringContainsString('private', $characterCacheControl);
        $this->assertStringContainsString('max-age=0', $characterCacheControl);
        $characterResponse->assertHeader('X-C76-Offline-Cache', 'allow-private-html');
    }
}
