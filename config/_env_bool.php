<?php

return static function (string $key, bool $default): bool {
    return \App\Support\ConfigEnv::boolean(env($key, $default), $default);
};
