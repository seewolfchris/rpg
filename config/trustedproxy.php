<?php

use Illuminate\Http\Request;

$trustedProxies = env('TRUSTED_PROXIES');
$normalizedAppEnv = strtolower((string) env('APP_ENV', 'production'));
$isProduction = in_array(
    $normalizedAppEnv,
    ['prod', 'production'],
    true
);

if ($normalizedAppEnv === 'testing') {
    $trustedProxies = '*';
} elseif ($trustedProxies === null && ! $isProduction) {
    $trustedProxies = '*';
}

return [
    'proxies' => $trustedProxies,

    'headers' => env(
        'TRUSTED_PROXY_HEADERS',
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_PREFIX |
        Request::HEADER_X_FORWARDED_AWS_ELB
    ),
];
