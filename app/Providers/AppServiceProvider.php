<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('register', function (Request $request): Limit {
            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('posts', function (Request $request): Limit {
            $key = $request->user()
                ? 'user:'.$request->user()->id
                : 'ip:'.$request->ip();

            return Limit::perMinute(20)->by($key);
        });

        RateLimiter::for('dice-rolls', function (Request $request): Limit {
            $key = $request->user()
                ? 'user:'.$request->user()->id
                : 'ip:'.$request->ip();

            return Limit::perMinute(40)->by($key);
        });
    }
}
