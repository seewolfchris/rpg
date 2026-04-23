<?php

namespace App\Http\Controllers;

use App\Actions\Campaign\UpdateCampaignMembershipRoleAction;
use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\CampaignMembership\UpdateCampaignMembershipRoleRequest;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\World;
use Illuminate\Http\RedirectResponse;

class CampaignMembershipController extends Controller
{
    use EnsuresWorldContext;

    public function __construct(
        private readonly UpdateCampaignMembershipRoleAction $updateCampaignMembershipRoleAction,
    ) {}

    public function update(
        UpdateCampaignMembershipRoleRequest $request,
        World $world,
        Campaign $campaign,
        CampaignMembership $membership,
    ): RedirectResponse {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->ensureMembershipBelongsToCampaign($campaign, $membership);

        $actor = $this->authenticatedUser($request);

        $this->updateCampaignMembershipRoleAction->execute(
            campaign: $campaign,
            membership: $membership,
            actorUserId: (int) $actor->id,
            role: (string) $request->validated('role'),
        );

        return redirect()
            ->route('campaigns.show', ['world' => $world, 'campaign' => $campaign])
            ->with('status', 'Teilnehmerrolle aktualisiert.');
    }

    private function ensureMembershipBelongsToCampaign(Campaign $campaign, CampaignMembership $membership): void
    {
        abort_unless((int) $membership->campaign_id === (int) $campaign->id, 404);
    }
}
