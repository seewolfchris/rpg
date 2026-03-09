<?php

namespace App\Http\Middleware;

use App\Models\World;
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

        URL::defaults([
            'world' => $slug ?? World::defaultSlug(),
        ]);

        return $next($request);
    }
}
