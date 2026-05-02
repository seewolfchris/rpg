<?php

declare(strict_types=1);

namespace App\Domain\Campaign;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\CampaignMembership;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CampaignAccess
{
    /**
     * @param  Builder<Campaign>  $query
     * @return Builder<Campaign>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $innerQuery) => $this->applyVisibleCampaignConstraint($innerQuery, $user));
    }

    /**
     * @template TModel of Model
     * @param  Builder<TModel>  $campaignQuery
     */
    public function applyVisibleCampaignConstraint(Builder $campaignQuery, User $user): void
    {
        $campaignQuery
            ->where('is_public', true)
            ->orWhere('owner_id', (int) $user->id)
            ->orWhereHas('memberships', function (Builder $membershipQuery) use ($user): void {
                $membershipQuery->where('user_id', (int) $user->id);
            });
    }

    public function isVisibleTo(Campaign $campaign, User $user): bool
    {
        if ((bool) $campaign->is_public) {
            return true;
        }

        if ($this->isOwnedBy($campaign, $user)) {
            return true;
        }

        return $this->hasMembership($campaign, $user);
    }

    public function hasAcceptedInvitation(Campaign $campaign, User $user): bool
    {
        return $this->hasMembership($campaign, $user);
    }

    public function hasMembership(Campaign $campaign, User $user): bool
    {
        if ($campaign->relationLoaded('memberships')) {
            foreach ($campaign->memberships as $membership) {
                if (! $membership instanceof CampaignMembership) {
                    continue;
                }

                if ((int) $membership->user_id === (int) $user->id) {
                    return true;
                }
            }

            return false;
        }

        return $campaign->memberships()
            ->where('user_id', (int) $user->id)
            ->exists();
    }

    public function hasMembershipRole(Campaign $campaign, User $user, CampaignMembershipRole|string $role): bool
    {
        $roleValue = $role instanceof CampaignMembershipRole ? $role->value : $role;

        if ($campaign->relationLoaded('memberships')) {
            foreach ($campaign->memberships as $membership) {
                if (! $membership instanceof CampaignMembership) {
                    continue;
                }

                if ((int) $membership->user_id !== (int) $user->id) {
                    continue;
                }

                if ($this->membershipRoleValue($membership) === $roleValue) {
                    return true;
                }
            }

            return false;
        }

        return $campaign->memberships()
            ->where('user_id', (int) $user->id)
            ->where('role', $roleValue)
            ->exists();
    }

    public function isOwnedBy(Campaign $campaign, User $user): bool
    {
        return (int) $campaign->owner_id === (int) $user->id;
    }

    public function isGm(Campaign $campaign, User $user): bool
    {
        return $this->hasMembershipRole($campaign, $user, CampaignMembershipRole::GM);
    }

    public function canManageCampaign(Campaign $campaign, User $user): bool
    {
        return $this->isOwnedBy($campaign, $user) || $this->isGm($campaign, $user);
    }

    public function canModeratePosts(Campaign $campaign, User $user): bool
    {
        return $this->canManageCampaign($campaign, $user);
    }

    public function hasParticipantRole(Campaign $campaign, User $user, string $role): bool
    {
        $membershipRole = $this->mapLegacyRoleToMembershipRole($role);

        return $membershipRole instanceof CampaignMembershipRole
            && $this->hasMembershipRole($campaign, $user, $membershipRole);
    }

    public function userCanPostWithoutModeration(Campaign $campaign, User $user): bool
    {
        if ($this->canModeratePosts($campaign, $user)) {
            return true;
        }

        if ((bool) $user->can_post_without_moderation) {
            return true;
        }

        return $this->hasParticipantRole($campaign, $user, CampaignInvitation::ROLE_TRUSTED_PLAYER);
    }

