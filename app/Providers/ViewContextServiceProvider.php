<?php

namespace App\Providers;

use App\Models\World;
use App\Support\NavigationCounters;
use App\Support\WorldThemeResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewContextServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        URL::defaults([
            'world' => World::defaultSlug(),
        ]);

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
