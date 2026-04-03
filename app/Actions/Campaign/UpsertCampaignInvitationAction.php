<?php

declare(strict_types=1);

namespace App\Actions\Campaign;

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;

class UpsertCampaignInvitationAction
{
    public function __construct(
        private readonly DatabaseManager $db,
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

            $isNew = ! $invitation instanceof CampaignInvitation;

            if ($isNew) {
                $invitation = new CampaignInvitation([
                    'campaign_id' => (int) $campaign->id,
                    'user_id' => $inviteeUserId,
                ]);
            }

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

            return new UpsertCampaignInvitationResult(
                invitation: $invitation,
                isNew: $isNew,
                wasAccepted: $wasAccepted,
            );
        }, 3);

        return $result;
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
