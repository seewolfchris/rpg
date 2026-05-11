<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->canAccessPlatform()) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if (in_array($routeName, ['account.status', 'logout'], true)) {
            return $next($request);
        }

        return redirect()->route('account.status');
    }
}
