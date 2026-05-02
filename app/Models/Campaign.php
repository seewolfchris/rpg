<?php

namespace App\Models;

use App\Domain\Campaign\CampaignAccess;
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
     * @return HasMany<Handout, $this>
     */
    public function handouts(): HasMany
    {
        return $this->hasMany(Handout::class, 'campaign_id');
    }

    /**
     * @return HasMany<StoryLogEntry, $this>
     */
    public function storyLogEntries(): HasMany
    {
        return $this->hasMany(StoryLogEntry::class, 'campaign_id');
    }

    /**
     * @return HasMany<PlayerNote, $this>
     */
    public function playerNotes(): HasMany
    {
        return $this->hasMany(PlayerNote::class, 'campaign_id');
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
        return app(CampaignAccess::class)->scopeVisibleTo($query, $user);
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
        return app(CampaignAccess::class)->isVisibleTo($this, $user);
    }

    public function hasAcceptedInvitation(User $user): bool
    {
        return app(CampaignAccess::class)->hasAcceptedInvitation($this, $user);
    }

    public function hasMembership(User $user): bool
    {
        return app(CampaignAccess::class)->hasMembership($this, $user);
    }

    public function hasMembershipRole(User $user, CampaignMembershipRole|string $role): bool
    {
        return app(CampaignAccess::class)->hasMembershipRole($this, $user, $role);
    }

    public function isOwnedBy(User $user): bool
    {
        return app(CampaignAccess::class)->isOwnedBy($this, $user);
    }

    public function isGm(User $user): bool
    {
        return app(CampaignAccess::class)->isGm($this, $user);
    }

    public function canManageCampaign(User $user): bool
    {
        return app(CampaignAccess::class)->canManageCampaign($this, $user);
    }

    public function canModeratePosts(User $user): bool
    {
        return app(CampaignAccess::class)->canModeratePosts($this, $user);
    }

    public function hasParticipantRole(User $user, string $role): bool
    {
        return app(CampaignAccess::class)->hasParticipantRole($this, $user, $role);
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
        return app(CampaignAccess::class)->userCanPostWithoutModeration($this, $user);
    }
}
