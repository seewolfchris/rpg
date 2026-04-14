<?php

return [
    'wave3' => [
        // Off-by-default fuer Phase A; Aktivierung erst in Phase B.
        'editor_preview' => \App\Support\ConfigEnv::boolean(env('FEATURE_WAVE3_EDITOR_PREVIEW', false), false),
        'draft_autosave' => \App\Support\ConfigEnv::boolean(env('FEATURE_WAVE3_DRAFT_AUTOSAVE', false), false),
    ],

    'wave4' => [
        // Startpunkte fuer Community-Features.
        'mentions' => \App\Support\ConfigEnv::boolean(env('FEATURE_WAVE4_MENTIONS', false), false),
        'reactions' => \App\Support\ConfigEnv::boolean(env('FEATURE_WAVE4_REACTIONS', false), false),
        'active_characters_week' => \App\Support\ConfigEnv::boolean(env('FEATURE_WAVE4_ACTIVE_CHARACTERS', false), false),
    ],
];
