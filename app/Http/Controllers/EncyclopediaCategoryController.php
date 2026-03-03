<?php

namespace App\Http\Controllers;

use App\Http\Requests\Encyclopedia\StoreEncyclopediaCategoryRequest;
use App\Http\Requests\Encyclopedia\UpdateEncyclopediaCategoryRequest;
use App\Models\EncyclopediaCategory;
use App\Models\EncyclopediaEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EncyclopediaCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $categories = EncyclopediaCategory::query()
            ->withCount('entries')
            ->withCount([
                'entries as published_entries_count' => fn ($query) => $query
                    ->where('status', EncyclopediaEntry::STATUS_PUBLISHED),
            ])
            ->orderBy('position')
            ->orderBy('name')
            ->paginate(20);

        return view('knowledge.admin.categories.index', compact('categories'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $category = new EncyclopediaCategory();

        return view('knowledge.admin.categories.create', compact('category'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEncyclopediaCategoryRequest $request): RedirectResponse
    {
        EncyclopediaCategory::query()->create($request->validated());

        return redirect()
            ->route('knowledge.admin.kategorien.index')
            ->with('status', 'Kategorie erstellt.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EncyclopediaCategory $encyclopediaCategory): View
    {
        $category = $encyclopediaCategory;
        $entries = $category->entries()
            ->orderBy('position')
            ->orderBy('title')
            ->get();

        return view('knowledge.admin.categories.edit', compact('category', 'entries'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEncyclopediaCategoryRequest $request, EncyclopediaCategory $encyclopediaCategory): RedirectResponse
    {
        $encyclopediaCategory->update($request->validated());

        return redirect()
            ->route('knowledge.admin.kategorien.edit', $encyclopediaCategory)
            ->with('status', 'Kategorie aktualisiert.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EncyclopediaCategory $encyclopediaCategory): RedirectResponse
    {
        $encyclopediaCategory->delete();

        return redirect()
            ->route('knowledge.admin.kategorien.index')
            ->with('status', 'Kategorie geloescht.');
    }
}
