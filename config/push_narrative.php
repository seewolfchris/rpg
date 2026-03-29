<?php

return [
    'default' => [
        'scene_new_post' => [
            'title' => 'Neuer Beitrag im Abenteuer',
            'body' => ':author schrieb in ":scene": :excerpt',
            'action_label' => 'Zur Szene',
        ],
        'post_moderation' => [
            'title' => 'Moderation aktualisiert',
            'body' => 'Dein Beitrag in ":scene" steht jetzt auf ":status".',
            'action_label' => 'Beitrag oeffnen',
        ],
        'campaign_invitation' => [
            'title' => 'Neue Kampagneneinladung',
            'body' => ':inviter laedt dich zu ":campaign" ein.',
            'action_label' => 'Einladungen',
        ],
    ],

    'worlds' => [
        'chroniken-der-asche' => [
            'scene_new_post' => [
                'title' => 'Neues Fluestern aus der Asche',
                'body' => ':author setzt in ":scene" den naechsten Satz: :excerpt',
                'action_label' => 'Zur Lesespur',
            ],
            'post_moderation' => [
                'title' => 'Das Archiv der Asche wurde geaendert',
                'body' => 'Dein Beitrag in ":scene" traegt nun den Stand ":status".',
                'action_label' => 'Zum Eintrag',
            ],
            'campaign_invitation' => [
                'title' => 'Ruf aus den Aschelanden',
                'body' => ':inviter ruft dich in die Kampagne ":campaign".',
                'action_label' => 'Ruf annehmen',
            ],
        ],
    ],
];
