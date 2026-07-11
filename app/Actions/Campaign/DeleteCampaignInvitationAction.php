<?php

declare(strict_types=1);

namespace App\Actions\Campaign;

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Scene;
use App\Models\SceneBookmark;
use App\Models\SceneSubscription;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;

final class DeleteCampaignInvitationAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly SyncCampaignMembershipFromInvitationAction $syncCampaignMembershipFromInvitationAction,
    ) {}

    public function execute(CampaignInvitation $invitation, ?int $actorUserId = null): void
    {
        $this->db->transaction(function () use ($invitation, $actorUserId): void {
            $lockedInvitation = $this->lockAndVerifyContext($invitation);

            if ($this->isAcceptedInvitation($lockedInvitation)) {
                $this->assertActorCanRevokeAcceptedMembership($lockedInvitation, $actorUserId);
                $this->cleanupAcceptedInvitationAccessData($lockedInvitation);
                $this->syncCampaignMembershipFromInvitationAction->revokeForAcceptedInvitation(
                    invitation: $lockedInvitation,
                    actorUserId: $actorUserId,
                    source: 'invitation_delete_accepted',
                );
            }

            $this->deleteInvitation($lockedInvitation);
        }, 3);
    }

    private function lockAndVerifyContext(CampaignInvitation $invitation): CampaignInvitation
    {
        /** @var CampaignInvitation $lockedInvitation */
        $lockedInvitation = CampaignInvitation::query()
            ->whereKey((int) $invitation->id)
            ->where('campaign_id', (int) $invitation->campaign_id)
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedInvitation;
    }

    private function isAcceptedInvitation(CampaignInvitation $invitation): bool
    {
        return (string) $invitation->status === CampaignInvitation::STATUS_ACCEPTED;
    }

    private function assertActorCanRevokeAcceptedMembership(CampaignInvitation $invitation, ?int $actorUserId): void
    {
        if ($actorUserId === null) {
            return;
        }

        $actor = User::query()->findOrFail($actorUserId);
        $campaign = Campaign::query()->findOrFail((int) $invitation->campaign_id);

        if (! $actor->isAdmin() && (int) $campaign->owner_id !== $actorUserId) {
            throw new AuthorizationException('Nur Kampagnenleitung oder Admin dürfen angenommene Teilnahmen entfernen.');
        }
    }

    private function cleanupAcceptedInvitationAccessData(CampaignInvitation $invitation): void
    {
        $targetUserId = (int) $invitation->user_id;
        $sceneIdsSubquery = $this->campaignSceneIdsSubquery($invitation);

        SceneSubscription::query()
            ->where('user_id', $targetUserId)
            ->whereIn('scene_id', $sceneIdsSubquery)
            ->delete();

        SceneBookmark::query()
            ->where('user_id', $targetUserId)
            ->whereIn('scene_id', $sceneIdsSubquery)
            ->delete();
    }

    /**
     * @return Builder<Scene>
     */
    private function campaignSceneIdsSubquery(CampaignInvitation $invitation): Builder
    {
        return Scene::query()
            ->select('id')
            ->where('campaign_id', (int) $invitation->campaign_id);
    }

    private function deleteInvitation(CampaignInvitation $invitation): void
    {
        $invitation->delete();
    }
}
