<?php

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
    'spike_log_candidates' => \App\Support\ConfigEnv::boolean(env('OUTBOX_SPIKE_LOG_CANDIDATES', false), false),

    /*
    |--------------------------------------------------------------------------
    | Guardrail fuer Log-Payload-Groesse
    |--------------------------------------------------------------------------
    */
    'max_payload_bytes' => (int) env('OUTBOX_MAX_PAYLOAD_BYTES', 4096),
];
