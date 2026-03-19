<?php

return [
    'posts_latest_by_id' => [
        'force_index_enabled' => env('PERF_POSTS_LATEST_BY_ID_FORCE_INDEX', false),
        'force_index_name' => env('PERF_POSTS_LATEST_BY_ID_FORCE_INDEX_NAME', 'posts_scene_id_id_idx'),
    ],
];
