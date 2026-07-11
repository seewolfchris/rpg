<?php

declare(strict_types=1);

namespace App\Actions\Campaign;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\CampaignMembership;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;

final class UpsertCampaignInvitationAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly SyncCampaignMembershipFromInvitationAction $syncCampaignMembershipFromInvitationAction,
    ) {}

    public function execute(
        UpsertCampaignInvitationInput $input,
    ): UpsertCampaignInvitationResult {
        try {
            return $this->runUpsertTransaction(
                campaign: $input->campaign,
                inviteeUserId: $input->inviteeUserId,
                inviterUserId: $input->inviterUserId,
                requestedRole: $input->requestedRole,
            );
        } catch (QueryException $exception) {
            if (! $this->isDuplicateCampaignInvitationKey($exception)) {
                throw $exception;
            }

            return $this->runUpsertTransaction(
                campaign: $input->campaign,
                inviteeUserId: $input->inviteeUserId,
                inviterUserId: $input->inviterUserId,
                requestedRole: $input->requestedRole,
            );
        }
    }

    private function runUpsertTransaction(
        Campaign $campaign,
        int $inviteeUserId,
        int $inviterUserId,
        string $requestedRole,
    ): UpsertCampaignInvitationResult {
        /** @var UpsertCampaignInvitationResult $result */
        $result = $this->db->transaction(function () use (
            $campaign,
            $inviteeUserId,
            $inviterUserId,
            $requestedRole,
        ): UpsertCampaignInvitationResult {
            $invitation = CampaignInvitation::query()
                ->where('campaign_id', (int) $campaign->id)
                ->where('user_id', $inviteeUserId)
                ->lockForUpdate()
                ->first();

            $isNew = false;
            $previousStatus = null;
            $previousRole = null;

            if ($invitation instanceof CampaignInvitation) {
                $previousStatus = (string) $invitation->status;
                $previousRole = (string) $invitation->role;
            } else {
                $isNew = true;
                $invitation = new CampaignInvitation([
                    'campaign_id' => (int) $campaign->id,
                    'user_id' => $inviteeUserId,
                ]);
            }

            $this->assertInviterCanUpsertInvitation(
                campaign: $campaign,
                invitation: $invitation,
                isNew: $isNew,
                previousRole: $previousRole,
                inviteeUserId: $inviteeUserId,
                inviterUserId: $inviterUserId,
                requestedRole: $requestedRole,
            );

            $wasAccepted = $invitation->status === CampaignInvitation::STATUS_ACCEPTED;
            $invitation->invited_by = $inviterUserId;
            $invitation->role = $requestedRole;

            if (! $wasAccepted) {
                $invitation->status = CampaignInvitation::STATUS_PENDING;
                $invitation->accepted_at = null;
                $invitation->responded_at = null;
            }

            if ($isNew) {
                $invitation->created_at = now()->toDateTimeString();
            }

            $invitation->save();

            if ($wasAccepted) {
                $this->syncCampaignMembershipFromInvitationAction->syncAcceptedInvitation(
                    invitation: $invitation,
                    actorUserId: $inviterUserId,
                    source: 'invitation_upsert_accepted',
                );
            }

            return new UpsertCampaignInvitationResult(
                invitation: $invitation,
                isNew: $isNew,
                wasAccepted: $wasAccepted,
                shouldNotify: ! $wasAccepted && (
                    $isNew
                    || $previousStatus !== CampaignInvitation::STATUS_PENDING
                    || $previousRole !== $requestedRole
                ),
            );
        }, 3);

        return $result;
    }

    private function assertInviterCanUpsertInvitation(
        Campaign $campaign,
        CampaignInvitation $invitation,
        bool $isNew,
        ?string $previousRole,
        int $inviteeUserId,
        int $inviterUserId,
        string $requestedRole,
    ): void {
        $inviter = User::query()->findOrFail($inviterUserId);
        $canManageMembershipRoles = $inviter->isAdmin()
            || (int) $campaign->owner_id === $inviterUserId;

        if ($canManageMembershipRoles) {
            return;
        }

        $isCampaignGm = CampaignMembership::query()
            ->where('campaign_id', (int) $campaign->id)
            ->where('user_id', $inviterUserId)
            ->where('role', CampaignMembershipRole::GM->value)
            ->exists();

        if (! $isCampaignGm || $requestedRole !== CampaignInvitation::ROLE_PLAYER) {
            throw new AuthorizationException('Nur Kampagnenleitung oder Admin dürfen privilegierte Rollen vergeben.');
        }

        $hasActiveMembership = CampaignMembership::query()
            ->where('campaign_id', (int) $campaign->id)
            ->where('user_id', $inviteeUserId)
            ->exists();
        $isAccepted = (string) $invitation->status === CampaignInvitation::STATUS_ACCEPTED;
        $changesExistingPrivilegedInvitation = ! $isNew
            && $previousRole !== CampaignInvitation::ROLE_PLAYER;

        if ($hasActiveMembership || $isAccepted || $changesExistingPrivilegedInvitation) {
            throw new AuthorizationException('Bestehende Teilnahmen und Rollen dürfen nur Kampagnenleitung oder Admin ändern.');
        }
    }

    private function isDuplicateCampaignInvitationKey(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo;
        $driverCode = is_array($errorInfo) && isset($errorInfo[1])
            ? (int) $errorInfo[1]
            : 0;
        $message = strtolower($exception->getMessage());

        if ($driverCode === 1062) {
            return true;
        }

        if (str_contains($message, 'duplicate entry')) {
            return true;
        }

        return str_contains(
            $message,
            'unique constraint failed: campaign_invitations.campaign_id, campaign_invitations.user_id'
        );
    }
}
