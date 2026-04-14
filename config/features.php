<?php

return [
    'wave3' => [
        // Off-by-default fuer Phase A; Aktivierung erst in Phase B.
        'editor_preview' => envBool('FEATURE_WAVE3_EDITOR_PREVIEW', false),
        'draft_autosave' => envBool('FEATURE_WAVE3_DRAFT_AUTOSAVE', false),
    ],

    'wave4' => [
        // Startpunkte fuer Community-Features.
        'mentions' => envBool('FEATURE_WAVE4_MENTIONS', false),
        'reactions' => envBool('FEATURE_WAVE4_REACTIONS', false),
        'active_characters_week' => envBool('FEATURE_WAVE4_ACTIVE_CHARACTERS', false),
    ],
];
