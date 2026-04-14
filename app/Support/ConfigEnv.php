<?php

namespace App\Support;

final class ConfigEnv
{
    public static function boolean(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (! is_scalar($value)) {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return is_bool($parsed) ? $parsed : $default;
    }
}
