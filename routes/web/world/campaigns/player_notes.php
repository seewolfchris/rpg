<?php

use App\Http\Controllers\PlayerNoteController;
use Illuminate\Support\Facades\Route;

Route::resource('campaigns.player-notes', PlayerNoteController::class)
    ->middlewareFor(['store', 'update', 'destroy'], 'throttle:writes')
    ->parameters(['player-notes' => 'playerNote']);
