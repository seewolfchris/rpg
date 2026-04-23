<?php

declare(strict_types=1);

namespace App\Actions\Campaign;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\CampaignRoleEvent;
use Illuminate\Database\DatabaseManager;

final class UpdateCampaignMembershipRoleAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function execute(
        Campaign $campaign,
        CampaignMembership $membership,
        int $actorUserId,
        string $role,
    ): CampaignMembership {
        /** @var CampaignMembership $updatedMembership */
        $updatedMembership = $this->db->transaction(function () use (
            $campaign,
            $membership,
            $actorUserId,
            $role,
        ): CampaignMembership {
            $lockedMembership = CampaignMembership::query()
                ->whereKey((int) $membership->id)
                ->where('campaign_id', (int) $campaign->id)
                ->lockForUpdate()
                ->firstOrFail();

            $nextRole = CampaignMembershipRole::from($role)->value;
            $oldRole = $this->membershipRoleValue($lockedMembership);

            if ($oldRole === $nextRole) {
                return $lockedMembership;
            }

            $lockedMembership->forceFill([
                'role' => $nextRole,
                'assigned_by' => $actorUserId,
                'assigned_at' => now(),
            ]);
            $lockedMembership->save();

            CampaignRoleEvent::query()->create([
                'campaign_id' => (int) $campaign->id,
                'actor_user_id' => $actorUserId,
                'target_user_id' => (int) $lockedMembership->user_id,
                'event_type' => CampaignRoleEvent::EVENT_MEMBERSHIP_ROLE_CHANGED,
                'old_role' => $oldRole,
                'new_role' => $nextRole,
                'source' => 'campaign_membership_role_update',
                'meta' => [
                    'membership_id' => (int) $lockedMembership->id,
                ],
                'created_at' => now(),
            ]);

            return $lockedMembership;
        }, 3);

        return $updatedMembership;
    }

    private function membershipRoleValue(CampaignMembership $membership): ?string
    {
        $role = $membership->role;

        if ($role instanceof CampaignMembershipRole) {
            return $role->value;
        }

        return is_string($role) ? $role : null;
    }
}
