<?php

namespace App\Providers;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class SecurityHeadersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(RequestHandled::class, function (RequestHandled $event): void {
            $request = $event->request;
            $response = $event->response;
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

            if (! $request->isSecure()) {
                return;
            }

            $hstsMaxAge = max(0, (int) config('security.hsts_max_age', 31536000));

            if ($hstsMaxAge <= 0) {
                return;
            }

            $response->headers->set(
                'Strict-Transport-Security',
                'max-age='.$hstsMaxAge.'; includeSubDomains'
            );
        });
    }
}
