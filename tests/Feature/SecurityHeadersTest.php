<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
