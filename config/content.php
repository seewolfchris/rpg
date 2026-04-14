<?php

return [
    // Read-only Vorschau fuer weltgebundene Markdown-Inhalte im Wissenszentrum.
    'world_markdown_preview' => \App\Support\ConfigEnv::boolean(env('WORLD_MARKDOWN_PREVIEW', false), false),
];
