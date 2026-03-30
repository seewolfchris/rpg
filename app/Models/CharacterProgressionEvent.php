<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterProgressionEvent extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory> */
    use HasFactory;

    public const EVENT_XP_MILESTONE = 'xp_milestone';

    public const EVENT_XP_CORRECTION = 'xp_correction';

    public const EVENT_AP_SPEND = 'ap_spend';

    public const EVENT_LEVEL_UP_SYSTEM = 'level_up_system';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'character_id',
        'actor_user_id',
        'campaign_id',
        'scene_id',
        'event_type',
        'xp_delta',
        'level_before',
        'level_after',
        'ap_delta',
        'attribute_deltas',
        'reason',
        'meta',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'xp_delta' => 'integer',
            'level_before' => 'integer',
            'level_after' => 'integer',
            'ap_delta' => 'integer',
            'attribute_deltas' => 'array',
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return BelongsTo<Scene, $this>
     */
    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }
}
