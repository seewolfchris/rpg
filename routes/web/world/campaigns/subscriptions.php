<?php

use App\Http\Controllers\SceneSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/scene-subscriptions', [SceneSubscriptionController::class, 'index'])
    ->name('scene-subscriptions.index');

Route::patch('/scene-subscriptions/bulk', [SceneSubscriptionController::class, 'bulkUpdate'])
    ->middleware('throttle:writes')
    ->name('scene-subscriptions.bulk-update');

Route::post('/campaigns/{campaign}/scenes/{scene}/subscribe', [SceneSubscriptionController::class, 'subscribe'])
    ->middleware('throttle:writes')
    ->name('campaigns.scenes.subscribe');

Route::delete('/campaigns/{campaign}/scenes/{scene}/subscribe', [SceneSubscriptionController::class, 'unsubscribe'])
    ->middleware('throttle:writes')
    ->name('campaigns.scenes.unsubscribe');

Route::patch('/campaigns/{campaign}/scenes/{scene}/subscription/mute', [SceneSubscriptionController::class, 'toggleMute'])
    ->middleware('throttle:writes')
    ->name('campaigns.scenes.subscription.mute');

Route::patch('/campaigns/{campaign}/scenes/{scene}/subscription/read', [SceneSubscriptionController::class, 'markRead'])
    ->middleware('throttle:writes')
    ->name('campaigns.scenes.subscription.read');

Route::patch('/campaigns/{campaign}/scenes/{scene}/subscription/unread', [SceneSubscriptionController::class, 'markUnread'])
    ->middleware('throttle:writes')
    ->name('campaigns.scenes.subscription.unread');
