<?php

return [

    /**
     * These are the keys for authentication (VAPID).
     * These keys must be safely stored and should not change.
     */
    'vapid' => [
        'subject' => env('VAPID_SUBJECT'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'pem_file' => env('VAPID_PEM_FILE'),
    ],

    /**
     * This is model that will be used to for push subscriptions.
     */
    'model' => \App\Models\PushSubscription::class,

    /**
     * This is the name of the table that will be created by the migration and
     * used by the PushSubscription model shipped with this package.
     */
    'table_name' => env('WEBPUSH_DB_TABLE', 'push_subscriptions'),

    /**
     * This is the database connection that will be used by the migration and
     * the PushSubscription model shipped with this package.
     */
    'database_connection' => env('WEBPUSH_DB_CONNECTION', env('DB_CONNECTION', 'mysql')),

    /**
     * The Guzzle client options used by Minishlink\WebPush.
     */
    'client_options' => [],

    /**
     * The automatic padding in bytes used by Minishlink\WebPush.
     * Set to false to support Firefox Android with v1 endpoint.
     */
    'automatic_padding' => env('WEBPUSH_AUTOMATIC_PADDING', true),

    /**
     * Project defaults used by notification classes.
     */
    'defaults' => [
        'ttl' => (int) env('WEBPUSH_TTL', 300),
        'icon' => env('WEBPUSH_ICON', '/images/icons/icon-192.png'),
        'badge' => env('WEBPUSH_BADGE', '/images/icons/icon-96.png'),
    ],

    /**
     * Allowed push service hosts for subscription endpoints.
     */
    'endpoint_allowed_hosts' => array_values(array_filter(
        array_map(
            static fn (string $host): string => strtolower(trim($host)),
            explode(
                ',',
                (string) env(
                    'WEBPUSH_ENDPOINT_ALLOWED_HOSTS',
                    'fcm.googleapis.com,fcmregistrations.googleapis.com,*.push.services.mozilla.com,web.push.apple.com,*.web.push.apple.com'
                )
            )
        ),
        static fn (string $host): bool => $host !== ''
    )),

];
