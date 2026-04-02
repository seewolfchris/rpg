<?php

namespace App\Providers;

use App\Support\NavigationCounters;
use App\Support\PushNarrativeTextResolver;
use App\Support\WorldThemeResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

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
    }
}
