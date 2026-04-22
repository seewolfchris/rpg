<?php

use App\Http\Controllers\EncyclopediaCategoryController;
use App\Http\Controllers\EncyclopediaEntryController;
use Illuminate\Support\Facades\Route;

Route::prefix('/wissen/enzyklopaedie/admin')
    ->as('knowledge.admin.')
    ->middleware('role:gm,admin')
    ->group(function (): void {
        Route::resource('kategorien', EncyclopediaCategoryController::class)
            ->parameters(['kategorien' => 'encyclopediaCategory'])
            ->except(['show'])
            ->middlewareFor(['store', 'update', 'destroy'], 'throttle:moderation');

        Route::resource('kategorien.eintraege', EncyclopediaEntryController::class)
            ->parameters([
                'kategorien' => 'encyclopediaCategory',
                'eintraege' => 'encyclopediaEntry',
            ])
            ->except(['show', 'index'])
            ->middlewareFor(['store', 'update', 'destroy'], 'throttle:moderation');
    });
