<?php

use App\Http\Controllers\CampaignController;
use Illuminate\Support\Facades\Route;

Route::resource('campaigns', CampaignController::class)
    ->middlewareFor(['store', 'update', 'destroy'], 'throttle:writes');

require __DIR__.'/campaigns/core.php';
require __DIR__.'/campaigns/handouts.php';
require __DIR__.'/campaigns/story_log.php';
require __DIR__.'/campaigns/player_notes.php';
require __DIR__.'/campaigns/subscriptions.php';
require __DIR__.'/campaigns/bookmarks.php';
