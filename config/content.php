<?php

$envBool = require __DIR__.'/_env_bool.php';

return [
    // Read-only Vorschau fuer weltgebundene Markdown-Inhalte im Wissenszentrum.
    'world_markdown_preview' => $envBool('WORLD_MARKDOWN_PREVIEW', false),
];
