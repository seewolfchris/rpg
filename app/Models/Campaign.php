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
        'owner_id',
        'title',
        'slug',
        'summary',
        'lore',
        'is_public',
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
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
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
}
