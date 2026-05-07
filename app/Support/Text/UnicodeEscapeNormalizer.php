<?php

declare(strict_types=1);

namespace App\Support\Text;

final class UnicodeEscapeNormalizer
{
    private const BROKEN_U00_PATTERN = '/(?<!\\\\)u(00(?:e4|f6|fc|df|c4|d6|dc))/i';

    public function normalizeVisibleText(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $normalized = (string) $value;
        if ($normalized === '') {
            return '';
        }

        $normalized = $this->decodeBackslashUnicodeEscapes($normalized);
        $normalized = $this->decodeBrokenU00Escapes($normalized);

        return $normalized;
    }

    private function decodeBackslashUnicodeEscapes(string $value): string
    {
        $decoded = preg_replace_callback(
            '/(\\\\+)u([0-9a-fA-F]{4})/',
            fn (array $matches): string => $this->codePointToChar((int) hexdec((string) $matches[2]), (string) $matches[0]),
            $value
        );

        return is_string($decoded) ? $decoded : $value;
    }

    private function decodeBrokenU00Escapes(string $value): string
    {
        $decoded = preg_replace_callback(
            self::BROKEN_U00_PATTERN,
            fn (array $matches): string => $this->codePointToChar((int) hexdec((string) $matches[1]), (string) $matches[0]),
            $value
        );

        return is_string($decoded) ? $decoded : $value;
    }

    private function codePointToChar(int $codePoint, string $fallback): string
    {
        if ($codePoint <= 0 || $codePoint > 0x10FFFF) {
            return $fallback;
        }

        $character = mb_chr($codePoint, 'UTF-8');

        return is_string($character) ? $character : $fallback;
    }
}
