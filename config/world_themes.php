<?php

return [
    'default' => [
        'theme_key' => 'default',
        'label' => 'Standardwelt',
        'theme_color' => '#0f0f14',
        'classes' => [
            'html' => 'world-theme-default',
            'body' => 'world-theme-default-shell',
        ],
        'css_variables' => [
            '--world-bg-top' => 'rgba(166,100,38,0.32)',
            '--world-bg-mid' => 'rgba(70,53,36,0.20)',
            '--world-bg-bottom' => '#020202',
            '--world-glow-primary' => 'rgba(166,100,38,0.30)',
            '--world-glow-secondary' => 'rgba(90,66,129,0.16)',
            '--world-texture-ink' => 'rgba(255,244,214,0.06)',
            '--world-texture-shadow' => 'rgba(0,0,0,0.24)',
            '--world-texture-opacity' => '0.46',
            '--narrative-font-size' => 'clamp(1.03rem, 1rem + 0.34vw, 1.18rem)',
            '--narrative-line-height' => '1.9',
            '--narrative-max-width' => '76ch',
            '--ui-font-size' => 'clamp(0.9rem, 0.86rem + 0.18vw, 0.98rem)',
            '--ui-line-height' => '1.45',
        ],
    ],

    'worlds' => [
        'chroniken-der-asche' => [
            'theme_key' => 'chroniken-der-asche',
            'label' => 'Chroniken der Asche',
            'theme_color' => '#18110f',
            'classes' => [
                'html' => 'world-theme-chroniken-der-asche',
                'body' => 'world-theme-chroniken-der-asche-shell',
            ],
            'css_variables' => [
                '--world-bg-top' => 'rgba(188,104,52,0.44)',
                '--world-bg-mid' => 'rgba(104,58,34,0.30)',
                '--world-bg-bottom' => '#090708',
                '--world-glow-primary' => 'rgba(199,117,62,0.42)',
                '--world-glow-secondary' => 'rgba(128,72,44,0.24)',
                '--world-texture-ink' => 'rgba(255,236,198,0.08)',
                '--world-texture-shadow' => 'rgba(0,0,0,0.34)',
                '--world-texture-opacity' => '0.56',
                '--narrative-font-size' => 'clamp(1.06rem, 1.02rem + 0.38vw, 1.22rem)',
                '--narrative-line-height' => '1.95',
            ],
        ],
    ],
];
