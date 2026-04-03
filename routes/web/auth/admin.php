<?php

use App\Http\Controllers\AdminUserModerationController;
use App\Http\Controllers\WorldAdminController;
use App\Http\Controllers\WorldCallingOptionAdminController;
use App\Http\Controllers\WorldCharacterOptionTemplateAdminController;
use App\Http\Controllers\WorldSpeciesOptionAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('/admin')
    ->middleware('role:admin')
    ->group(function (): void {
        Route::get('users/moderation', [AdminUserModerationController::class, 'index'])
            ->name('admin.users.moderation.index');

        Route::patch('users/{user}/moderation', [AdminUserModerationController::class, 'update'])
            ->name('admin.users.moderation.update')
            ->middleware('throttle:moderation');

        Route::patch('worlds/{world}/toggle-active', [WorldAdminController::class, 'toggleActive'])
            ->name('admin.worlds.toggle-active')
            ->middleware('throttle:moderation');

        Route::patch('worlds/{world}/move/{direction}', [WorldAdminController::class, 'move'])
            ->where('direction', 'up|down')
            ->name('admin.worlds.move')
            ->middleware('throttle:moderation');

        Route::post('worlds/{world}/character-options/import-template', [WorldCharacterOptionTemplateAdminController::class, 'importTemplate'])
            ->name('admin.worlds.character-options.import-template')
            ->middleware('throttle:moderation');

        Route::post('worlds/{world}/species-options', [WorldSpeciesOptionAdminController::class, 'store'])
            ->name('admin.worlds.species-options.store')
            ->middleware('throttle:moderation');

        Route::patch('worlds/{world}/species-options/{speciesOption}', [WorldSpeciesOptionAdminController::class, 'update'])
            ->name('admin.worlds.species-options.update')
            ->middleware('throttle:moderation');

        Route::patch('worlds/{world}/species-options/{speciesOption}/toggle', [WorldSpeciesOptionAdminController::class, 'toggle'])
            ->name('admin.worlds.species-options.toggle')
            ->middleware('throttle:moderation');

        Route::patch('worlds/{world}/species-options/{speciesOption}/move/{direction}', [WorldSpeciesOptionAdminController::class, 'move'])
            ->where('direction', 'up|down')
            ->name('admin.worlds.species-options.move')
            ->middleware('throttle:moderation');

        Route::post('worlds/{world}/calling-options', [WorldCallingOptionAdminController::class, 'store'])
            ->name('admin.worlds.calling-options.store')
            ->middleware('throttle:moderation');

        Route::patch('worlds/{world}/calling-options/{callingOption}', [WorldCallingOptionAdminController::class, 'update'])
            ->name('admin.worlds.calling-options.update')
            ->middleware('throttle:moderation');

        Route::patch('worlds/{world}/calling-options/{callingOption}/toggle', [WorldCallingOptionAdminController::class, 'toggle'])
            ->name('admin.worlds.calling-options.toggle')
            ->middleware('throttle:moderation');

        Route::patch('worlds/{world}/calling-options/{callingOption}/move/{direction}', [WorldCallingOptionAdminController::class, 'move'])
            ->where('direction', 'up|down')
            ->name('admin.worlds.calling-options.move')
            ->middleware('throttle:moderation');

        Route::resource('worlds', WorldAdminController::class)
            ->except(['show'])
            ->names('admin.worlds')
            ->middlewareFor(['store', 'update', 'destroy'], 'throttle:moderation');
    });
