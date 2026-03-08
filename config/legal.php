<?php

return [
    'source' => [
        'imprint_url' => env('LEGAL_SOURCE_IMPRINT_URL', 'https://c76.org/impressum/'),
        'privacy_url' => env('LEGAL_SOURCE_PRIVACY_URL', 'https://c76.org/datenschutz/'),
    ],

    'imprint' => [
        'scope_note' => env('LEGAL_SCOPE_NOTE', 'Dieses Impressum gilt für c76.org und zugehörige Subdomains, inklusive rpg.c76.org.'),
        'responsible_name' => env('LEGAL_RESPONSIBLE_NAME', 'Christoph Sieber'),
        'responsible_address' => env('LEGAL_RESPONSIBLE_ADDRESS', "Bachstraße 3\n27570 Bremerhaven\nDeutschland"),
        'contact_email' => env('LEGAL_CONTACT_EMAIL', 'admin@c76.org'),
        'contact_phone' => env('LEGAL_CONTACT_PHONE', 'auf Anfrage per E-Mail'),
        'content_responsible' => env('LEGAL_CONTENT_RESPONSIBLE', 'Christoph Sieber, Bachstraße 3, 27570 Bremerhaven'),
    ],

    'privacy' => [
        'controller_name' => env('LEGAL_CONTROLLER_NAME', 'Christoph Sieber, Kapitän zur See'),
        'controller_address' => env('LEGAL_CONTROLLER_ADDRESS', "Bachstraße 3\n27570 Bremerhaven\nDeutschland"),
        'controller_email' => env('LEGAL_CONTROLLER_EMAIL', 'admin@c76.org'),
        'rights_contact' => env('LEGAL_RIGHTS_CONTACT', 'admin@c76.org'),
        'rights_contact_note' => env('LEGAL_RIGHTS_CONTACT_NOTE', 'Datenschutzanfragen bitte mit Betreff und kurzer Sachverhaltsbeschreibung senden.'),
        'retention_periods' => [
            [
                'topic' => 'Server- und Sicherheitslogs',
                'period' => 'Grundsätzlich bis zu 7 Tage; bei Sicherheitsvorfällen bis zur abgeschlossenen Aufklärung und Beweissicherung.',
            ],
            [
                'topic' => 'Spielinhalte (Posts, Moderationsvermerke, Revisionsdaten)',
                'period' => 'Bis zur Löschung durch Berechtigte oder bis zur Entfernung nach Regelverstoß/Policy-Entscheidung.',
            ],
            [
                'topic' => 'E-Mail-Kommunikation',
                'period' => 'Bis zur Erledigung des Anliegens; darüber hinaus nur bei gesetzlicher Pflicht oder zur Rechtsdurchsetzung.',
            ],
            [
                'topic' => 'Benachrichtigungsdaten',
                'period' => 'Bis zur Löschung durch Nutzer oder bis zur technischen Bereinigung nach interner Betriebsrichtlinie.',
            ],
        ],
        'processors' => [
            [
                'name' => 'STRATO AG',
                'purpose' => 'Webhosting und Serverbetrieb.',
                'location' => 'Deutschland (Auftragsverarbeitung nach Art. 28 DSGVO).',
            ],
            [
                'name' => 'Eigene Mail-Infrastruktur (c76.org)',
                'purpose' => 'Betriebliche E-Mail-Kommunikation und Systemzustellung.',
                'location' => 'Deutschland (eigener Betrieb/Verantwortlicher).',
            ],
        ],
    ],

    'copyright' => [
        'rights_contact' => env('LEGAL_RIGHTS_CONTACT', 'admin@c76.org'),
        'rights_contact_note' => env('LEGAL_RIGHTS_CONTACT_NOTE', 'Bitte in der Meldung URL, betroffenen Inhalt und Nachweis der Rechte angeben.'),
    ],
];
