<?php

namespace App\Http\Controllers;

use App\Actions\Encyclopedia\CreateEncyclopediaEntryAction;
use App\Actions\Encyclopedia\DeleteEncyclopediaEntryAction;
use App\Actions\Encyclopedia\UpdateEncyclopediaEntryAction;
use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\Encyclopedia\StoreEncyclopediaEntryRequest;
use App\Http\Requests\Encyclopedia\UpdateEncyclopediaEntryRequest;
use App\Models\EncyclopediaCategory;
use App\Models\EncyclopediaEntry;
use App\Models\World;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EncyclopediaEntryController extends Controller
{
    use EnsuresWorldContext;

    public function __construct(
        private readonly CreateEncyclopediaEntryAction $createEncyclopediaEntryAction,
        private readonly UpdateEncyclopediaEntryAction $updateEncyclopediaEntryAction,
        private readonly DeleteEncyclopediaEntryAction $deleteEncyclopediaEntryAction,
    ) {}

    /**
     * Show the form for creating a new resource.
     */
    public function create(World $world, EncyclopediaCategory $encyclopediaCategory): View
    {
        $this->ensureCategoryBelongsToWorld($world, $encyclopediaCategory);

        $category = $encyclopediaCategory;
        $entry = new EncyclopediaEntry;

        return view('knowledge.admin.entries.create', compact('world', 'category', 'entry'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(
        StoreEncyclopediaEntryRequest $request,
        World $world,
        EncyclopediaCategory $encyclopediaCategory
    ): RedirectResponse {
        $this->createEncyclopediaEntryAction->execute(
            world: $world,
            category: $encyclopediaCategory,
            actor: $this->authenticatedUser($request),
            data: $request->validated(),
        );

        return redirect()
            ->route('knowledge.admin.kategorien.edit', [
                'world' => $world,
                'encyclopediaCategory' => $encyclopediaCategory,
            ])
            ->with('status', 'Eintrag erstellt.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(
        World $world,
        EncyclopediaCategory $encyclopediaCategory,
        EncyclopediaEntry $encyclopediaEntry
    ): View {
        $this->ensureCategoryBelongsToWorld($world, $encyclopediaCategory);
        $this->abortIfCategoryMismatch($encyclopediaCategory, $encyclopediaEntry);

        $category = $encyclopediaCategory;
        $entry = $encyclopediaEntry;

        return view('knowledge.admin.entries.edit', compact('world', 'category', 'entry'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdateEncyclopediaEntryRequest $request,
        World $world,
        EncyclopediaCategory $encyclopediaCategory,
        EncyclopediaEntry $encyclopediaEntry
    ): RedirectResponse {
        $this->updateEncyclopediaEntryAction->execute(
            world: $world,
            category: $encyclopediaCategory,
            entry: $encyclopediaEntry,
            actor: $this->authenticatedUser($request),
            data: $request->validated(),
        );

        return redirect()
            ->route('knowledge.admin.kategorien.edit', [
                'world' => $world,
                'encyclopediaCategory' => $encyclopediaCategory,
            ])
            ->with('status', 'Eintrag aktualisiert.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(
        World $world,
        EncyclopediaCategory $encyclopediaCategory,
        EncyclopediaEntry $encyclopediaEntry
    ): RedirectResponse {
        $this->deleteEncyclopediaEntryAction->execute(
            world: $world,
            category: $encyclopediaCategory,
            entry: $encyclopediaEntry,
        );

        return redirect()
            ->route('knowledge.admin.kategorien.edit', [
                'world' => $world,
                'encyclopediaCategory' => $encyclopediaCategory,
            ])
            ->with('status', 'Eintrag gelöscht.');
    }

    private function abortIfCategoryMismatch(EncyclopediaCategory $category, EncyclopediaEntry $entry): void
    {
        abort_unless($entry->encyclopedia_category_id === $category->id, 404);
    }
}
