<?php

namespace App\Support\Observability;

use Illuminate\Support\Facades\Log;

class StructuredLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $event, array $context = []): void
    {
        Log::info($event, $this->normalizeContext($context));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function normalizeContext(array $context): array
    {
        $requestId = null;

        if (app()->bound('request')) {
            $request = app('request');
            $fromAttributes = $request?->attributes?->get('request_id');
            $fromHeader = $request?->headers?->get('X-Request-Id');
            $candidate = is_string($fromAttributes) && trim($fromAttributes) !== ''
                ? trim($fromAttributes)
                : (is_string($fromHeader) && trim($fromHeader) !== '' ? trim($fromHeader) : null);

            $requestId = $candidate;
        }

        $merged = array_merge([
            'request_id' => $requestId,
        ], $context);

        return array_filter($merged, static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
