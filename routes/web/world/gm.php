<?php

use App\Http\Controllers\GmModerationController;
use App\Http\Controllers\GmProgressionController;
use Illuminate\Support\Facades\Route;

Route::get('/gm/moderation', [GmModerationController::class, 'index'])
    ->name('gm.moderation.index');

Route::patch('/gm/moderation/bulk', [GmModerationController::class, 'bulkUpdate'])
    ->middleware('throttle:moderation')
    ->name('gm.moderation.bulk-update');

Route::post('/gm/moderation/{post}/probe', [GmModerationController::class, 'probe'])
    ->withoutScopedBindings()
    ->middleware('throttle:moderation')
    ->name('gm.moderation.probe');

Route::get('/gm/progression', [GmProgressionController::class, 'index'])
    ->name('gm.progression.index');

Route::post('/gm/progression/xp', [GmProgressionController::class, 'awardXp'])
    ->middleware('throttle:moderation')
    ->name('gm.progression.award-xp');
