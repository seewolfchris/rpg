<?php

declare(strict_types=1);

namespace App\Actions\Campaign;

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

final class InviteRegisteredCampaignUsersAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly UpsertCampaignInvitationAction $upsertCampaignInvitationAction,
    ) {}

    /**
     * @param  list<int>  $selectedUserIds
     * @return list<array{invitee: User, invitation: CampaignInvitation}>
     */
    public function execute(
        Campaign $campaign,
        User $inviter,
        array $selectedUserIds,
        string $requestedRole,
    ): array {
        $invitees = User::query()
            ->whereIn('id', $selectedUserIds)
            ->get(['id', 'name', 'email'])
            ->keyBy(static fn (User $invitee): int => (int) $invitee->id);

        if ($invitees->count() !== count($selectedUserIds)) {
            throw new \RuntimeException('Invitee missing during bulk invitation setup.');
        }

        /**
         * @var list<array{invitee: User, invitation: CampaignInvitation}> $pendingNotifications
         */
        $pendingNotifications = [];

        $this->db->transaction(function () use (
            $campaign,
            $invitees,
            $inviter,
            $requestedRole,
            $selectedUserIds,
            &$pendingNotifications,
        ): void {
            foreach ($selectedUserIds as $selectedUserId) {
                $invitee = $invitees->get($selectedUserId);
                if (! $invitee instanceof User) {
                    throw new \RuntimeException('Invitee missing during bulk invitation transaction.');
                }

                $result = $this->upsertCampaignInvitationAction->execute(
                    new UpsertCampaignInvitationInput(
                        campaign: $campaign,
                        inviteeUserId: (int) $invitee->id,
                        inviterUserId: (int) $inviter->id,
                        requestedRole: $requestedRole,
                    ),
                );

                if ($result->wasAccepted) {
                    continue;
                }

                $pendingNotifications[] = [
                    'invitee' => $invitee,
                    'invitation' => $result->invitation,
                ];
            }
        });

        return $pendingNotifications;
    }
}
