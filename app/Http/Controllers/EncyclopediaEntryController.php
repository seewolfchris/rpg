<?php

namespace App\Http\Controllers;

use App\Http\Requests\Encyclopedia\StoreEncyclopediaEntryRequest;
use App\Http\Requests\Encyclopedia\UpdateEncyclopediaEntryRequest;
use App\Models\EncyclopediaCategory;
use App\Models\EncyclopediaEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class EncyclopediaEntryController extends Controller
{
    /**
     * Show the form for creating a new resource.
     */
    public function create(EncyclopediaCategory $encyclopediaCategory): View
    {
        $category = $encyclopediaCategory;
        $entry = new EncyclopediaEntry;

        return view('knowledge.admin.entries.create', compact('category', 'entry'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEncyclopediaEntryRequest $request, EncyclopediaCategory $encyclopediaCategory): RedirectResponse
    {
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
            ->route('knowledge.admin.kategorien.edit', $encyclopediaCategory)
            ->with('status', 'Eintrag erstellt.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EncyclopediaCategory $encyclopediaCategory, EncyclopediaEntry $encyclopediaEntry): View
    {
        $this->abortIfCategoryMismatch($encyclopediaCategory, $encyclopediaEntry);

        $category = $encyclopediaCategory;
        $entry = $encyclopediaEntry;

        return view('knowledge.admin.entries.edit', compact('category', 'entry'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdateEncyclopediaEntryRequest $request,
        EncyclopediaCategory $encyclopediaCategory,
        EncyclopediaEntry $encyclopediaEntry
    ): RedirectResponse {
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
            ->route('knowledge.admin.kategorien.edit', $encyclopediaCategory)
            ->with('status', 'Eintrag aktualisiert.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EncyclopediaCategory $encyclopediaCategory, EncyclopediaEntry $encyclopediaEntry): RedirectResponse
    {
        $this->abortIfCategoryMismatch($encyclopediaCategory, $encyclopediaEntry);

        $encyclopediaEntry->delete();

        return redirect()
            ->route('knowledge.admin.kategorien.edit', $encyclopediaCategory)
            ->with('status', 'Eintrag gelöscht.');
    }

    private function abortIfCategoryMismatch(EncyclopediaCategory $category, EncyclopediaEntry $entry): void
    {
        abort_unless($entry->encyclopedia_category_id === $category->id, 404);
    }
}
