<?php

namespace App\Models;

use App\Enums\CampaignMembershipRole;
use App\Enums\UserRole;
use App\Notifications\Auth\ResetPasswordNotification;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasPushSubscriptions, Notifiable;

    /**
     * @var array<string, array<string, bool>>
     */
    public const NOTIFICATION_PREFERENCE_DEFAULTS = [
        'post_moderation' => [
            'database' => true,
            'mail' => false,
            'browser' => true,
        ],
        'scene_new_post' => [
            'database' => true,
            'mail' => false,
            'browser' => false,
        ],
        'campaign_invitation' => [
            'database' => true,
            'mail' => false,
            'browser' => true,
        ],
        'character_mention' => [
            'database' => true,
            'mail' => false,
        ],
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'can_post_without_moderation',
        'can_create_campaigns',
        'offline_queue_enabled',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'points' => 'integer',
            'notification_preferences' => 'array',
            'can_post_without_moderation' => 'boolean',
            'can_create_campaigns' => 'boolean',
            'offline_queue_enabled' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Character, $this>
     */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    /**
     * @return HasMany<Campaign, $this>
     */
    public function ownedCampaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'owner_id');
    }

    /**
     * @return HasMany<CampaignInvitation, $this>
     */
    public function campaignInvitations(): HasMany
    {
        return $this->hasMany(CampaignInvitation::class);
    }

    /**
     * @return HasMany<CampaignMembership, $this>
     */
    public function campaignMemberships(): HasMany
    {
        return $this->hasMany(CampaignMembership::class);
    }

    /**
     * @return BelongsToMany<Campaign, $this>
     */
    public function invitedCampaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'campaign_invitations')
            ->withPivot(['invited_by', 'status', 'role', 'accepted_at', 'responded_at', 'created_at']);
    }

    /**
     * @return HasMany<Scene, $this>
     */
    public function createdScenes(): HasMany
    {
        return $this->hasMany(Scene::class, 'created_by');
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * @return HasMany<CampaignGmContactThread, $this>
     */
    public function createdGmContactThreads(): HasMany
    {
        return $this->hasMany(CampaignGmContactThread::class, 'created_by');
    }

    /**
     * @return HasMany<CampaignGmContactMessage, $this>
     */
    public function campaignGmContactMessages(): HasMany
    {
        return $this->hasMany(CampaignGmContactMessage::class);
    }

    /**
     * @return HasMany<PostReaction, $this>
     */
    public function postReactions(): HasMany
    {
        return $this->hasMany(PostReaction::class);
    }

    /**
     * @return HasMany<PostMention, $this>
     */
    public function receivedPostMentions(): HasMany
    {
        return $this->hasMany(PostMention::class, 'mentioned_user_id');
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function approvedPosts(): HasMany
    {
        return $this->hasMany(Post::class, 'approved_by');
    }

    /**
     * @return HasMany<PostRevision, $this>
     */
    public function postRevisions(): HasMany
    {
        return $this->hasMany(PostRevision::class, 'editor_id');
    }

    /**
     * @return HasMany<PointEvent, $this>
     */
    public function pointEvents(): HasMany
    {
        return $this->hasMany(PointEvent::class);
    }

    /**
     * @return HasMany<DiceRoll, $this>
     */
    public function diceRolls(): HasMany
    {
        return $this->hasMany(DiceRoll::class);
    }

    /**
     * @return HasMany<SceneSubscription, $this>
     */
    public function sceneSubscriptions(): HasMany
    {
        return $this->hasMany(SceneSubscription::class);
    }

    /**
     * @return HasMany<SceneBookmark, $this>
     */
    public function sceneBookmarks(): HasMany
    {
        return $this->hasMany(SceneBookmark::class);
    }

    /**
     * @return HasMany<PlayerNote, $this>
     */
    public function playerNotes(): HasMany
    {
        return $this->hasMany(PlayerNote::class);
    }

    /**
     * @return BelongsToMany<Scene, $this>
     */
    public function subscribedScenes(): BelongsToMany
    {
        return $this->belongsToMany(Scene::class, 'scene_subscriptions')
            ->withPivot(['is_muted', 'last_read_post_id', 'last_read_at'])
            ->withTimestamps();
    }

    /**
     * @return MorphMany<\NotificationChannels\WebPush\PushSubscription, $this>
     */
    public function pushSubscriptionsForWorld(World|int $world): MorphMany
    {
        $worldId = $world instanceof World ? (int) $world->id : (int) $world;

        return $this->pushSubscriptions()->where('world_id', $worldId);
    }

    /**
     * @return Collection<int, PushSubscription>
     */
    public function routeNotificationForWebPush(?Notification $notification = null): Collection
    {
        $query = $this->pushSubscriptions();

        if ($notification && method_exists($notification, 'worldId')) {
            /** @var mixed $resolvedWorldId */
            $resolvedWorldId = $notification->worldId();
            $worldId = (int) $resolvedWorldId;

            if ($worldId > 0) {
                $query->where('world_id', $worldId);
            }
        }

        /** @var Collection<int, PushSubscription> $subscriptions */
        $subscriptions = $query->get();

        return $subscriptions;
    }

    public function hasRole(UserRole|string $role): bool
    {
        $roleValue = $role instanceof UserRole ? $role->value : $role;
        $currentRole = $this->role;
        $currentRoleValue = $currentRole instanceof UserRole
            ? $currentRole->value
            : (is_string($currentRole) ? $currentRole : null);

        return $currentRoleValue === $roleValue;
    }

    public function hasAnyRole(UserRole|string ...$roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function offlineQueueEnabled(): bool
    {
        $value = $this->offline_queue_enabled;

        if ($value === null) {
            return true;
        }

        return (bool) $value;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::ADMIN);
    }

    public function isGmOrAdmin(): bool
    {
        return $this->isAdmin();
    }

    public function hasAnyCoGmCampaignAccess(): bool
    {
        if ($this->ownedCampaigns()->exists()) {
            return true;
        }

        if ($this->campaignMemberships()
            ->where('role', CampaignMembershipRole::GM->value)
            ->exists()
        ) {
            return true;
        }

        return false;
    }

    public function canPostWithoutModeration(): bool
    {
        return (bool) $this->can_post_without_moderation;
    }

    public function canCreateCampaigns(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return (bool) $this->can_create_campaigns;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public function resolvedNotificationPreferences(): array
    {
        $stored = is_array($this->notification_preferences)
            ? $this->notification_preferences
            : [];

        $resolved = self::NOTIFICATION_PREFERENCE_DEFAULTS;

        foreach ($resolved as $kind => $channels) {
            foreach (array_keys($channels) as $channel) {
                $storedValue = data_get($stored, $kind.'.'.$channel);

                if ($storedValue !== null) {
                    $resolved[$kind][$channel] = (bool) $storedValue;
                }
            }
        }

        return $resolved;
    }

    public function wantsNotificationChannel(string $kind, string $channel): bool
    {
        $preferences = $this->resolvedNotificationPreferences();

        return (bool) data_get($preferences, $kind.'.'.$channel, false);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
