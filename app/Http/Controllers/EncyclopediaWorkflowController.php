<?php

namespace App\Http\Controllers;

use App\Actions\Encyclopedia\ApproveEncyclopediaEntryAction;
use App\Actions\Encyclopedia\RejectEncyclopediaEntryAction;
use App\Actions\Encyclopedia\StoreEncyclopediaProposalAction;
use App\Actions\Encyclopedia\UpdateEncyclopediaProposalAction;
use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\Encyclopedia\StoreProposalRequest;
use App\Http\Requests\Encyclopedia\UpdateProposalRequest;
use App\Models\EncyclopediaCategory;
use App\Models\EncyclopediaEntry;
use App\Models\World;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EncyclopediaWorkflowController extends Controller
{
    use EnsuresWorldContext;

    public function __construct(
        private readonly StoreEncyclopediaProposalAction $storeEncyclopediaProposalAction,
        private readonly UpdateEncyclopediaProposalAction $updateEncyclopediaProposalAction,
        private readonly ApproveEncyclopediaEntryAction $approveEncyclopediaEntryAction,
        private readonly RejectEncyclopediaEntryAction $rejectEncyclopediaEntryAction,
    ) {}

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

        $actor = $this->authenticatedUser($request);
        /** @var array{
         *   encyclopedia_category_id: int|string,
         *   title: string,
         *   slug: string,
         *   excerpt?: string|null,
         *   content: string
         * } $storeData
         */
        $storeData = $request->validated();
        $category = EncyclopediaCategory::query()
            ->forWorld($world)
            ->whereKey((int) $storeData['encyclopedia_category_id'])
            ->firstOrFail();
        $entry = $this->storeEncyclopediaProposalAction->execute(
            category: $category,
            actor: $actor,
            data: $storeData,
        );

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

        $actor = $this->authenticatedUser($request);
        /** @var array{
         *   encyclopedia_category_id: int|string,
         *   title: string,
         *   slug: string,
         *   excerpt?: string|null,
         *   content: string
         * } $updateData
         */
        $updateData = $request->validated();
        $this->updateEncyclopediaProposalAction->execute(
            entry: $encyclopediaEntry,
            actor: $actor,
            data: $updateData,
        );

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

    public function approve(Request $request, World $world, EncyclopediaEntry $encyclopediaEntry): RedirectResponse
    {
        $this->resolveCategoryFromEntry($world, $encyclopediaEntry);
        $this->authorize('review', [EncyclopediaEntry::class, $world]);
        $reviewer = $this->authenticatedUser($request);
        $this->approveEncyclopediaEntryAction->execute(
            entry: $encyclopediaEntry,
            reviewer: $reviewer,
        );

        return redirect()
            ->route('knowledge.encyclopedia.moderation.index', ['world' => $world])
            ->with('status', 'Vorschlag freigegeben.');
    }

    public function reject(Request $request, World $world, EncyclopediaEntry $encyclopediaEntry): RedirectResponse
    {
        $this->resolveCategoryFromEntry($world, $encyclopediaEntry);
        $this->authorize('review', [EncyclopediaEntry::class, $world]);
        $reviewer = $this->authenticatedUser($request);
        $this->rejectEncyclopediaEntryAction->execute(
            entry: $encyclopediaEntry,
            reviewer: $reviewer,
        );

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

}
