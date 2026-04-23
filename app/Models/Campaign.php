<?php

namespace App\Models;

use App\Enums\CampaignMembershipRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    /** @use HasFactory<\Database\Factories\CampaignFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'world_id',
        'owner_id',
        'title',
        'slug',
        'summary',
        'lore',
        'is_public',
        'requires_post_moderation',
        'status',
        'starts_at',
        'ends_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'requires_post_moderation' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Campaign $campaign): void {
            if (! $campaign->world_id) {
                $campaign->world_id = max(0, World::resolveDefaultId());
            }
        });
    }

    /**
     * @return BelongsTo<World, $this>
     */
    public function world(): BelongsTo
    {
        return $this->belongsTo(World::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<Scene, $this>
     */
    public function scenes(): HasMany
    {
        return $this->hasMany(Scene::class);
    }

    /**
     * @return HasMany<CampaignGmContactThread, $this>
     */
    public function gmContactThreads(): HasMany
    {
        return $this->hasMany(CampaignGmContactThread::class, 'campaign_id');
    }

    /**
     * @return HasMany<CampaignMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(CampaignMembership::class, 'campaign_id');
    }

    /**
     * @return HasMany<CampaignInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(CampaignInvitation::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function invitedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'campaign_invitations')
            ->withPivot(['invited_by', 'status', 'role', 'accepted_at', 'responded_at', 'created_at']);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $innerQuery) use ($user): void {
            $innerQuery
                ->where('is_public', true)
                ->orWhere('owner_id', $user->id)
                ->orWhereHas('memberships', function (Builder $membershipQuery) use ($user): void {
                    $membershipQuery->where('user_id', $user->id);
                })
                ->orWhereHas('invitations', function (Builder $invitationQuery) use ($user): void {
                    // Transitional fallback until invitation-only legacy rows are fully backfilled.
                    $invitationQuery
                        ->where('user_id', $user->id)
                        ->where('status', CampaignInvitation::STATUS_ACCEPTED);
                });
        });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForWorld(Builder $query, World|int $world): Builder
    {
        $worldId = $world instanceof World ? (int) $world->id : (int) $world;

        return $query->where('world_id', $worldId);
    }

    public function isVisibleTo(User $user): bool
    {
        if ($this->is_public || $this->isOwnedBy($user)) {
            return true;
        }

        return $this->hasMembership($user) || $this->hasLegacyAcceptedInvitation($user);
    }

    public function hasAcceptedInvitation(User $user): bool
    {
        if ($this->hasMembership($user)) {
            return true;
        }

        return $this->hasLegacyAcceptedInvitation($user);
    }

    public function hasMembership(User $user): bool
    {
        if ($this->relationLoaded('memberships')) {
            foreach ($this->memberships as $membership) {
                if (! $membership instanceof CampaignMembership) {
                    continue;
                }

                if ((int) $membership->user_id === (int) $user->id) {
                    return true;
                }
            }

            return false;
        }

        return $this->memberships()
            ->where('user_id', (int) $user->id)
            ->exists();
    }

    public function hasMembershipRole(User $user, CampaignMembershipRole|string $role): bool
    {
        $roleValue = $role instanceof CampaignMembershipRole ? $role->value : $role;

        if ($this->relationLoaded('memberships')) {
            foreach ($this->memberships as $membership) {
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

        return $this->memberships()
            ->where('user_id', (int) $user->id)
            ->where('role', $roleValue)
            ->exists();
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->owner_id === (int) $user->id;
    }

    public function isGm(User $user): bool
    {
        if ($this->hasMembershipRole($user, CampaignMembershipRole::GM)) {
            return true;
        }

        // Transitional fallback until invitation-only legacy rows are fully backfilled.
        return $this->hasLegacyInvitationRole($user, CampaignInvitation::ROLE_CO_GM);
    }

    public function canManageCampaign(User $user): bool
    {
        return $this->isOwnedBy($user) || $this->isGm($user);
    }

    public function canModeratePosts(User $user): bool
    {
        return $this->canManageCampaign($user);
    }

    private function hasLegacyAcceptedInvitation(User $user): bool
    {
        if ($this->relationLoaded('invitations')) {
            foreach ($this->invitations as $invitation) {
                if (! $invitation instanceof CampaignInvitation) {
                    continue;
                }

                if (
                    $invitation->user_id === $user->id
                    && $invitation->status === CampaignInvitation::STATUS_ACCEPTED
                ) {
                    return true;
                }
            }

            return false;
        }

        return $this->invitations()
            ->where('user_id', $user->id)
            ->where('status', CampaignInvitation::STATUS_ACCEPTED)
            ->exists();
    }

    public function hasParticipantRole(User $user, string $role): bool
    {
        $membershipRole = $this->mapLegacyRoleToMembershipRole($role);

        if ($membershipRole instanceof CampaignMembershipRole && $this->hasMembershipRole($user, $membershipRole)) {
            return true;
        }

        // Transitional fallback until invitation-only legacy rows are fully backfilled.
        return $this->hasLegacyInvitationRole($user, $role);
    }

    public function isCoGm(User $user): bool
    {
        return $this->isGm($user);
    }

    public function requiresPostModeration(): bool
    {
        return (bool) ($this->is_public || $this->requires_post_moderation);
    }

    public function userCanPostWithoutModeration(User $user): bool
    {
        if ($this->canModeratePosts($user)) {
            return true;
        }

        if ((bool) $user->can_post_without_moderation) {
            return true;
        }

        return $this->hasParticipantRole($user, CampaignInvitation::ROLE_TRUSTED_PLAYER);
    }

    private function hasLegacyInvitationRole(User $user, string $role): bool
    {
        if ($this->relationLoaded('invitations')) {
            foreach ($this->invitations as $invitation) {
                if (! $invitation instanceof CampaignInvitation) {
                    continue;
                }

                if (
                    (int) $invitation->user_id === (int) $user->id
                    && $invitation->status === CampaignInvitation::STATUS_ACCEPTED
                    && $invitation->role === $role
                ) {
                    return true;
                }
            }

            return false;
        }

        return $this->invitations()
            ->where('user_id', (int) $user->id)
            ->where('status', CampaignInvitation::STATUS_ACCEPTED)
            ->where('role', $role)
            ->exists();
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

    private function membershipRoleValue(CampaignMembership $membership): ?string
    {
        $role = $membership->role;

        if ($role instanceof CampaignMembershipRole) {
            return $role->value;
        }

        return is_string($role) ? $role : null;
    }
}
