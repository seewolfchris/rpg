<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ApplySecurityHeaders
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $currentPolicy = (string) $response->headers->get('Content-Security-Policy', '');
        $configuredPolicy = trim((string) config('security.content_security_policy', "frame-ancestors 'self'"));
        $frameAncestorsDirective = "frame-ancestors 'self'";

        if ($currentPolicy === '' && $configuredPolicy !== '') {
            $response->headers->set('Content-Security-Policy', $configuredPolicy);
        } elseif ($currentPolicy !== '' && ! str_contains(Str::lower($currentPolicy), 'frame-ancestors')) {
            $response->headers->set(
                'Content-Security-Policy',
                rtrim($currentPolicy, " \t\n\r\0\x0B;").'; '.$frameAncestorsDirective
            );
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set(
            'Referrer-Policy',
            (string) config('security.referrer_policy', 'strict-origin-when-cross-origin')
        );
        $response->headers->set(
            'Permissions-Policy',
            (string) config(
                'security.permissions_policy',
                'accelerometer=(), autoplay=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()'
            )
        );

        // Private HTML responses should not be persisted by browser or service worker caches.
        if ($request->user() && str_contains((string) $response->headers->get('Content-Type', ''), 'text/html')) {
            $response->headers->set('Cache-Control', 'no-store, private, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');

            if ($this->allowsPrivateOfflineReadCache($request)) {
                $response->headers->set('X-C76-Offline-Cache', 'allow-private-html');
            }
        }

        if (! $request->isSecure()) {
            return $response;
        }

        $hstsMaxAge = max(0, (int) config('security.hsts_max_age', 31536000));

        if ($hstsMaxAge > 0) {
            $response->headers->set('Strict-Transport-Security', 'max-age='.$hstsMaxAge.'; includeSubDomains');
        }

        return $response;
    }

    private function allowsPrivateOfflineReadCache(Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        return $request->routeIs('campaigns.scenes.show', 'characters.show');
    }
}
