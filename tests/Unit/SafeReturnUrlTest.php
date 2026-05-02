<?php

namespace Tests\Unit;

use App\Support\Navigation\SafeReturnUrl;
use Illuminate\Http\Request;
use Tests\TestCase;

class SafeReturnUrlTest extends TestCase
{
    public function test_it_accepts_internal_paths_and_query_and_fragment(): void
    {
        $service = app(SafeReturnUrl::class);
        $request = $this->request();

        $this->assertSame('/notifications?page=2', $service->sanitizeCandidate('/notifications?page=2', $request));
        $this->assertSame('/characters/5?tab=inventory#slot-2', $service->sanitizeCandidate('/characters/5?tab=inventory#slot-2', $request));
    }

    public function test_it_rejects_external_protocol_relative_and_script_like_targets(): void
    {
        $service = app(SafeReturnUrl::class);
        $request = $this->request();

        $this->assertNull($service->sanitizeCandidate('https://evil.example/path', $request));
        $this->assertNull($service->sanitizeCandidate('//evil.example/path', $request));
        $this->assertNull($service->sanitizeCandidate('javascript:alert(1)', $request));
        $this->assertNull($service->sanitizeCandidate('data:text/html,boom', $request));
        $this->assertNull($service->sanitizeCandidate('mailto:admin@example.org', $request));
        $this->assertNull($service->sanitizeCandidate('tel:123', $request));
        $this->assertNull($service->sanitizeCandidate('', $request));
        $this->assertNull($service->sanitizeCandidate('characters/5', $request));
    }

    public function test_resolve_uses_fallback_for_backslash_and_encoded_backslash_targets(): void
    {
        $service = app(SafeReturnUrl::class);
        $fallback = '/notifications';

        $unsafeTargets = [
            '/\\evil.example',
            '/\\\\evil.example',
            '/%5Cevil.example',
            '/%5cevil.example',
            '\\evil.example',
            '\\\\evil.example',
            '/foo\\bar',
            '/foo%5Cbar',
            'tel:123',
        ];

        foreach ($unsafeTargets as $target) {
            $request = $this->request(['return_to' => $target]);
            $this->assertSame($fallback, $service->resolve($request, $fallback), 'Target should fallback: '.$target);
        }
    }

    public function test_it_normalizes_same_origin_absolute_urls(): void
    {
        $service = app(SafeReturnUrl::class);
        $request = $this->request();

        $this->assertSame(
            '/w/chroniken-der-asche/campaigns/1/scenes/2?tab=thread#post-10',
            $service->sanitizeCandidate('http://rpg.test/w/chroniken-der-asche/campaigns/1/scenes/2?tab=thread#post-10', $request)
        );
    }

    public function test_resolve_uses_fallback_when_return_to_is_invalid(): void
    {
        $service = app(SafeReturnUrl::class);
        $request = $this->request(['return_to' => 'https://evil.example']);

        $this->assertSame('/notifications', $service->resolve($request, '/notifications'));
    }

    public function test_resolve_can_use_internal_referer_and_reject_external_referer(): void
    {
        $service = app(SafeReturnUrl::class);

        $internalRefererRequest = $this->request([], 'http://rpg.test/characters/5?tab=inventory');
        $this->assertSame('/characters/5?tab=inventory', $service->resolve($internalRefererRequest, '/notifications'));

        $externalRefererRequest = $this->request([], 'https://evil.example/phishing');
        $this->assertSame('/notifications', $service->resolve($externalRefererRequest, '/notifications'));
    }

    public function test_carry_only_uses_explicit_return_to_and_never_referer(): void
    {
        $service = app(SafeReturnUrl::class);

        $requestWithRefererOnly = $this->request([], 'http://rpg.test/characters/8');
        $this->assertNull($service->carry($requestWithRefererOnly));

        $requestWithExplicitReturnTo = $this->request(['return_to' => '/characters/8'], 'https://evil.example/ignored');
        $this->assertSame('/characters/8', $service->carry($requestWithExplicitReturnTo));
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function request(array $input = [], ?string $referer = null): Request
    {
        $server = [
            'HTTP_HOST' => 'rpg.test',
            'SERVER_PORT' => 80,
            'REQUEST_SCHEME' => 'http',
        ];

        if (is_string($referer) && $referer !== '') {
            $server['HTTP_REFERER'] = $referer;
        }

        return Request::create('/unit-test', 'GET', $input, [], [], $server);
    }
}
