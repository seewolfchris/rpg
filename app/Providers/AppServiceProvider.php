<?php

namespace App\Providers;

use App\Models\CampaignInvitation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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

        RateLimiter::for('password-reset', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email', 'unknown'));

            return Limit::perMinute(3)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('password-update', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email', 'unknown'));

            return Limit::perMinute(5)->by('password-update|'.$email.'|'.$request->ip());
        });

        RateLimiter::for('posts', function (Request $request): Limit {
            $key = $request->user()
                ? 'user:'.$request->user()->id
                : 'ip:'.$request->ip();

            return Limit::perMinute(20)->by($key);
        });

        View::composer('layouts.auth', function ($view): void {
            $user = Auth::user();

            if (! $user) {
                $view->with([
                    'unreadNotificationsCount' => 0,
                    'pendingCampaignInvitationsCount' => 0,
                    'bookmarkCount' => 0,
                ]);

                return;
            }

            $unreadNotificationsCount = $user->unreadNotifications()->count();
            $pendingCampaignInvitationsCount = $user->campaignInvitations()
                ->where('status', CampaignInvitation::STATUS_PENDING)
                ->count();
            $bookmarkCount = $user->sceneBookmarks()
                ->whereHas('scene.campaign', fn (Builder $campaignQuery) => $campaignQuery->visibleTo($user))
                ->count();

            $view->with(compact(
                'unreadNotificationsCount',
                'pendingCampaignInvitationsCount',
                'bookmarkCount',
            ));
        });
    }
}
