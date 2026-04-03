<?php

use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/notifications/preferences', [NotificationController::class, 'preferences'])
    ->name('notifications.preferences');

Route::patch('/notifications/preferences', [NotificationController::class, 'updatePreferences'])
    ->middleware('throttle:notifications')
    ->name('notifications.preferences.update');

Route::get('/notifications', [NotificationController::class, 'index'])
    ->name('notifications.index');

Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])
    ->middleware('throttle:notifications')
    ->name('notifications.read-all');

Route::post('/notifications/{notificationId}/read', [NotificationController::class, 'read'])
    ->middleware('throttle:notifications')
    ->name('notifications.read');
