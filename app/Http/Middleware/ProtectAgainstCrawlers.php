<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ProtectAgainstCrawlers
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldBlockUserAgent($request->userAgent())) {
            return response('Automatisierte Crawler sind für diese Anwendung deaktiviert.', 403)
                ->header('X-Robots-Tag', config('privacy.x_robots_tag'));
        }

        $response = $next($request);

        if (config('privacy.send_noindex_headers', true)) {
            $response->headers->set(
                'X-Robots-Tag',
                config('privacy.x_robots_tag')
            );
        }

        return $response;
    }

    private function shouldBlockUserAgent(?string $userAgent): bool
    {
        if (! config('privacy.block_known_bots', true)) {
            return false;
        }

        if (! is_string($userAgent) || trim($userAgent) === '') {
            return false;
        }

        $normalizedUserAgent = Str::lower($userAgent);

        if ($this->isAllowedUserAgent($normalizedUserAgent)) {
            return false;
        }

        $blockedUserAgents = config('privacy.blocked_user_agents', []);

        foreach ($blockedUserAgents as $needle) {
            if (! is_string($needle) || $needle === '') {
                continue;
            }

            if (Str::contains($normalizedUserAgent, Str::lower($needle))) {
                return true;
            }
        }

        return false;
    }

    private function isAllowedUserAgent(string $normalizedUserAgent): bool
    {
        if (! config('privacy.allow_link_preview_bots', true)) {
            return false;
        }

        $allowedUserAgents = config('privacy.allowed_user_agents', []);

        foreach ($allowedUserAgents as $needle) {
            if (! is_string($needle) || $needle === '') {
                continue;
            }

            if (Str::contains($normalizedUserAgent, Str::lower($needle))) {
                return true;
            }
        }

        return false;
    }
}
