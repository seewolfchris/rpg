<?php

use App\Http\Controllers\GmIndexController;
use Illuminate\Support\Facades\Route;

Route::get('/gm', GmIndexController::class)
    ->name('gm.index');
