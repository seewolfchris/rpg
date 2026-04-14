<?php

declare(strict_types=1);

if (! function_exists('envBool')) {
    /**
     * Parse boolean .env values robustly and avoid PHP truthiness pitfalls.
     *
     * Example: the raw string "off" must resolve to false (not true).
     */
    function envBool(string $key, bool $default): bool
    {
        return \App\Support\ConfigEnv::boolean(env($key, $default), $default);
    }
}
