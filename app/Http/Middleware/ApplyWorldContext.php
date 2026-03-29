<?php

namespace App\Http\Middleware;

use App\Models\World;
use App\Support\WorldThemeResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class ApplyWorldContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeWorld = $request->route('world');

        $slug = null;

        if ($routeWorld instanceof World) {
            $slug = $routeWorld->slug;

            if ($request->hasSession()) {
                $request->session()->put('world_slug', $slug);
            }
        } elseif (is_string($routeWorld) && $routeWorld !== '') {
            $slug = $routeWorld;
        } elseif ($request->hasSession()) {
            $sessionWorld = $request->session()->get('world_slug');
            $slug = is_string($sessionWorld) && $sessionWorld !== ''
                ? $sessionWorld
                : null;
        }

        $resolvedSlug = $slug ?? World::defaultSlug();

        $request->attributes->set('active_world_slug', $resolvedSlug);
        $request->attributes->set(
            'active_world_theme',
            app(WorldThemeResolver::class)->resolve($resolvedSlug)
        );

        URL::defaults([
            'world' => $resolvedSlug,
        ]);

        return $next($request);
    }
}
