<?php

return [
    'default_status' => 'active',

    'statuses' => [
        'active' => [
            'label' => 'Aktiv',
            'badge_class' => 'border-emerald-600/70 bg-emerald-900/25 text-emerald-200',
        ],
        'pause' => [
            'label' => 'Pause',
            'badge_class' => 'border-amber-600/70 bg-amber-900/25 text-amber-200',
        ],
        'deceased' => [
            'label' => 'Verstorben',
            'badge_class' => 'border-slate-600/80 bg-slate-900/35 text-slate-100',
        ],
        'archived' => [
            'label' => 'Archiviert',
            'badge_class' => 'border-stone-600/80 bg-stone-900/35 text-stone-200',
        ],
    ],
];
