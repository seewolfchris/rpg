<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
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
                $campaign->world_id = World::resolveDefaultId();
            }
        });
    }

    public function world(): BelongsTo
    {
        return $this->belongsTo(World::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function scenes(): HasMany
    {
        return $this->hasMany(Scene::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(CampaignInvitation::class);
    }

    public function invitedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'campaign_invitations')
            ->withPivot(['invited_by', 'status', 'role', 'accepted_at', 'responded_at', 'created_at']);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isGmOrAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $innerQuery) use ($user): void {
            $innerQuery
                ->where('is_public', true)
                ->orWhere('owner_id', $user->id)
                ->orWhereHas('invitations', function (Builder $invitationQuery) use ($user): void {
                    $invitationQuery
                        ->where('user_id', $user->id)
                        ->where('status', CampaignInvitation::STATUS_ACCEPTED);
                });
        });
    }

    public function scopeForWorld(Builder $query, World|int $world): Builder
    {
        $worldId = $world instanceof World ? (int) $world->id : (int) $world;

        return $query->where('world_id', $worldId);
    }

    public function isVisibleTo(User $user): bool
    {
        if ($this->is_public || $this->owner_id === $user->id || $user->isGmOrAdmin()) {
            return true;
        }

        return $this->hasAcceptedInvitation($user);
    }

    public function hasAcceptedInvitation(User $user): bool
    {
        if ($this->relationLoaded('invitations')) {
            return $this->invitations
                ->contains(
                    fn (CampaignInvitation $invitation): bool => $invitation->user_id === $user->id
                        && $invitation->status === CampaignInvitation::STATUS_ACCEPTED,
                );
        }

        return $this->invitations()
            ->where('user_id', $user->id)
            ->where('status', CampaignInvitation::STATUS_ACCEPTED)
            ->exists();
    }

    public function hasParticipantRole(User $user, string $role): bool
    {
        if ($this->relationLoaded('invitations')) {
            return $this->invitations
                ->contains(
                    fn (CampaignInvitation $invitation): bool => $invitation->user_id === $user->id
                        && $invitation->status === CampaignInvitation::STATUS_ACCEPTED
                        && $invitation->role === $role,
                );
        }

        return $this->invitations()
            ->where('user_id', $user->id)
            ->where('status', CampaignInvitation::STATUS_ACCEPTED)
            ->where('role', $role)
            ->exists();
    }

    public function isCoGm(User $user): bool
    {
        return $this->hasParticipantRole($user, CampaignInvitation::ROLE_CO_GM);
    }

    public function requiresPostModeration(): bool
    {
        return (bool) ($this->is_public || $this->requires_post_moderation);
    }

    public function userCanPostWithoutModeration(User $user): bool
    {
        if ($user->isGmOrAdmin() || $this->owner_id === $user->id || $this->isCoGm($user)) {
            return true;
        }

        if ((bool) $user->can_post_without_moderation) {
            return true;
        }

        return $this->hasParticipantRole($user, CampaignInvitation::ROLE_TRUSTED_PLAYER);
    }
}
