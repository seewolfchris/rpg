<?php

$envBool = require __DIR__.'/_env_bool.php';

return [
    /*
    |--------------------------------------------------------------------------
    | Outbox Spike
    |--------------------------------------------------------------------------
    |
    | v0.30-Spike: noch kein persistentes Outbox-Schema.
    | Wenn aktiv, werden Outbox-Kandidaten als strukturierte Events geloggt,
    | um reale Last-/Fehlerdaten fuer eine spaetere ADR-Entscheidung zu sammeln.
    |
    */
    'spike_log_candidates' => $envBool('OUTBOX_SPIKE_LOG_CANDIDATES', false),

    /*
    |--------------------------------------------------------------------------
    | Guardrail fuer Log-Payload-Groesse
    |--------------------------------------------------------------------------
    */
    'max_payload_bytes' => (int) env('OUTBOX_MAX_PAYLOAD_BYTES', 4096),
];
