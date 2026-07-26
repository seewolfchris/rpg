<?php

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PushDeviceController;
use Illuminate\Support\Facades\Route;

Route::get('/notifications/preferences', [NotificationController::class, 'preferences'])
    ->name('notifications.preferences');

Route::patch('/notifications/preferences', [NotificationController::class, 'updatePreferences'])
    ->middleware('throttle:notifications')
    ->name('notifications.preferences.update');

Route::delete('/notifications/preferences/push-devices', [PushDeviceController::class, 'destroyAll'])
    ->middleware('throttle:webpush-subscriptions')
    ->name('notifications.preferences.push-devices.destroy-all');

Route::delete('/notifications/preferences/push-devices/{pushSubscription}', [PushDeviceController::class, 'destroy'])
    ->middleware('throttle:webpush-subscriptions')
    ->name('notifications.preferences.push-devices.destroy');

Route::get('/notifications', [NotificationController::class, 'index'])
    ->name('notifications.index');

Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])
    ->middleware('throttle:notifications')
    ->name('notifications.read-all');

Route::post('/notifications/{notificationId}/read', [NotificationController::class, 'read'])
    ->middleware('throttle:notifications')
    ->name('notifications.read');
