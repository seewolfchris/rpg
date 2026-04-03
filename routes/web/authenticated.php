<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth')->scopeBindings()->group(function (): void {
    require __DIR__.'/auth/core.php';
    require __DIR__.'/auth/notifications.php';
    require __DIR__.'/auth/webpush.php';
    require __DIR__.'/auth/gm.php';
    require __DIR__.'/auth/admin.php';
    require __DIR__.'/auth/legacy_redirects.php';
});
