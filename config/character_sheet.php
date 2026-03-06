<?php

return [
    'attributes' => [
        'mu' => ['label' => 'Mut', 'min' => 30, 'max' => 60],
        'kl' => ['label' => 'Klugheit', 'min' => 30, 'max' => 60],
        'in' => ['label' => 'Intuition', 'min' => 30, 'max' => 60],
        'ch' => ['label' => 'Charisma', 'min' => 30, 'max' => 60],
        'ff' => ['label' => 'Fingerfertigkeit', 'min' => 30, 'max' => 60],
        'ge' => ['label' => 'Gewandtheit', 'min' => 30, 'max' => 60],
        'ko' => ['label' => 'Konstitution', 'min' => 30, 'max' => 60],
        'kk' => ['label' => 'Körperkraft', 'min' => 30, 'max' => 60],
    ],

    'average_max' => 50,

    'origins' => [
        'real_world_beginner' => 'Real-World Anfänger',
        'native_vhaltor' => "Native aus Vhal'Tor",
    ],

    // Falls eine Herkunft hier hinterlegt ist, sind nur diese Spezies erlaubt.
    'origin_species_constraints' => [
        'real_world_beginner' => ['mensch'],
    ],

    'species' => [
        'mensch' => [
            'label' => 'Mensch',
            'description' => 'Vielseitig, anpassungsfähig und ohne angeborene Extreme. Menschen tragen keine festen uralten Lasten, aber auch keine geschenkten Wege.',
            'modifiers' => [],
            'le_bonus' => 0,
            'ae_bonus' => 0,
        ],
        'elf' => [
            'label' => 'Elf',
            'description' => 'Feinsinnig und nah an den verborgenen Strömungen der Welt. Elfen wirken oft entrückt, doch ihre Körper tragen weniger rohe Gewalt.',
            'modifiers' => [
                'in' => 10,
                'ch' => 10,
                'kk' => -5,
            ],
            'le_bonus' => 0,
            'ae_bonus' => 5,
        ],
        'zwerg' => [
            'label' => 'Zwerg',
            'description' => 'Zäh, standhaft und gehärtet durch Stein, Feuer und Schwur. Zwerge tragen Lasten, die andere brechen, zahlen dafür aber mit Beweglichkeit und Diplomatie.',
            'modifiers' => [
                'kk' => 10,
                'ko' => 10,
                'ge' => -5,
                'ch' => -5,
            ],
            'le_bonus' => 5,
            'ae_bonus' => 0,
        ],
    ],

    'callings' => [
        'magier' => [
            'label' => 'Magier',
            'minimums' => ['kl' => 45, 'in' => 40],
            'bonuses' => ['ae_percent' => 10, 'attributes' => ['kl' => 5]],
            'description' => 'Du liest Muster in Asche und Sternen, wo andere nur Rauch sehen. Jede Erkenntnis öffnet Türen, die besser verschlossen blieben.',
        ],
        'ritter' => [
            'label' => 'Ritter',
            'minimums' => ['mu' => 40, 'kk' => 40, 'ge' => 35],
            'bonuses' => ['le_flat' => 5, 'attributes' => ['mu' => 5]],
            'description' => 'Du lebst nach Eid und Klinge in einer Welt ohne klare Herrschaft. Dein Name schützt Schwache, aber bindet dich an alte Schuld.',
        ],
        'abenteurer' => [
            'label' => 'Abenteurer',
            'minimums' => ['mu' => 35, 'in' => 35],
            'bonuses' => ['attributes' => ['in' => 5, 'ge' => 5]],
            'description' => 'Du überlebst durch Instinkt, Tempo und den Mut zum falschen Weg. Wo Karten enden, beginnt dein Revier.',
        ],
        'geistlicher' => [
            'label' => 'Geistlicher',
            'minimums' => ['ch' => 40, 'mu' => 35, 'kl' => 35],
            'bonuses' => ['ae_percent' => 10, 'attributes' => ['ch' => 5]],
            'description' => 'Du trägst Liturgie durch Ruinen und Hungerzeiten. Dein Trost heilt Herzen, doch dein Glaube wird ständig geprüft.',
        ],
        'wissenschaftler' => [
            'label' => 'Wissenschaftler',
            'minimums' => ['kl' => 45, 'in' => 35],
            'bonuses' => ['attributes' => ['kl' => 5, 'in' => 5]],
            'description' => 'Du sezierst Wahrheit mit kalter Präzision. Im Schatten der Blutpforten wird Wissen schnell zur Sünde.',
        ],
        'dieb' => [
            'label' => 'Dieb',
            'minimums' => ['ff' => 45, 'ge' => 40, 'in' => 35],
            'bonuses' => ['attributes' => ['ff' => 5, 'ge' => 5]],
            'description' => 'Du nimmst, was gut bewacht ist, und verschwindest vor dem Echo der Schritte. In dunklen Städten ist dein Ruf mehr wert als Gold.',
        ],
        'krieger' => [
            'label' => 'Krieger',
            'minimums' => ['kk' => 45, 'ko' => 40, 'mu' => 40],
            'bonuses' => ['le_flat' => 5, 'attributes' => ['kk' => 5]],
            'description' => 'Du entscheidest Konflikte dort, wo Worte längst verbrannt sind. Narben sind bei dir kein Makel, sondern Chronik.',
        ],
        'heiler' => [
            'label' => 'Heiler',
            'minimums' => ['kl' => 40, 'in' => 40, 'ch' => 35],
            'bonuses' => ['attributes' => ['in' => 5], 'ae_flat' => 5],
            'description' => 'Du kennst den Preis von Blut und Fieber besser als jeder Feldherr. Deine Hände retten Leben, die Welt schuldet dir nichts.',
        ],
        'barde' => [
            'label' => 'Barde',
            'minimums' => ['ch' => 45, 'in' => 40, 'ff' => 35],
            'bonuses' => ['attributes' => ['ch' => 5, 'in' => 5]],
            'description' => 'Du lenkst Hallen mit Stimme, Vers und Lüge. Deine Lieder tragen Wahrheit, wenn niemand sie direkt hören will.',
        ],
        'jaeger' => [
            'label' => 'Jäger',
            'minimums' => ['in' => 40, 'ge' => 40, 'ko' => 35],
            'bonuses' => ['attributes' => ['in' => 5, 'ge' => 5]],
            'description' => 'Du liest Spuren in Nebel, Schlamm und Aschewind. Wo andere gejagt werden, bist du bereits auf der Fährte.',
        ],
        'schmied' => [
            'label' => 'Schmied',
            'minimums' => ['kk' => 45, 'ff' => 40, 'ko' => 40],
            'bonuses' => ['attributes' => ['ko' => 5, 'kk' => 5]],
            'description' => 'Du formst aus Erz und Feuer mehr als nur Stahl. Jede Klinge trägt Erinnerung, jede Rüstung ein Versprechen.',
        ],
        'gelehrter' => [
            'label' => 'Gelehrter',
            'minimums' => ['kl' => 50, 'in' => 40],
            'bonuses' => ['attributes' => ['kl' => 5, 'ch' => 5]],
            'description' => 'Du sammelst Fragmente gefallener Zeitalter und setzt sie zu Sinn zusammen. Manche Wahrheiten machen dich wertvoll, andere gefährlich.',
        ],
        'eigene' => [
            'label' => 'Eigene',
            'minimums' => [],
            'bonuses' => ['attributes' => []],
            'description' => 'Frei definierte Berufung, die mit dem GM abgestimmt wird.',
            'custom' => true,
        ],
    ],

    // Nur diese Spezies/Berufungen besitzen von Beginn an Astralzugang.
    'magic_capable_species' => ['elf'],
    'magic_capable_callings' => ['magier', 'geistlicher', 'heiler'],

    'traits' => [
        'min' => 1,
        'max' => 3,
    ],

    // Uebergangs-Mapping auf das alte 6-Werte-Schema fuer bestehende DB/UI.
    'legacy_column_map' => [
        'strength' => 'kk',
        'dexterity' => 'ge',
        'constitution' => 'ko',
        'intelligence' => 'kl',
        'wisdom' => 'in',
        'charisma' => 'ch',
    ],
];
