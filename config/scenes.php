<?php

return [
    'default_mood' => 'neutral',

    'moods' => [
        'neutral' => [
            'label' => 'Neutral',
            'theme_class' => 'scene-mood-neutral',
            'badge_class' => '',
        ],
        'dark' => [
            'label' => 'Dunkel',
            'theme_class' => 'scene-mood-dark',
            'badge_class' => '!border-slate-600/80 !bg-slate-900/35 !text-slate-100',
        ],
        'cheerful' => [
            'label' => 'Heiter',
            'theme_class' => 'scene-mood-cheerful',
            'badge_class' => '!border-emerald-600/70 !bg-emerald-900/25 !text-emerald-200',
        ],
        'mystic' => [
            'label' => 'Mystisch',
            'theme_class' => 'scene-mood-mystic',
            'badge_class' => '!border-indigo-600/70 !bg-indigo-900/25 !text-indigo-200',
        ],
        'tense' => [
            'label' => 'Angespannt',
            'theme_class' => 'scene-mood-tense',
            'badge_class' => '!border-red-700/70 !bg-red-900/25 !text-red-200',
        ],
    ],
];
