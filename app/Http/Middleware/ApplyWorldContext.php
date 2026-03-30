<?php

namespace App\Http\Middleware;

use App\Models\World;
use App\Support\WorldThemeResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class ApplyWorldContext
{
    private ?bool $hasWorldsTable = null;

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Route|null $route */
        $route = $request->route();
        $routeWorld = $route?->parameter('world');
        $allowsInactiveWorld = $this->allowsInactiveWorldContext($route);
        $routeHasWorldParameter = $route?->hasParameter('world') ?? false;

        $slug = null;

        if ($routeWorld instanceof World) {
            if (! $allowsInactiveWorld && ! (bool) $routeWorld->is_active) {
                abort(404);
            }

            $slug = $routeWorld->slug;
        } elseif (is_string($routeWorld) && $routeWorld !== '') {
            $slug = $routeWorld;
        } elseif ($request->hasSession()) {
            $sessionWorld = $request->session()->get('world_slug');
            $slug = is_string($sessionWorld) && $sessionWorld !== ''
                ? $sessionWorld
                : null;
        }

        if (! $allowsInactiveWorld && $routeHasWorldParameter && $slug !== null && ! $this->activeWorldSlugExists($slug)) {
            abort(404);
        }

        $resolvedSlug = $allowsInactiveWorld
            ? $this->resolveAnyWorldSlug($slug)
            : $this->resolveActiveWorldSlug($slug);

        if ($request->hasSession()) {
            $request->session()->put('world_slug', $resolvedSlug);
        }

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

    private function allowsInactiveWorldContext(?Route $route): bool
    {
        $routeName = $route?->getName();

        return is_string($routeName) && str_starts_with($routeName, 'admin.worlds.');
    }

    private function resolveActiveWorldSlug(?string $slug): string
    {
        if (is_string($slug) && $slug !== '' && $this->activeWorldSlugExists($slug)) {
            return $slug;
        }

        return $this->fallbackWorldSlug(requireActive: true);
    }

    private function resolveAnyWorldSlug(?string $slug): string
    {
        if (is_string($slug) && $slug !== '' && $this->worldSlugExists($slug)) {
            return $slug;
        }

        return $this->fallbackWorldSlug(requireActive: false);
    }

    private function fallbackWorldSlug(bool $requireActive): string
    {
        $defaultSlug = World::defaultSlug();

        if (! $this->hasWorldsTable()) {
            return $defaultSlug;
        }

        if ($requireActive) {
            if ($this->activeWorldSlugExists($defaultSlug)) {
                return $defaultSlug;
            }

            $firstActiveSlug = World::query()
                ->active()
                ->ordered()
                ->value('slug');

            return is_string($firstActiveSlug) && $firstActiveSlug !== ''
                ? $firstActiveSlug
                : $defaultSlug;
        }

        if ($this->worldSlugExists($defaultSlug)) {
            return $defaultSlug;
        }

        $firstWorldSlug = World::query()
            ->ordered()
            ->value('slug');

        return is_string($firstWorldSlug) && $firstWorldSlug !== ''
            ? $firstWorldSlug
            : $defaultSlug;
    }

    private function activeWorldSlugExists(string $slug): bool
    {
        if (! $this->hasWorldsTable()) {
            return true;
        }

        return World::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->exists();
    }

    private function worldSlugExists(string $slug): bool
    {
        if (! $this->hasWorldsTable()) {
            return true;
        }

        return World::query()
            ->where('slug', $slug)
            ->exists();
    }

    private function hasWorldsTable(): bool
    {
        if ($this->hasWorldsTable !== null) {
            return $this->hasWorldsTable;
        }

        $this->hasWorldsTable = Schema::hasTable('worlds');

        return $this->hasWorldsTable;
    }
}