    /**
     * @return Collection<int, int<1, max>>
     */
    public function participantUserIds(Campaign $campaign): Collection
    {
        $participantUserIds = CampaignMembership::query()
            ->where('campaign_id', (int) $campaign->id)
            ->pluck('user_id');

        return $participantUserIds
            ->merge([(int) $campaign->owner_id])
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, int<1, max>>
     */
    public function moderatableCampaignIdsForWorld(User $user, World $world): Collection
    {
        if ($user->isAdmin()) {
            return Campaign::query()
                ->where('world_id', (int) $world->id)
                ->pluck('id')
                ->map(static fn ($campaignId): int => (int) $campaignId)
                ->filter(static fn (int $campaignId): bool => $campaignId > 0)
                ->unique()
                ->values();
        }

        return Campaign::query()
            ->where('world_id', (int) $world->id)
            ->where(function (Builder $campaignQuery) use ($user): void {
                $campaignQuery
                    ->where('owner_id', (int) $user->id)
                    ->orWhereHas('memberships', function (Builder $membershipQuery) use ($user): void {
                        $membershipQuery
                            ->where('user_id', (int) $user->id)
                            ->where('role', CampaignMembershipRole::GM->value);
                    });
            })
            ->pluck('id')
            ->map(static fn ($campaignId): int => (int) $campaignId)
            ->filter(static fn (int $campaignId): bool => $campaignId > 0)
            ->unique()
            ->values();
    }

    public function hasCoGmAccessInWorld(User $user, World $world): bool
    {
        return $this->moderatableCampaignIdsForWorld($user, $world)->isNotEmpty();
    }

    /**
     * @return list<int>
     */
    public function coGmWorldIds(User $user): array
    {
        /** @var list<int> $worldIds */
        $worldIds = Campaign::query()
            ->whereHas('memberships', function (Builder $membershipQuery) use ($user): void {
                $membershipQuery
                    ->where('user_id', (int) $user->id)
                    ->where('role', CampaignMembershipRole::GM->value);
            })
            ->pluck('world_id')
            ->map(static fn (mixed $worldId): int => (int) $worldId)
            ->filter(static fn (int $worldId): bool => $worldId > 0)
            ->unique()
            ->values()
            ->all();

        return $worldIds;
    }

    public function hasAcceptedCoGmAccessForWorld(User $user, int $worldId): bool
    {
        if ($worldId <= 0) {
            return false;
        }

        return Campaign::query()
            ->where('world_id', $worldId)
            ->whereHas('memberships', function (Builder $membershipQuery) use ($user): void {
                $membershipQuery
                    ->where('user_id', (int) $user->id)
                    ->where('role', CampaignMembershipRole::GM->value);
            })
            ->exists();
    }

    /**
     * @param  Builder<Model>  $campaignQuery
     */
    public function applyParticipantCampaignConstraint(Builder $campaignQuery, User $user): void
    {
        $campaignQuery->where(function (Builder $participantQuery) use ($user): void {
            $participantQuery
                ->where('campaigns.owner_id', (int) $user->id)
                ->orWhereHas('memberships', function (Builder $membershipQuery) use ($user): void {
                    $membershipQuery->where('user_id', (int) $user->id);
                });
        });
    }

    public function hasCampaignContactAccess(Campaign $campaign, User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($this->isOwnedBy($campaign, $user)) {
            return true;
        }

        return $this->hasAcceptedInvitation($campaign, $user);
    }

    public function isCampaignContactGmSide(Campaign $campaign, User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($this->isOwnedBy($campaign, $user)) {
            return true;
        }

        return $this->isGm($campaign, $user);
    }

    public function gmContactManageCampaignPanel(Campaign $campaign, User $user): bool
    {
        if ($this->isOwnedBy($campaign, $user)) {
            return true;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $this->isGm($campaign, $user);
    }

    /**
     * @return Collection<int, int<1, max>>
     */
    public function gmContactCoGmRecipientIds(Campaign $campaign): Collection
    {
        return $campaign->memberships()
            ->where('role', CampaignMembershipRole::GM->value)
            ->pluck('user_id')
            ->map(static fn ($userId): int => (int) $userId)
            ->filter(static fn (int $userId): bool => $userId > 0)
            ->values();
    }

    private function mapLegacyRoleToMembershipRole(string $role): ?CampaignMembershipRole
    {
        return match ($role) {
            CampaignInvitation::ROLE_CO_GM, CampaignMembershipRole::GM->value => CampaignMembershipRole::GM,
            CampaignInvitation::ROLE_TRUSTED_PLAYER, CampaignMembershipRole::TRUSTED_PLAYER->value => CampaignMembershipRole::TRUSTED_PLAYER,
            CampaignInvitation::ROLE_PLAYER, CampaignMembershipRole::PLAYER->value => CampaignMembershipRole::PLAYER,
            default => null,
        };
    }

    private function membershipRoleValue(CampaignMembership $membership): string
    {
        $role = $membership->role;

        if ($role instanceof CampaignMembershipRole) {
            return $role->value;
        }

        return (string) $role;
    }
}
