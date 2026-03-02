<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if ($roles === []) {
            return $next($request);
        }

        $mappedRoles = [];

        foreach ($roles as $role) {
            $parsedRole = UserRole::tryFrom(strtolower($role));

            if (! $parsedRole) {
                abort(403);
            }

            $mappedRoles[] = $parsedRole->value;
        }

        if (! $user->hasAnyRole(...$mappedRoles)) {
            abort(403);
        }

        return $next($request);
    }
}
