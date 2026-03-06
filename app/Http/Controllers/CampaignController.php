<?php

namespace App\Http\Controllers;

use App\Http\Requests\Campaign\StoreCampaignRequest;
use App\Http\Requests\Campaign\UpdateCampaignRequest;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Campaign::class, 'campaign');
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        $campaigns = Campaign::query()
            ->with('owner')
            ->withExists([
                'invitations as is_invited' => fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->where('status', CampaignInvitation::STATUS_ACCEPTED),
            ])
            ->visibleTo($user)
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('campaigns.index', compact('campaigns'));
    }

    public function create(): View
    {
        return view('campaigns.create');
    }

    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['owner_id'] = auth()->id();

        $campaign = Campaign::query()->create($data);

        return redirect()
            ->route('campaigns.show', $campaign)
            ->with('status', 'Kampagne erstellt.');
    }

    public function show(Request $request, Campaign $campaign): View
    {
        $user = $request->user();

        $sceneStatus = in_array((string) $request->query('scene_status', 'all'), ['all', 'open', 'closed', 'archived'], true)
            ? (string) $request->query('scene_status', 'all')
            : 'all';
        $sceneSearch = trim((string) $request->query('q', ''));

        $campaign->load('owner');

        $canManageCampaign = $campaign->owner_id === $user->id
            || $user->isGmOrAdmin()
            || $campaign->isCoGm($user);

        $scenesQuery = $campaign->scenes()
            ->with('creator')
            ->withCount('posts')
            ->withMax('posts as latest_post_id', 'id')
            ->withMax('posts as latest_post_created_at', 'created_at')
            ->with([
                'subscriptions' => fn ($query) => $query
                    ->where('user_id', $user->id),
            ])
            ->when(
                ! $canManageCampaign,
                fn ($query) => $query->where('status', '!=', 'archived')
            );

        if ($sceneStatus !== 'all') {
            $scenesQuery->where('status', $sceneStatus);
        }

        if ($sceneSearch !== '') {
            $searchTerm = '%'.$sceneSearch.'%';
            $scenesQuery->where(function ($query) use ($searchTerm): void {
                $query
                    ->where('title', 'like', $searchTerm)
                    ->orWhere('summary', 'like', $searchTerm);
            });
        }

        $scenes = $scenesQuery
            ->orderBy('position')
            ->orderBy('created_at')
            ->paginate(12)
            ->withQueryString();

        $invitations = collect();
        $canManageInvitations = $campaign->owner_id === $user->id || $user->isGmOrAdmin();

        if ($canManageInvitations) {
            $invitations = $campaign->invitations()
                ->with(['user', 'inviter'])
                ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'accepted' THEN 1 ELSE 2 END")
                ->latest('created_at')
                ->get();
        }

        return view('campaigns.show', compact(
            'campaign',
            'scenes',
            'sceneStatus',
            'sceneSearch',
            'invitations',
            'canManageInvitations',
            'canManageCampaign',
        ));
    }

    public function edit(Campaign $campaign): View
    {
        return view('campaigns.edit', compact('campaign'));
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        $campaign->update($request->validated());

        return redirect()
            ->route('campaigns.show', $campaign)
            ->with('status', 'Kampagne aktualisiert.');
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        $campaign->delete();

        return redirect()
            ->route('campaigns.index')
            ->with('status', 'Kampagne gelöscht.');
    }
}
