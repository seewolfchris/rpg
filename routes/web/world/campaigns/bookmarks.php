<?php

use App\Http\Controllers\SceneBookmarkController;
use Illuminate\Support\Facades\Route;

Route::get('/bookmarks', [SceneBookmarkController::class, 'index'])
    ->name('bookmarks.index');

Route::post('/campaigns/{campaign}/scenes/{scene}/bookmark', [SceneBookmarkController::class, 'store'])
    ->middleware('throttle:writes')
    ->name('campaigns.scenes.bookmark.store');

Route::delete('/campaigns/{campaign}/scenes/{scene}/bookmark', [SceneBookmarkController::class, 'destroy'])
    ->middleware('throttle:writes')
    ->name('campaigns.scenes.bookmark.destroy');
