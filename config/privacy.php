<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Suchmaschinen / Crawler Schutz
    |--------------------------------------------------------------------------
    |
    | Best-Effort Schutz gegen Indexierung und bekannte Crawler-User-Agents.
    | Hinweis: User-Agent Spoofing ist technisch moeglich. Fuer maximalen
    | Schutz sollte zusaetzlich auf Webserver-/Firewall-Ebene gefiltert werden.
    |
    */
    'send_noindex_headers' => \App\Support\ConfigEnv::boolean(env('PRIVACY_NOINDEX_HEADERS', true), true),

    'x_robots_tag' => env(
        'PRIVACY_X_ROBOTS_TAG',
        'noindex, nofollow, noarchive, nosnippet, noimageindex, max-snippet:0, max-image-preview:none, max-video-preview:0'
    ),

    'block_known_bots' => \App\Support\ConfigEnv::boolean(env('PRIVACY_BLOCK_KNOWN_BOTS', true), true),

    /*
    |--------------------------------------------------------------------------
    | Link-Preview-Bots erlauben
    |--------------------------------------------------------------------------
    |
    | Fuer OG/Twitter-Vorschauen in Messengern oder Social-Apps koennen
    | bestimmte Vorschau-Crawler zugelassen werden, waehrend Such-/KI-Bots
    | weiterhin geblockt bleiben.
    |
    */
    'allow_link_preview_bots' => \App\Support\ConfigEnv::boolean(env('PRIVACY_ALLOW_LINK_PREVIEW_BOTS', true), true),
    'allowed_user_agents' => [
        'facebookexternalhit',
        'facebot',
        'twitterbot',
        'linkedinbot',
        'slackbot',
        'discordbot',
        'telegrambot',
        'whatsapp',
    ],

    'blocked_user_agents' => [
        'gptbot',
        'oai-searchbot',
        'chatgpt-user',
        'claudebot',
        'claude-searchbot',
        'perplexitybot',
        'perplexity-user',
        'googlebot',
        'googleother',
        'google-extended',
        'bingbot',
        'duckduckbot',
        'yandexbot',
        'baiduspider',
        'applebot',
        'bytespider',
        'ccbot',
        'ahrefsbot',
        'semrushbot',
        'mj12bot',
        'dotbot',
        'petalbot',
        'slurp',
        'sogou',
        'seznambot',
    ],
];
