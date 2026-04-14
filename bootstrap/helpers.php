<?php

declare(strict_types=1);

if (! function_exists('envBool')) {
    function envBool(string $key, bool $default): bool
    {
        return \App\Support\ConfigEnv::boolean(env($key, $default), $default);
    }
}
