<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\Auth\ResetPasswordNotification;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var array<string, array<string, bool>>
     */
    public const NOTIFICATION_PREFERENCE_DEFAULTS = [
        'post_moderation' => [
            'database' => true,
            'mail' => false,
            'browser' => false,
        ],
        'scene_new_post' => [
            'database' => true,
            'mail' => false,
            'browser' => false,
        ],
        'campaign_invitation' => [
            'database' => true,
            'mail' => false,
            'browser' => false,
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
        ];
    }

    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    public function ownedCampaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'owner_id');
    }

    public function campaignInvitations(): HasMany
    {
        return $this->hasMany(CampaignInvitation::class);
    }

    public function invitedCampaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'campaign_invitations')
            ->withPivot(['invited_by', 'status', 'role', 'accepted_at', 'responded_at', 'created_at']);
    }

    public function createdScenes(): HasMany
    {
        return $this->hasMany(Scene::class, 'created_by');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function approvedPosts(): HasMany
    {
        return $this->hasMany(Post::class, 'approved_by');
    }

    public function postRevisions(): HasMany
    {
        return $this->hasMany(PostRevision::class, 'editor_id');
    }

    public function pointEvents(): HasMany
    {
        return $this->hasMany(PointEvent::class);
    }

    public function diceRolls(): HasMany
    {
        return $this->hasMany(DiceRoll::class);
    }

    public function sceneSubscriptions(): HasMany
    {
        return $this->hasMany(SceneSubscription::class);
    }

    public function sceneBookmarks(): HasMany
    {
        return $this->hasMany(SceneBookmark::class);
    }

    public function subscribedScenes(): BelongsToMany
    {
        return $this->belongsToMany(Scene::class, 'scene_subscriptions')
            ->withPivot(['is_muted', 'last_read_post_id', 'last_read_at'])
            ->withTimestamps();
    }

    public function hasRole(UserRole|string $role): bool
    {
        $roleValue = $role instanceof UserRole ? $role->value : $role;

        return $this->role?->value === $roleValue;
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

    public function isGmOrAdmin(): bool
    {
        return $this->hasAnyRole(UserRole::GM, UserRole::ADMIN);
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
                if (isset($stored[$kind][$channel])) {
                    $resolved[$kind][$channel] = (bool) $stored[$kind][$channel];
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
