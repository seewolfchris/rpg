<?php

use App\Http\Controllers\Api\WebPushSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::post('/api/webpush/subscribe', [WebPushSubscriptionController::class, 'subscribe'])
    ->middleware('throttle:webpush-subscriptions')
    ->name('api.webpush.subscribe');

Route::post('/api/webpush/unsubscribe', [WebPushSubscriptionController::class, 'unsubscribe'])
    ->middleware('throttle:webpush-subscriptions')
    ->name('api.webpush.unsubscribe');
