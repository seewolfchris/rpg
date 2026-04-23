<?php

declare(strict_types=1);

namespace App\Actions\Campaign;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\CampaignMembership;
use App\Models\CampaignRoleEvent;

final class SyncCampaignMembershipFromInvitationAction
{
    public function syncAcceptedInvitation(
        CampaignInvitation $invitation,
        ?int $actorUserId,
        string $source,
    ): void {
        $campaign = $this->resolveCampaign($invitation);
        $targetUserId = (int) $invitation->user_id;

        if ($targetUserId <= 0) {
            return;
        }

        if ((int) $campaign->owner_id === $targetUserId) {
            $this->ensureOwnerGmMembership($campaign, $targetUserId, $actorUserId, $source);

            return;
        }

        $newRole = $this->mapInvitationRole((string) $invitation->role)->value;

        $membership = CampaignMembership::query()
            ->where('campaign_id', (int) $campaign->id)
            ->where('user_id', $targetUserId)
            ->lockForUpdate()
            ->first();

        if (! $membership instanceof CampaignMembership) {
            CampaignMembership::query()->create([
                'campaign_id' => (int) $campaign->id,
                'user_id' => $targetUserId,
                'role' => $newRole,
                'assigned_by' => $this->normalizeActor($actorUserId),
                'assigned_at' => now(),
            ]);

            $this->recordEvent(
                campaignId: (int) $campaign->id,
                actorUserId: $actorUserId,
                targetUserId: $targetUserId,
                eventType: CampaignRoleEvent::EVENT_MEMBERSHIP_GRANTED,
                oldRole: null,
                newRole: $newRole,
                source: $source,
                meta: [
                    'invitation_id' => (int) $invitation->id,
                    'invitation_role' => (string) $invitation->role,
                ],
            );

            return;
        }

        $oldRole = $this->membershipRoleValue($membership);

        if ($oldRole === $newRole) {
            return;
        }

        $membership->role = $newRole;
        $membership->assigned_by = $this->normalizeActor($actorUserId);
        $membership->assigned_at = now();
        $membership->save();

        $this->recordEvent(
            campaignId: (int) $campaign->id,
            actorUserId: $actorUserId,
            targetUserId: $targetUserId,
            eventType: CampaignRoleEvent::EVENT_MEMBERSHIP_ROLE_CHANGED,
            oldRole: $oldRole,
            newRole: $newRole,
            source: $source,
            meta: [
                'invitation_id' => (int) $invitation->id,
                'invitation_role' => (string) $invitation->role,
            ],
        );
    }

    public function revokeForAcceptedInvitation(
        CampaignInvitation $invitation,
        ?int $actorUserId,
        string $source,
    ): void {
        $campaign = $this->resolveCampaign($invitation);
        $targetUserId = (int) $invitation->user_id;

        if ($targetUserId <= 0) {
            return;
        }

        if ((int) $campaign->owner_id === $targetUserId) {
            return;
        }

        $membership = CampaignMembership::query()
            ->where('campaign_id', (int) $campaign->id)
            ->where('user_id', $targetUserId)
            ->lockForUpdate()
            ->first();

        if (! $membership instanceof CampaignMembership) {
            return;
        }

        $oldRole = $this->membershipRoleValue($membership);
        $membership->delete();

        $this->recordEvent(
            campaignId: (int) $campaign->id,
            actorUserId: $actorUserId,
            targetUserId: $targetUserId,
            eventType: CampaignRoleEvent::EVENT_MEMBERSHIP_REVOKED,
            oldRole: $oldRole,
            newRole: null,
            source: $source,
            meta: [
                'invitation_id' => (int) $invitation->id,
                'invitation_role' => (string) $invitation->role,
            ],
        );
    }

    private function ensureOwnerGmMembership(
        Campaign $campaign,
        int $ownerUserId,
        ?int $actorUserId,
        string $source,
    ): void {
        $membership = CampaignMembership::query()
            ->where('campaign_id', (int) $campaign->id)
            ->where('user_id', $ownerUserId)
            ->lockForUpdate()
            ->first();

        $gmRole = CampaignMembershipRole::GM->value;

        if (! $membership instanceof CampaignMembership) {
            CampaignMembership::query()->create([
                'campaign_id' => (int) $campaign->id,
                'user_id' => $ownerUserId,
                'role' => $gmRole,
                'assigned_by' => (int) $campaign->owner_id,
                'assigned_at' => now(),
            ]);

            $this->recordEvent(
                campaignId: (int) $campaign->id,
                actorUserId: $actorUserId,
                targetUserId: $ownerUserId,
                eventType: CampaignRoleEvent::EVENT_MEMBERSHIP_GRANTED,
                oldRole: null,
                newRole: $gmRole,
                source: $source,
                meta: [
                    'reason' => 'owner_membership_guard',
                ],
            );

            return;
        }

        $oldRole = $this->membershipRoleValue($membership);

        if ($oldRole === $gmRole) {
            return;
        }

        $membership->role = $gmRole;
        $membership->assigned_by = (int) $campaign->owner_id;
        $membership->assigned_at = now();
        $membership->save();

        $this->recordEvent(
            campaignId: (int) $campaign->id,
            actorUserId: $actorUserId,
            targetUserId: $ownerUserId,
            eventType: CampaignRoleEvent::EVENT_MEMBERSHIP_ROLE_CHANGED,
            oldRole: $oldRole,
            newRole: $gmRole,
            source: $source,
            meta: [
                'reason' => 'owner_membership_guard',
            ],
        );
    }

    private function resolveCampaign(CampaignInvitation $invitation): Campaign
    {
        if ($invitation->relationLoaded('campaign')) {
            $loadedCampaign = $invitation->getRelation('campaign');

            if ($loadedCampaign instanceof Campaign) {
                return $loadedCampaign;
            }
        }

        return Campaign::query()
            ->whereKey((int) $invitation->campaign_id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function mapInvitationRole(string $invitationRole): CampaignMembershipRole
    {
        return match ($invitationRole) {
            CampaignInvitation::ROLE_CO_GM => CampaignMembershipRole::GM,
            CampaignInvitation::ROLE_TRUSTED_PLAYER => CampaignMembershipRole::TRUSTED_PLAYER,
            default => CampaignMembershipRole::PLAYER,
        };
    }

    private function normalizeActor(?int $actorUserId): ?int
    {
        return $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null;
    }

    private function membershipRoleValue(CampaignMembership $membership): ?string
    {
        $role = $membership->role;

        if ($role instanceof CampaignMembershipRole) {
            return $role->value;
        }

        return is_string($role) ? $role : null;
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function recordEvent(
        int $campaignId,
        ?int $actorUserId,
        int $targetUserId,
        string $eventType,
        ?string $oldRole,
        ?string $newRole,
        string $source,
        ?array $meta = null,
    ): void {
        CampaignRoleEvent::query()->create([
            'campaign_id' => $campaignId,
            'actor_user_id' => $this->normalizeActor($actorUserId),
            'target_user_id' => $targetUserId,
            'event_type' => $eventType,
            'old_role' => $oldRole,
            'new_role' => $newRole,
            'source' => $source,
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }
}
