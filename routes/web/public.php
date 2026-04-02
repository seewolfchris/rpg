<?php

use App\Http\Controllers\KnowledgeController;
use App\Http\Controllers\WorldController;
use App\Models\World;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::get('/', function () {
    $worlds = collect();

    if (Schema::hasTable('worlds')) {
        $worlds = World::query()
            ->active()
            ->ordered()
            ->withCount('campaigns')
            ->get();
    }

    return view('welcome', compact('worlds'));
})->name('home');

Route::get('/welten', [WorldController::class, 'index'])
    ->name('worlds.index');

Route::post('/welten/{world:slug}/aktivieren', [WorldController::class, 'activate'])
    ->middleware('throttle:writes')
    ->name('worlds.activate');

Route::get('/wissen', [KnowledgeController::class, 'index'])
    ->name('knowledge.global.index');

Route::get('/wissen/wie-spielt-man', [KnowledgeController::class, 'howToPlay'])
    ->name('knowledge.global.how-to-play');

Route::get('/wissen/regelwerk', [KnowledgeController::class, 'rules'])
    ->name('knowledge.global.rules');

Route::get('/wissen/enzyklopaedie', [KnowledgeController::class, 'encyclopedia'])
    ->name('knowledge.global.encyclopedia');

Route::get('/wissen/enzyklopaedie/{categorySlug}/{entrySlug}', function (
    Request $request,
    string $categorySlug,
    string $entrySlug
) {
    $sessionSlug = $request->session()->get('world_slug');

    if (is_string($sessionSlug) && $sessionSlug !== '') {
        return redirect()->route('knowledge.encyclopedia.entry', [
            'world' => $sessionSlug,
            'categorySlug' => $categorySlug,
            'entrySlug' => $entrySlug,
        ], 302);
    }

    return redirect()->route('knowledge.global.encyclopedia', [], 302);
})->where([
    'categorySlug' => '(?!admin$)[a-z0-9\\-]+',
    'entrySlug' => '[a-z0-9\\-]+',
]);

Route::get('/hilfe', function () {
    return redirect()->route('knowledge.global.index', [], 302);
})->name('help.index');
