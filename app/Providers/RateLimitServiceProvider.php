<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class RateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
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
    }
}
