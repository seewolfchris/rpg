<?php

use Illuminate\Support\Facades\Route;

Route::prefix('/w/{world:slug}')->scopeBindings()->group(function (): void {
    require __DIR__.'/world/knowledge.php';

    Route::middleware('auth')->scopeBindings()->group(function (): void {
        require __DIR__.'/world/campaigns.php';
        require __DIR__.'/world/posts.php';
        require __DIR__.'/world/encyclopedia.php';
        require __DIR__.'/world/gm.php';
        require __DIR__.'/world/encyclopedia_admin.php';
    });
});
