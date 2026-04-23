<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignRoleEvent extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory> */
    use HasFactory;

    public const EVENT_MEMBERSHIP_GRANTED = 'membership_granted';

    public const EVENT_MEMBERSHIP_ROLE_CHANGED = 'membership_role_changed';

    public const EVENT_MEMBERSHIP_REVOKED = 'membership_revoked';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'campaign_id',
        'actor_user_id',
        'target_user_id',
        'event_type',
        'old_role',
        'new_role',
        'source',
        'meta',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'campaign_id' => 'integer',
            'actor_user_id' => 'integer',
            'target_user_id' => 'integer',
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
