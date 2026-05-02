<?php

use App\Http\Controllers\StoryLogEntryController;
use Illuminate\Support\Facades\Route;

Route::resource('campaigns.story-log', StoryLogEntryController::class)
    ->middlewareFor(['store', 'update', 'destroy'], 'throttle:writes')
    ->parameters(['story-log' => 'storyLogEntry']);

Route::patch('/campaigns/{campaign}/story-log/{storyLogEntry}/reveal', [StoryLogEntryController::class, 'reveal'])
    ->middleware('throttle:writes')
    ->name('campaigns.story-log.reveal');

Route::patch('/campaigns/{campaign}/story-log/{storyLogEntry}/unreveal', [StoryLogEntryController::class, 'unreveal'])
    ->middleware('throttle:writes')
    ->name('campaigns.story-log.unreveal');
