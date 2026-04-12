<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\Encyclopedia\StoreProposalRequest;
use App\Http\Requests\Encyclopedia\UpdateProposalRequest;
use App\Models\EncyclopediaCategory;
use App\Models\EncyclopediaEntry;
use App\Models\World;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EncyclopediaWorkflowController extends Controller
{
    use EnsuresWorldContext;

    public function createProposal(World $world): View
    {
        $this->authorize('propose', EncyclopediaEntry::class);

        $entry = new EncyclopediaEntry;
        $categories = EncyclopediaCategory::query()
            ->forWorld($world)
            ->visible()
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return view('knowledge.proposals.create', compact('world', 'entry', 'categories'));
    }

    public function storeProposal(StoreProposalRequest $request, World $world): RedirectResponse
    {
        $this->authorize('propose', EncyclopediaEntry::class);

        $data = $request->validated();
        $category = $this->resolvePublicCategoryForWorld($world, (int) $data['encyclopedia_category_id']);

        $entry = EncyclopediaEntry::query()->create([
            'encyclopedia_category_id' => (int) $category->id,
            'title' => $data['title'],
            'slug' => $data['slug'],
            'excerpt' => $data['excerpt'] ?? null,
            'content' => $data['content'],
            'status' => EncyclopediaEntry::STATUS_PENDING,
            'position' => 0,
            'published_at' => null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        return redirect()
            ->route('knowledge.encyclopedia.proposals.edit', [
                'world' => $world,
                'encyclopediaEntry' => $entry,
            ])
            ->with('status', 'Vorschlag gespeichert und zur Prüfung eingereicht.');
    }

    public function editProposal(World $world, EncyclopediaEntry $encyclopediaEntry): View
    {
        $category = $this->resolveCategoryFromEntry($world, $encyclopediaEntry);
        $this->authorize('updateProposal', $encyclopediaEntry);

        $entry = $encyclopediaEntry;
        $categories = EncyclopediaCategory::query()
            ->forWorld($world)
            ->visible()
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return view('knowledge.proposals.edit', compact('world', 'entry', 'category', 'categories'));
    }

    public function updateProposal(
        UpdateProposalRequest $request,
        World $world,
        EncyclopediaEntry $encyclopediaEntry
    ): RedirectResponse {
        $this->resolveCategoryFromEntry($world, $encyclopediaEntry);
        $this->authorize('updateProposal', $encyclopediaEntry);

        $data = $request->validated();
        $category = $this->resolvePublicCategoryForWorld($world, (int) $data['encyclopedia_category_id']);

        $encyclopediaEntry->revisions()->create([
            'editor_id' => auth()->id(),
            'title_before' => $encyclopediaEntry->title,
            'excerpt_before' => $encyclopediaEntry->excerpt,
            'content_before' => $encyclopediaEntry->content,
            'status_before' => $encyclopediaEntry->status,
            'created_at' => now(),
        ]);

        $encyclopediaEntry->update([
            'encyclopedia_category_id' => (int) $category->id,
            'title' => $data['title'],
            'slug' => $data['slug'],
            'excerpt' => $data['excerpt'] ?? null,
            'content' => $data['content'],
            'status' => EncyclopediaEntry::STATUS_PENDING,
            'position' => 0,
            'published_at' => null,
            'updated_by' => auth()->id(),
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        return redirect()
            ->route('knowledge.encyclopedia.proposals.edit', [
                'world' => $world,
                'encyclopediaEntry' => $encyclopediaEntry,
            ])
            ->with('status', 'Vorschlag aktualisiert und erneut zur Prüfung eingereicht.');
    }

    public function moderationIndex(World $world): View
    {
        $this->authorize('review', [EncyclopediaEntry::class, $world]);

        $entries = EncyclopediaEntry::query()
            ->where('status', EncyclopediaEntry::STATUS_PENDING)
            ->whereIn('encyclopedia_category_id', EncyclopediaCategory::query()
                ->forWorld($world)
                ->select('id'))
            ->with([
                'category:id,world_id,name,slug',
                'creator:id,name',
            ])
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('knowledge.proposals.moderation', compact('world', 'entries'));
    }

    public function approve(World $world, EncyclopediaEntry $encyclopediaEntry): RedirectResponse
    {
        $this->resolveCategoryFromEntry($world, $encyclopediaEntry);
        $this->authorize('review', [EncyclopediaEntry::class, $world]);
        abort_unless($encyclopediaEntry->status === EncyclopediaEntry::STATUS_PENDING, 404);

        $updatePayload = [
            'status' => EncyclopediaEntry::STATUS_PUBLISHED,
            'updated_by' => auth()->id(),
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ];

        if (! $encyclopediaEntry->published_at) {
            $updatePayload['published_at'] = now();
        }

        $encyclopediaEntry->update($updatePayload);

        return redirect()
            ->route('knowledge.encyclopedia.moderation.index', ['world' => $world])
            ->with('status', 'Vorschlag freigegeben.');
    }

    public function reject(World $world, EncyclopediaEntry $encyclopediaEntry): RedirectResponse
    {
        $this->resolveCategoryFromEntry($world, $encyclopediaEntry);
        $this->authorize('review', [EncyclopediaEntry::class, $world]);
        abort_unless($encyclopediaEntry->status === EncyclopediaEntry::STATUS_PENDING, 404);

        $encyclopediaEntry->update([
            'status' => EncyclopediaEntry::STATUS_REJECTED,
            'updated_by' => auth()->id(),
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'published_at' => null,
        ]);

        return redirect()
            ->route('knowledge.encyclopedia.moderation.index', ['world' => $world])
            ->with('status', 'Vorschlag abgelehnt.');
    }

    private function resolveCategoryFromEntry(World $world, EncyclopediaEntry $entry): EncyclopediaCategory
    {
        $entry->loadMissing('category');

        $category = $entry->category;
        abort_unless($category instanceof EncyclopediaCategory, 404);
        $this->ensureCategoryBelongsToWorld($world, $category);

        return $category;
    }

    private function resolvePublicCategoryForWorld(World $world, int $categoryId): EncyclopediaCategory
    {
        $category = EncyclopediaCategory::query()
            ->forWorld($world)
            ->visible()
            ->findOrFail($categoryId);
        $this->ensureCategoryBelongsToWorld($world, $category);

        return $category;
    }
}
