<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

trait NormalizesWorldCharacterOptionInput
{
    /**
     * @return array<string, mixed>
     */
    private function decodeJsonArray(mixed $raw): array
    {
        if (! is_string($raw)) {
            return [];
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function trimNullable(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
