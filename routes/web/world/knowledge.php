<?php

use App\Http\Controllers\KnowledgeEncyclopediaController;
use App\Http\Controllers\KnowledgePageController;
use App\Http\Controllers\WorldController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WorldController::class, 'show'])
    ->name('worlds.show');

Route::get('/wissen', [KnowledgePageController::class, 'index'])
    ->name('knowledge.index');

Route::get('/wissen/wie-spielt-man', [KnowledgePageController::class, 'howToPlay'])
    ->name('knowledge.how-to-play');

Route::get('/wissen/regelwerk', [KnowledgePageController::class, 'rules'])
    ->name('knowledge.rules');

Route::get('/wissen/weltueberblick', [KnowledgePageController::class, 'worldOverview'])
    ->name('knowledge.world-overview');

Route::get('/wissen/lore/{category?}', [KnowledgePageController::class, 'worldLore'])
    ->where('category', '[a-z0-9\\-]+')
    ->name('knowledge.lore');

Route::get('/wissen/enzyklopaedie', [KnowledgeEncyclopediaController::class, 'encyclopedia'])
    ->name('knowledge.encyclopedia');

Route::get('/wissen/enzyklopaedie/{categorySlug}/{entrySlug}', [KnowledgeEncyclopediaController::class, 'encyclopediaEntry'])
    ->where([
        'categorySlug' => '(?!admin(?:/|$))[a-z0-9\\-]+',
        'entrySlug' => '[a-z0-9\\-]+',
    ])
    ->name('knowledge.encyclopedia.entry');
