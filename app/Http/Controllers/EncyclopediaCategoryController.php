<?php

namespace App\Http\Controllers;

use App\Actions\Encyclopedia\CreateEncyclopediaCategoryAction;
use App\Actions\Encyclopedia\DeleteEncyclopediaCategoryAction;
use App\Actions\Encyclopedia\UpdateEncyclopediaCategoryAction;
use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\Encyclopedia\StoreEncyclopediaCategoryRequest;
use App\Http\Requests\Encyclopedia\UpdateEncyclopediaCategoryRequest;
use App\Models\EncyclopediaCategory;
use App\Models\EncyclopediaEntry;
use App\Models\World;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EncyclopediaCategoryController extends Controller
{
    use EnsuresWorldContext;

    public function __construct(
        private readonly CreateEncyclopediaCategoryAction $createEncyclopediaCategoryAction,
        private readonly UpdateEncyclopediaCategoryAction $updateEncyclopediaCategoryAction,
        private readonly DeleteEncyclopediaCategoryAction $deleteEncyclopediaCategoryAction,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(World $world): View
    {
        $categories = EncyclopediaCategory::query()
            ->forWorld($world)
            ->withCount('entries')
            ->withCount([
                'entries as published_entries_count' => fn ($query) => $query
                    ->where('status', EncyclopediaEntry::STATUS_PUBLISHED),
            ])
            ->orderBy('position')
            ->orderBy('name')
            ->paginate(20);

        return view('knowledge.admin.categories.index', compact('world', 'categories'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(World $world): View
    {
        $category = new EncyclopediaCategory;

        return view('knowledge.admin.categories.create', compact('world', 'category'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEncyclopediaCategoryRequest $request, World $world): RedirectResponse
    {
        $this->createEncyclopediaCategoryAction->execute(
            world: $world,
            data: $request->validated(),
        );

        return redirect()
            ->route('knowledge.admin.kategorien.index', ['world' => $world])
            ->with('status', 'Kategorie erstellt.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(World $world, EncyclopediaCategory $encyclopediaCategory): View
    {
        $this->ensureCategoryBelongsToWorld($world, $encyclopediaCategory);

        $category = $encyclopediaCategory;
        $entries = $category->entries()
            ->orderBy('position')
            ->orderBy('title')
            ->get();

        return view('knowledge.admin.categories.edit', compact('world', 'category', 'entries'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdateEncyclopediaCategoryRequest $request,
        World $world,
        EncyclopediaCategory $encyclopediaCategory
    ): RedirectResponse {
        $this->updateEncyclopediaCategoryAction->execute(
            world: $world,
            category: $encyclopediaCategory,
            data: $request->validated(),
        );

        return redirect()
            ->route('knowledge.admin.kategorien.edit', [
                'world' => $world,
                'encyclopediaCategory' => $encyclopediaCategory,
            ])
            ->with('status', 'Kategorie aktualisiert.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(World $world, EncyclopediaCategory $encyclopediaCategory): RedirectResponse
    {
        $this->deleteEncyclopediaCategoryAction->execute(
            world: $world,
            category: $encyclopediaCategory,
        );

        return redirect()
            ->route('knowledge.admin.kategorien.index', ['world' => $world])
            ->with('status', 'Kategorie gelöscht.');
    }
}
