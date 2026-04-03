<?php

namespace App\Http\Controllers;

use App\Actions\Campaign\UpsertCampaignInvitationAction;
use App\Enums\UserRole;
use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\CampaignInvitation\StoreCampaignInvitationRequest;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\SceneBookmark;
use App\Models\SceneSubscription;
use App\Models\User;
use App\Models\World;
use App\Notifications\CampaignInvitationNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignInvitationController extends Controller
{
    use EnsuresWorldContext;

    public function __construct(
        private readonly UpsertCampaignInvitationAction $upsertCampaignInvitationAction,
    ) {}

    public function index(Request $request): View
    {
        $user = $this->authenticatedUser($request);

        $status = in_array((string) $request->query('status', CampaignInvitation::STATUS_PENDING), [
            'all',
            CampaignInvitation::STATUS_PENDING,
            CampaignInvitation::STATUS_ACCEPTED,
            CampaignInvitation::STATUS_DECLINED,
        ], true)
            ? (string) $request->query('status', CampaignInvitation::STATUS_PENDING)
            : CampaignInvitation::STATUS_PENDING;

        $invitationsQuery = CampaignInvitation::query()
            ->where('user_id', $user->id)
            ->with(['campaign.owner', 'campaign.world', 'inviter'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status));

        if ($status === 'all') {
            $invitationsQuery
                ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'accepted' THEN 1 ELSE 2 END")
                ->latest('created_at');
        } else {
            $invitationsQuery->latest('created_at');
        }

        $invitations = $invitationsQuery
            ->paginate(20)
            ->withQueryString();

        $pendingCount = CampaignInvitation::query()
            ->where('user_id', $user->id)
            ->where('status', CampaignInvitation::STATUS_PENDING)
            ->count();

        return view('campaign-invitations.index', compact('invitations', 'status', 'pendingCount'));
    }

    public function store(StoreCampaignInvitationRequest $request, World $world, Campaign $campaign): RedirectResponse
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $user = $this->authenticatedUser($request);

        if (! $this->canManageInvitations($user, $campaign)) {
            abort(403);
        }

        $invitee = User::query()
            ->where('email', $request->validated('email'))
            ->firstOrFail();

        if ($invitee->id === $campaign->owner_id) {
            return back()->withErrors([
                'email' => 'Der Kampagnenleiter benötigt keine Einladung.',
            ]);
        }

        $requestedRole = (string) $request->validated('role');
        if (
            $requestedRole === CampaignInvitation::ROLE_TRUSTED_PLAYER
            && ! $user->hasRole(UserRole::ADMIN)
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'role' => 'Die Rolle "Trusted Player" kann nur von Admins vergeben werden.',
                ]);
        }

        $result = $this->upsertCampaignInvitationAction->execute(
            campaign: $campaign,
            inviteeUserId: (int) $invitee->id,
            inviterUserId: (int) $user->id,
            requestedRole: $requestedRole,
        );

        $invitation = $result->invitation;
        $isNew = $result->isNew;
        $wasAccepted = $result->wasAccepted;

        if (! $wasAccepted) {
            $invitation->loadMissing(['campaign.owner', 'inviter']);
            $invitee->notify(new CampaignInvitationNotification($invitation));
        }

        $status = $wasAccepted
            ? 'Teilnehmerrolle aktualisiert: '.$invitee->name
            : ($isNew
                ? 'Einladung versendet: '.$invitee->name
                : 'Einladung erneut vorgemerkt: '.$invitee->name);

        return redirect()
            ->route('campaigns.show', ['world' => $world, 'campaign' => $campaign])
            ->with('status', $status);
    }

    public function decline(Request $request, World $world, CampaignInvitation $invitation): RedirectResponse
    {
        return $this->respondToInvitation(
            request: $request,
            world: $world,
            invitation: $invitation,
            decision: CampaignInvitation::STATUS_DECLINED,
        );
    }

    public function acceptLegacy(Request $request, CampaignInvitation $invitation): RedirectResponse
    {
        $campaign = $this->resolveCampaignForInvitation($invitation);
        $world = $this->resolveWorldForCampaign($campaign);

        return $this->respondToInvitation(
            request: $request,
            world: $world,
            invitation: $invitation,
            decision: CampaignInvitation::STATUS_ACCEPTED,
        );
    }

    public function declineLegacy(Request $request, CampaignInvitation $invitation): RedirectResponse
    {
        $campaign = $this->resolveCampaignForInvitation($invitation);
        $world = $this->resolveWorldForCampaign($campaign);

        return $this->respondToInvitation(
            request: $request,
            world: $world,
            invitation: $invitation,
            decision: CampaignInvitation::STATUS_DECLINED,
        );
    }

    public function accept(Request $request, World $world, CampaignInvitation $invitation): RedirectResponse
    {
        return $this->respondToInvitation(
            request: $request,
            world: $world,
            invitation: $invitation,
            decision: CampaignInvitation::STATUS_ACCEPTED,
        );
    }

    private function respondToInvitation(
        Request $request,
        World $world,
        CampaignInvitation $invitation,
        string $decision,
    ): RedirectResponse {
        $user = $this->authenticatedUser($request);
        $this->ensureUserOwnsInvitation($user, $invitation);
        $campaign = $this->resolveCampaignForInvitation($invitation);
        $campaignWorld = $this->resolveWorldForCampaign($campaign);
        $this->ensureCampaignBelongsToWorld($world, $campaign);

        if ($invitation->status !== CampaignInvitation::STATUS_PENDING) {
            return redirect()
                ->route('campaign-invitations.index')
                ->with('status', 'Einladung ist nicht mehr offen.');
        }

        $isAccept = $decision === CampaignInvitation::STATUS_ACCEPTED;

        $invitation->status = $isAccept
            ? CampaignInvitation::STATUS_ACCEPTED
            : CampaignInvitation::STATUS_DECLINED;
        $invitation->accepted_at = $isAccept ? now() : null;
        $invitation->responded_at = now();
        $invitation->save();

        if ($isAccept) {
            return redirect()
                ->route('campaigns.show', [
                    'world' => $campaignWorld,
                    'campaign' => $campaign,
                ])
                ->with('status', 'Einladung angenommen.');
        }

        return redirect()->route('campaign-invitations.index')->with('status', 'Einladung abgelehnt.');
    }

    public function destroy(Request $request, World $world, Campaign $campaign, CampaignInvitation $invitation): RedirectResponse
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $user = $this->authenticatedUser($request);

        if (! $this->canManageInvitations($user, $campaign)) {
            abort(403);
        }

        $this->ensureInvitationBelongsToCampaign($campaign, $invitation);

        $shouldCleanupAccessData = $invitation->status === CampaignInvitation::STATUS_ACCEPTED;
        $targetUserId = (int) $invitation->user_id;

        $invitation->delete();

        if ($shouldCleanupAccessData) {
            $sceneIds = $campaign->scenes()->pluck('id');

            if ($sceneIds->isNotEmpty()) {
                SceneSubscription::query()
                    ->where('user_id', $targetUserId)
                    ->whereIn('scene_id', $sceneIds)
                    ->delete();

                SceneBookmark::query()
                    ->where('user_id', $targetUserId)
                    ->whereIn('scene_id', $sceneIds)
                    ->delete();
            }
        }

        return redirect()
            ->route('campaigns.show', ['world' => $world, 'campaign' => $campaign])
            ->with('status', 'Einladung entfernt.');
    }

    private function ensureInvitationBelongsToCampaign(Campaign $campaign, CampaignInvitation $invitation): void
    {
        abort_unless($invitation->campaign_id === $campaign->id, 404);
    }

    private function ensureUserOwnsInvitation(User $user, CampaignInvitation $invitation): void
    {
        abort_unless($invitation->user_id === $user->id, 403);
    }

    private function resolveCampaignForInvitation(CampaignInvitation $invitation): Campaign
    {
        return Campaign::query()
            ->with('world')
            ->findOrFail((int) $invitation->campaign_id);
    }

    private function resolveWorldForCampaign(Campaign $campaign): World
    {
        $world = $campaign->world;
        abort_unless($world instanceof World, 404);

        return $world;
    }

    private function canManageInvitations(User $user, Campaign $campaign): bool
    {
        return $campaign->owner_id === $user->id || $user->isGmOrAdmin();
    }
}
