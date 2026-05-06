<?php

use App\Http\Controllers\PostController;
use App\Http\Controllers\PostReactionController;
use App\Http\Controllers\SceneCombatActionController;
use App\Http\Controllers\SceneController;
use Illuminate\Support\Facades\Route;

Route::post('/campaigns/{campaign}/scenes/{scene}/posts', [PostController::class, 'store'])
    ->middleware('throttle:writes')
    ->name('campaigns.scenes.posts.store');

Route::post('/posts/preview', [PostController::class, 'preview'])
    ->middleware('throttle:writes')
    ->name('posts.preview');

Route::post('/campaigns/{campaign}/scenes/{scene}/inventory-quick-action', [SceneController::class, 'inventoryQuickAction'])
    ->middleware('throttle:writes')
    ->name('campaigns.scenes.inventory-quick-action');

Route::post('/campaigns/{campaign}/scenes/{scene}/combat/actions', [SceneCombatActionController::class, 'store'])
    ->middleware('throttle:writes')
    ->name('campaigns.scenes.combat.actions.store');

Route::get('/posts/{post}/edit', [PostController::class, 'edit'])
    ->withoutScopedBindings()
    ->name('posts.edit');

Route::patch('/posts/{post}', [PostController::class, 'update'])
    ->withoutScopedBindings()
    ->middleware('throttle:writes')
    ->name('posts.update');

Route::delete('/posts/{post}', [PostController::class, 'destroy'])
    ->withoutScopedBindings()
    ->middleware('throttle:writes')
    ->name('posts.destroy');

Route::patch('/posts/{post}/moderate', [PostController::class, 'moderate'])
    ->withoutScopedBindings()
    ->middleware('throttle:moderation')
    ->name('posts.moderate');

Route::patch('/posts/{post}/pin', [PostController::class, 'pin'])
    ->withoutScopedBindings()
    ->middleware('throttle:moderation')
    ->name('posts.pin');

Route::patch('/posts/{post}/unpin', [PostController::class, 'unpin'])
    ->withoutScopedBindings()
    ->middleware('throttle:moderation')
    ->name('posts.unpin');

Route::post('/posts/{post}/reactions', [PostReactionController::class, 'store'])
    ->withoutScopedBindings()
    ->middleware('throttle:writes')
    ->name('posts.reactions.store');

Route::delete('/posts/{post}/reactions', [PostReactionController::class, 'destroy'])
    ->withoutScopedBindings()
    ->middleware('throttle:writes')
    ->name('posts.reactions.destroy');
