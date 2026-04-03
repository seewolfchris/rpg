<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CampaignInvitationController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\CharacterProgressionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaderboardController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', DashboardController::class)
    ->name('dashboard');

Route::get('/leaderboard', [LeaderboardController::class, 'index'])
    ->name('leaderboard.index');

Route::resource('characters', CharacterController::class)
    ->middlewareFor(['store', 'update', 'destroy'], 'throttle:writes');

Route::post('/characters/{character}/progression/spend', [CharacterProgressionController::class, 'spend'])
    ->middleware('throttle:writes')
    ->name('characters.progression.spend');

Route::patch('/characters/{character}/inline', [CharacterController::class, 'inlineUpdate'])
    ->middleware('throttle:writes')
    ->name('characters.inline-update');

Route::get('/campaign-invitations', [CampaignInvitationController::class, 'index'])
    ->name('campaign-invitations.index');

Route::patch('/campaign-invitations/{invitation}/accept', [CampaignInvitationController::class, 'acceptLegacy'])
    ->middleware('throttle:writes')
    ->name('campaign-invitations.accept.legacy');

Route::patch('/campaign-invitations/{invitation}/decline', [CampaignInvitationController::class, 'declineLegacy'])
    ->middleware('throttle:writes')
    ->name('campaign-invitations.decline.legacy');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('throttle:writes')
    ->name('logout');
