<?php

use App\Http\Controllers\HandoutController;
use Illuminate\Support\Facades\Route;

Route::resource('campaigns.handouts', HandoutController::class)
    ->middlewareFor(['store', 'update', 'destroy'], 'throttle:writes')
    ->parameters(['handouts' => 'handout']);

Route::patch('/campaigns/{campaign}/handouts/{handout}/reveal', [HandoutController::class, 'reveal'])
    ->middleware('throttle:writes')
    ->name('campaigns.handouts.reveal');

Route::patch('/campaigns/{campaign}/handouts/{handout}/unreveal', [HandoutController::class, 'unreveal'])
    ->middleware('throttle:writes')
    ->name('campaigns.handouts.unreveal');

Route::get('/campaigns/{campaign}/handouts/{handout}/file', [HandoutController::class, 'file'])
    ->name('campaigns.handouts.file');
