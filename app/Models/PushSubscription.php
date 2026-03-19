<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NotificationChannels\WebPush\PushSubscription as BasePushSubscription;

/**
 * @property int $user_id
 * @property int $world_id
 * @property int|string $subscribable_id
 * @property string $subscribable_type
 * @property string $endpoint
 * @property string|null $public_key
 * @property string|null $auth_token
 * @property string|null $content_encoding
 */
class PushSubscription extends BasePushSubscription
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'subscribable_id',
        'subscribable_type',
        'endpoint',
        'public_key',
        'auth_token',
        'content_encoding',
        'user_id',
        'world_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'world_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $subscription): void {
            if (! $subscription->user_id && $subscription->subscribable_type === (new User)->getMorphClass()) {
                $subscription->user_id = (int) $subscription->subscribable_id;
            }

            if (! $subscription->world_id) {
                $subscription->world_id = World::resolveDefaultId();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function world(): BelongsTo
    {
        return $this->belongsTo(World::class);
    }

    public function scopeForUser(Builder $query, User|int $user): Builder
    {
        $userId = $user instanceof User ? (int) $user->id : (int) $user;

        return $query->where('user_id', $userId);
    }

    public function scopeForWorld(Builder $query, World|int $world): Builder
    {
        $worldId = $world instanceof World ? (int) $world->id : (int) $world;

        return $query->where('world_id', $worldId);
    }
}
