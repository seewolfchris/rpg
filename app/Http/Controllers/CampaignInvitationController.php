<?php

namespace App\Http\Controllers;

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

    public function index(Request $request): View
    {
        $status = in_array((string) $request->query('status', CampaignInvitation::STATUS_PENDING), [
            'all',
            CampaignInvitation::STATUS_PENDING,
            CampaignInvitation::STATUS_ACCEPTED,
            CampaignInvitation::STATUS_DECLINED,
        ], true)
            ? (string) $request->query('status', CampaignInvitation::STATUS_PENDING)
            : CampaignInvitation::STATUS_PENDING;

        $invitations = CampaignInvitation::query()
            ->where('user_id', $request->user()->id)
            ->with(['campaign.owner', 'campaign.world', 'inviter'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'accepted' THEN 1 ELSE 2 END")
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        $pendingCount = CampaignInvitation::query()
            ->where('user_id', $request->user()->id)
            ->where('status', CampaignInvitation::STATUS_PENDING)
            ->count();

        return view('campaign-invitations.index', compact('invitations', 'status', 'pendingCount'));
    }

    public function store(StoreCampaignInvitationRequest $request, World $world, Campaign $campaign): RedirectResponse
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);

        if (! $this->canManageInvitations($request->user(), $campaign)) {
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

        $invitation = CampaignInvitation::query()->firstOrNew([
            'campaign_id' => $campaign->id,
            'user_id' => $invitee->id,
        ]);

        $isNew = ! $invitation->exists;
        $wasAccepted = $invitation->status === CampaignInvitation::STATUS_ACCEPTED;

        $invitation->invited_by = $request->user()->id;
        $invitation->role = $requestedRole;

        if (! $wasAccepted) {
            $invitation->status = CampaignInvitation::STATUS_PENDING;
            $invitation->accepted_at = null;
            $invitation->responded_at = null;
        }

        if ($isNew) {
            $invitation->created_at = now();
        }

        $invitation->save();

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

    public function accept(Request $request, CampaignInvitation $invitation): RedirectResponse
    {
        $this->ensureUserOwnsInvitation($request->user(), $invitation);

        if ($invitation->status !== CampaignInvitation::STATUS_PENDING) {
            return redirect()
                ->route('campaign-invitations.index')
                ->with('status', 'Einladung ist nicht mehr offen.');
        }

        $invitation->status = CampaignInvitation::STATUS_ACCEPTED;
        $invitation->accepted_at = now();
        $invitation->responded_at = now();
        $invitation->save();

        $campaign = Campaign::query()
            ->with('world')
            ->findOrFail((int) $invitation->campaign_id);

        return redirect()
            ->route('campaigns.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
            ])
            ->with('status', 'Einladung angenommen.');
    }

    public function decline(Request $request, CampaignInvitation $invitation): RedirectResponse
    {
        $this->ensureUserOwnsInvitation($request->user(), $invitation);

        if ($invitation->status !== CampaignInvitation::STATUS_PENDING) {
            return redirect()
                ->route('campaign-invitations.index')
                ->with('status', 'Einladung ist nicht mehr offen.');
        }

        $invitation->status = CampaignInvitation::STATUS_DECLINED;
        $invitation->accepted_at = null;
        $invitation->responded_at = now();
        $invitation->save();

        return redirect()
            ->route('campaign-invitations.index')
            ->with('status', 'Einladung abgelehnt.');
    }

    public function destroy(Request $request, World $world, Campaign $campaign, CampaignInvitation $invitation): RedirectResponse
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);

        if (! $this->canManageInvitations($request->user(), $campaign)) {
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

    private function canManageInvitations(User $user, Campaign $campaign): bool
    {
        return $campaign->owner_id === $user->id || $user->isGmOrAdmin();
    }
}
