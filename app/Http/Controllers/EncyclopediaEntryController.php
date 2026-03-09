<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\Encyclopedia\StoreEncyclopediaEntryRequest;
use App\Http\Requests\Encyclopedia\UpdateEncyclopediaEntryRequest;
use App\Models\EncyclopediaCategory;
use App\Models\EncyclopediaEntry;
use App\Models\World;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class EncyclopediaEntryController extends Controller
{
    use EnsuresWorldContext;

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
        $this->ensureCategoryBelongsToWorld($world, $encyclopediaCategory);

        $data = $request->validated();
        $data['encyclopedia_category_id'] = $encyclopediaCategory->id;
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        if ($data['status'] === EncyclopediaEntry::STATUS_PUBLISHED && empty($data['published_at'])) {
            $data['published_at'] = Carbon::now();
        }

        if ($data['status'] !== EncyclopediaEntry::STATUS_PUBLISHED) {
            $data['published_at'] = null;
        }

        EncyclopediaEntry::query()->create($data);

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
        $this->ensureCategoryBelongsToWorld($world, $encyclopediaCategory);
        $this->abortIfCategoryMismatch($encyclopediaCategory, $encyclopediaEntry);

        $data = $request->validated();
        $data['updated_by'] = auth()->id();

        if ($data['status'] === EncyclopediaEntry::STATUS_PUBLISHED && empty($data['published_at'])) {
            $data['published_at'] = Carbon::now();
        }

        if ($data['status'] !== EncyclopediaEntry::STATUS_PUBLISHED) {
            $data['published_at'] = null;
        }

        $encyclopediaEntry->update($data);

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
        $this->ensureCategoryBelongsToWorld($world, $encyclopediaCategory);
        $this->abortIfCategoryMismatch($encyclopediaCategory, $encyclopediaEntry);

        $encyclopediaEntry->delete();

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
