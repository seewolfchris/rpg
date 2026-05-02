<?php

namespace App\Http\Controllers;

use App\Actions\Campaign\CreateCampaignAction;
use App\Actions\Campaign\DeleteCampaignAction;
use App\Actions\Campaign\UpdateCampaignAction;
use App\Actions\CampaignGmContact\BuildCampaignGmContactPanelDataAction;
use App\Enums\CampaignMembershipRole;
use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\Campaign\StoreCampaignRequest;
use App\Http\Requests\Campaign\UpdateCampaignRequest;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\World;
use App\Support\Navigation\SafeReturnUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignController extends Controller
{
    use EnsuresWorldContext;

    public function __construct(
        private readonly CreateCampaignAction $createCampaignAction,
        private readonly UpdateCampaignAction $updateCampaignAction,
        private readonly DeleteCampaignAction $deleteCampaignAction,
        private readonly BuildCampaignGmContactPanelDataAction $buildCampaignGmContactPanelDataAction,
        private readonly SafeReturnUrl $safeReturnUrl,
    ) {
        $this->authorizeResource(Campaign::class, 'campaign');
    }

    public function index(Request $request, World $world): View
    {
        $user = $this->authenticatedUser($request);

        $campaigns = Campaign::query()
            ->forWorld($world)
            ->with(['owner', 'world'])
            ->withExists([
                'invitations as is_invited' => fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->where('status', CampaignInvitation::STATUS_ACCEPTED),
            ])
            ->visibleTo($user)
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('campaigns.index', compact('campaigns', 'world'));
    }

    public function create(Request $request, World $world): View
    {
        $fallback = route('campaigns.index', ['world' => $world]);
        $backUrl = $this->safeReturnUrl->resolve($request, $fallback);
        $returnTo = $this->safeReturnUrl->carry($request);

        return view('campaigns.create', compact('world', 'backUrl', 'returnTo'));
    }

    public function store(StoreCampaignRequest $request, World $world): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $campaign = $this->createCampaignAction->execute(
            world: $world,
            owner: $user,
            data: $request->validated(),
        );

        $parameters = ['world' => $world, 'campaign' => $campaign];
        $returnTo = $this->safeReturnUrl->carry($request);
        if (is_string($returnTo) && $returnTo !== '') {
            $parameters['return_to'] = $returnTo;
        }

        return redirect()
            ->route('campaigns.show', $parameters)
            ->with('status', 'Kampagne erstellt.');
    }

    public function show(Request $request, World $world, Campaign $campaign): View
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);

        $user = $this->authenticatedUser($request);

        $sceneStatus = in_array((string) $request->query('scene_status', 'all'), ['all', 'open', 'closed', 'archived'], true)
            ? (string) $request->query('scene_status', 'all')
            : 'all';
        $sceneSearch = trim((string) $request->query('q', ''));

        $campaign->load('owner');

        $canManageCampaign = $campaign->canManageCampaign($user);

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

        $memberships = $campaign->memberships()
            ->with(['user', 'assigner'])
            ->get()
            ->sortBy([
                function ($membership): int {
                    $role = $membership->role instanceof CampaignMembershipRole
                        ? $membership->role->value
                        : (string) $membership->role;

                    return match ($role) {
                        CampaignMembershipRole::GM->value => 0,
                        CampaignMembershipRole::TRUSTED_PLAYER->value => 1,
                        default => 2,
                    };
                },
                fn ($membership) => mb_strtolower((string) data_get($membership, 'user.name', '')),
            ])
            ->values();

        $pendingInvitations = collect();
        $canManageInvitations = $campaign->isOwnedBy($user);
        $canManageMembershipRoles = $campaign->isOwnedBy($user);

        if ($canManageInvitations) {
            $pendingInvitations = $campaign->invitations()
                ->with(['user', 'inviter'])
                ->where('status', CampaignInvitation::STATUS_PENDING)
                ->latest('created_at')
                ->get();
        }

        $selectedThreadId = $request->integer('gm_contact_thread');
        if ($selectedThreadId <= 0) {
            $selectedThreadId = null;
        }

        $gmContactPanelData = $this->buildCampaignGmContactPanelDataAction->execute(
            campaign: $campaign,
            user: $user,
            selectedThreadId: $selectedThreadId,
            sceneStatus: $sceneStatus,
            sceneSearch: $sceneSearch,
            canManageCampaign: $canManageCampaign,
        );

        $backUrl = $this->safeReturnUrl->resolve($request, route('campaigns.index', ['world' => $world]));

        return view('campaigns.show', compact(
            'world',
            'campaign',
            'scenes',
            'sceneStatus',
            'sceneSearch',
            'memberships',
            'pendingInvitations',
            'canManageInvitations',
            'canManageMembershipRoles',
            'canManageCampaign',
            'gmContactPanelData',
            'backUrl',
        ));
    }

    public function edit(Request $request, World $world, Campaign $campaign): View
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);

        $fallback = route('campaigns.index', ['world' => $world]);
        $backUrl = $this->safeReturnUrl->resolve($request, $fallback);
        $returnTo = $this->safeReturnUrl->carry($request);

        return view('campaigns.edit', compact('campaign', 'world', 'backUrl', 'returnTo'));
    }

    public function update(UpdateCampaignRequest $request, World $world, Campaign $campaign): RedirectResponse
    {
        $this->updateCampaignAction->execute(
            world: $world,
            campaign: $campaign,
            data: $request->validated(),
        );

        $parameters = ['world' => $world, 'campaign' => $campaign];
        $returnTo = $this->safeReturnUrl->carry($request);
        if (is_string($returnTo) && $returnTo !== '') {
            $parameters['return_to'] = $returnTo;
        }

        return redirect()
            ->route('campaigns.show', $parameters)
            ->with('status', 'Kampagne aktualisiert.');
    }

    public function destroy(World $world, Campaign $campaign): RedirectResponse
    {
        $this->deleteCampaignAction->execute(
            world: $world,
            campaign: $campaign,
        );

        return redirect()
            ->route('campaigns.index', ['world' => $world])
            ->with('status', 'Kampagne gelöscht.');
    }
}
