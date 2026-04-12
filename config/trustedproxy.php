<?php

use Illuminate\Http\Request;

$trustedProxies = env('TRUSTED_PROXIES');
$isProduction = in_array(
    strtolower((string) env('APP_ENV', 'production')),
    ['prod', 'production'],
    true
);

if ($trustedProxies === null && ! $isProduction) {
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
