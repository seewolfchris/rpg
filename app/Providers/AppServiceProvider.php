<?php

namespace App\Providers;

use App\Models\World;
use App\Support\NavigationCounters;
use App\Support\Observability\StructuredLogger;
use App\Support\PushNarrativeTextResolver;
use App\Support\WorldThemeResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\Events\NotificationFailed;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(NavigationCounters::class);
        $this->app->scoped(PushNarrativeTextResolver::class);
        $this->app->scoped(WorldThemeResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        URL::defaults([
            'world' => World::defaultSlug(),
        ]);

        RateLimiter::for('register', function (Request $request): Limit {
            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email', 'unknown'));

            return Limit::perMinute(3)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('password-update', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email', 'unknown'));

            return Limit::perMinute(5)->by('password-update|'.$email.'|'.$request->ip());
        });
        RateLimiter::for('writes', function (Request $request): Limit {
            $key = $request->user()
                ? 'user:'.$request->user()->id
                : 'ip:'.$request->ip();

            return Limit::perMinute(30)->by('writes|'.$key);
        });

        RateLimiter::for('moderation', function (Request $request): Limit {
            $key = $request->user()
                ? 'user:'.$request->user()->id
                : 'ip:'.$request->ip();

            return Limit::perMinute(15)->by('moderation|'.$key);
        });

        RateLimiter::for('notifications', function (Request $request): Limit {
            $key = $request->user()
                ? 'user:'.$request->user()->id
                : 'ip:'.$request->ip();

            return Limit::perMinute(20)->by('notifications|'.$key);
        });

        RateLimiter::for('webpush-subscriptions', function (Request $request): Limit {
            $key = $request->user()
                ? 'user:'.$request->user()->id
                : 'ip:'.$request->ip();
            $worldSlug = (string) $request->input('world_slug', 'unknown-world');

            return Limit::perMinute(20)->by('webpush-subscriptions|'.$key.'|'.$worldSlug);
        });

        Event::listen(NotificationFailed::class, function (NotificationFailed $event): void {
            $statusCode = $event->report->getResponse()?->getStatusCode();

            if (in_array($statusCode, [404, 410], true)) {
                $event->subscription->delete();
            }

            app(StructuredLogger::class)->info('webpush.delivery_failed', [
                'user_id' => data_get($event->subscription, 'user_id'),
                'world_id' => data_get($event->subscription, 'world_id'),
                'endpoint_hash' => sha1((string) $event->subscription->endpoint),
                'status_code' => $statusCode,
                'reason' => $event->report->getReason(),
                'expired' => $event->report->isSubscriptionExpired(),
            ]);
        });

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

        View::composer(['layouts.app', 'layouts.auth', 'welcome'], function ($view): void {
            $request = request();
            $activeWorldSlug = (string) $request->attributes->get('active_world_slug', World::defaultSlug());
            $activeWorldTheme = $request->attributes->get('active_world_theme');

            if (! is_array($activeWorldTheme)) {
                $activeWorldTheme = app(WorldThemeResolver::class)->resolve($activeWorldSlug);
            }

            $view->with(array_merge(
                app(NavigationCounters::class)->forUser(Auth::user()),
                [
                    'activeWorldSlug' => $activeWorldSlug,
                    'activeWorldTheme' => $activeWorldTheme,
                ],
            ));
        });
    }
}
