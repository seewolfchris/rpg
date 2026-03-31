<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class WebPushEndpointAllowed implements ValidationRule
{
    /**
     * @var list<string>
     */
    private array $allowedHosts;

    /**
     * @param  list<string>|null  $allowedHosts
     */
    public function __construct(?array $allowedHosts = null)
    {
        $hosts = $allowedHosts ?? config('webpush.endpoint_allowed_hosts', []);

        if (! is_array($hosts)) {
            $hosts = [];
        }

        $this->allowedHosts = array_values(array_filter(
            array_map(
                static fn (mixed $host): string => strtolower(trim((string) $host)),
                $hosts
            ),
            static fn (string $host): bool => $host !== ''
        ));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            $fail('Der Push-Endpunkt ist ungueltig.');

            return;
        }

        $parts = parse_url($value);

        if (! is_array($parts)) {
            $fail('Der Push-Endpunkt ist ungueltig.');

            return;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== 'https' || $host === '') {
            $fail('Der Push-Endpunkt muss HTTPS verwenden.');

            return;
        }

        if ($this->allowedHosts === []) {
            return;
        }

        foreach ($this->allowedHosts as $pattern) {
            if ($this->matchesHostPattern($host, $pattern)) {
                return;
            }
        }

        $fail('Der Push-Endpunkt ist fuer diesen Dienst nicht erlaubt.');
    }

    private function matchesHostPattern(string $host, string $pattern): bool
    {
        if ($pattern === '') {
            return false;
        }

        if ($host === $pattern) {
            return true;
        }

        if (! str_starts_with($pattern, '*.')) {
            return false;
        }

        $baseHost = substr($pattern, 2);

        if ($baseHost === '' || $host === $baseHost) {
            return false;
        }

        return str_ends_with($host, '.'.$baseHost);
    }
}
