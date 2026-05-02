<?php

namespace App\Support\Navigation;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SafeReturnUrl
{
    public function resolve(Request $request, string $fallback, bool $allowReferer = true): string
    {
        $sanitizedFallback = $this->sanitizeCandidate($fallback, $request) ?? '/';

        $carried = $this->carry($request);
        if (is_string($carried) && $carried !== '') {
            return $carried;
        }

        if ($allowReferer) {
            $referer = $this->sanitizeCandidate((string) $request->headers->get('referer', ''), $request);
            if (is_string($referer) && $referer !== '') {
                return $referer;
            }
        }

        return $sanitizedFallback;
    }

    public function carry(Request $request): ?string
    {
        $candidate = $this->extractReturnTo($request);

        return $this->sanitizeCandidate($candidate, $request);
    }

    public function sanitizeCandidate(?string $candidate, Request $request): ?string
    {
        if (! is_string($candidate)) {
            return null;
        }

        $trimmed = trim($candidate);
        if ($trimmed === '') {
            return null;
        }

        if ($this->containsBackslash($trimmed)) {
            return null;
        }

        if (Str::startsWith($trimmed, '//')) {
            return null;
        }

        if (Str::startsWith($trimmed, '/')) {
            return $this->sanitizeRelativeUrl($trimmed);
        }

        $parsed = parse_url($trimmed);
        if (! is_array($parsed)) {
            return null;
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        if (in_array($scheme, ['javascript', 'data', 'mailto', 'tel'], true)) {
            return null;
        }

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower((string) ($parsed['host'] ?? ''));
        if ($host === '') {
            return null;
        }

        $requestHost = strtolower($request->getHost());
        if ($host !== $requestHost) {
            return null;
        }

        $requestScheme = strtolower($request->getScheme());
        if ($scheme !== $requestScheme) {
            return null;
        }

        $port = isset($parsed['port']) ? (int) $parsed['port'] : null;
        if ($port !== null && $port !== $request->getPort()) {
            return null;
        }

        $path = (string) ($parsed['path'] ?? '/');
        if ($path === '' || ! Str::startsWith($path, '/')) {
            return null;
        }

        $result = $path;
        if (isset($parsed['query']) && (string) $parsed['query'] !== '') {
            $result .= '?'.$parsed['query'];
        }
        if (isset($parsed['fragment']) && (string) $parsed['fragment'] !== '') {
            $result .= '#'.$parsed['fragment'];
        }

        return Str::startsWith($result, '//') || $this->containsBackslash($result) ? null : $result;
    }

    private function sanitizeRelativeUrl(string $candidate): ?string
    {
        $parsed = parse_url($candidate);
        if (! is_array($parsed)) {
            return null;
        }

        $path = (string) ($parsed['path'] ?? '');
        if ($path === '' || ! Str::startsWith($path, '/')) {
            return null;
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        if ($scheme !== '') {
            return null;
        }

        $host = (string) ($parsed['host'] ?? '');
        if ($host !== '') {
            return null;
        }

        $result = $path;
        if (isset($parsed['query']) && (string) $parsed['query'] !== '') {
            $result .= '?'.$parsed['query'];
        }
        if (isset($parsed['fragment']) && (string) $parsed['fragment'] !== '') {
            $result .= '#'.$parsed['fragment'];
        }

        return Str::startsWith($result, '//') || $this->containsBackslash($result) ? null : $result;
    }

    private function containsBackslash(string $value): bool
    {
        if (str_contains($value, '\\')) {
            return true;
        }

        $decoded = $value;
        for ($index = 0; $index < 3; $index++) {
            $next = rawurldecode($decoded);
            if ($next === $decoded) {
                break;
            }

            if (str_contains($next, '\\')) {
                return true;
            }

            $decoded = $next;
        }

        return false;
    }

    private function extractReturnTo(Request $request): ?string
    {
        $value = $request->input('return_to');

        return is_string($value) ? $value : null;
    }
}
