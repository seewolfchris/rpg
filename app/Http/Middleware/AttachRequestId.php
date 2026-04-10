<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AttachRequestId
{
    private const REQUEST_ID_PATTERN = '/\A[A-Za-z0-9][A-Za-z0-9._-]{7,79}\z/';

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = trim((string) $request->headers->get('X-Request-Id', ''));
        $candidate = Str::limit($incoming, 80, '');
        $requestId = preg_match(self::REQUEST_ID_PATTERN, $candidate) === 1
            ? $candidate
            : (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);
        Log::withContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
