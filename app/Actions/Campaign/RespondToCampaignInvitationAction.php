<?php

declare(strict_types=1);

namespace App\Actions\Campaign;

use App\Models\CampaignInvitation;
use Illuminate\Database\DatabaseManager;

final class RespondToCampaignInvitationAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function execute(
        int $invitationId,
        int $userId,
        int $worldId,
        string $decision,
    ): RespondToCampaignInvitationResult {
        /** @var RespondToCampaignInvitationResult $result */
        $result = $this->db->transaction(function () use (
            $invitationId,
            $userId,
            $worldId,
            $decision,
        ): RespondToCampaignInvitationResult {
            $invitation = CampaignInvitation::query()
                ->whereKey($invitationId)
                ->where('user_id', $userId)
                ->whereHas('campaign', static function ($query) use ($worldId): void {
                    $query->where('world_id', $worldId);
                })
                ->lockForUpdate()
                ->firstOrFail();

            if ($invitation->status !== CampaignInvitation::STATUS_PENDING) {
                return new RespondToCampaignInvitationResult(
                    alreadyClosed: true,
                    isAccepted: false,
                );
            }

            $isAccept = $decision === CampaignInvitation::STATUS_ACCEPTED;

            $invitation->status = $isAccept
                ? CampaignInvitation::STATUS_ACCEPTED
                : CampaignInvitation::STATUS_DECLINED;
            $invitation->accepted_at = $isAccept ? now()->toDateTimeString() : null;
            $invitation->responded_at = now()->toDateTimeString();
            $invitation->save();

            return new RespondToCampaignInvitationResult(
                alreadyClosed: false,
                isAccepted: $isAccept,
            );
        }, 3);

        return $result;
    }
}
