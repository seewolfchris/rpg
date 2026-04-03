<?php

use App\Http\Controllers\LegacyAuthenticatedRedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/campaigns', [LegacyAuthenticatedRedirectController::class, 'campaignsIndex']);

Route::get('/campaigns/create', [LegacyAuthenticatedRedirectController::class, 'campaignsCreate']);

Route::get('/campaigns/{campaign}', [LegacyAuthenticatedRedirectController::class, 'campaignsShow']);

Route::get('/campaigns/{campaign}/edit', [LegacyAuthenticatedRedirectController::class, 'campaignsEdit']);

Route::get('/campaigns/{campaign}/scenes/create', [LegacyAuthenticatedRedirectController::class, 'campaignScenesCreate']);

Route::get('/campaigns/{campaign}/scenes/{scene}', [LegacyAuthenticatedRedirectController::class, 'campaignScenesShow']);

Route::get('/campaigns/{campaign}/scenes/{scene}/edit', [LegacyAuthenticatedRedirectController::class, 'campaignScenesEdit']);

Route::get('/scene-subscriptions', [LegacyAuthenticatedRedirectController::class, 'sceneSubscriptionsIndex']);

Route::get('/bookmarks', [LegacyAuthenticatedRedirectController::class, 'bookmarksIndex']);

Route::get('/posts/{post}/edit', [LegacyAuthenticatedRedirectController::class, 'postsEdit']);

Route::get('/gm/moderation', [LegacyAuthenticatedRedirectController::class, 'gmModerationIndex']);
