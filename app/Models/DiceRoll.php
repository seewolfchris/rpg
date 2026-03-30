<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiceRoll extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory> */
    use HasFactory;

    public const MODE_NORMAL = 'normal';

    public const MODE_ADVANTAGE = 'advantage';

    public const MODE_DISADVANTAGE = 'disadvantage';

    public const ALLOWED_MODES = [
        self::MODE_NORMAL,
        self::MODE_ADVANTAGE,
        self::MODE_DISADVANTAGE,
    ];

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'scene_id',
        'post_id',
        'user_id',
        'character_id',
        'roll_mode',
        'modifier',
        'label',
        'probe_attribute_key',
        'probe_target_value',
        'probe_is_success',
        'rolls',
        'kept_roll',
        'total',
        'applied_le_delta',
        'applied_ae_delta',
        'resulting_le_current',
        'resulting_ae_current',
        'is_critical_success',
        'is_critical_failure',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rolls' => 'array',
            'modifier' => 'integer',
            'probe_target_value' => 'integer',
            'probe_is_success' => 'boolean',
            'kept_roll' => 'integer',
            'total' => 'integer',
            'applied_le_delta' => 'integer',
            'applied_ae_delta' => 'integer',
            'resulting_le_current' => 'integer',
            'resulting_ae_current' => 'integer',
            'is_critical_success' => 'boolean',
            'is_critical_failure' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Scene, $this>
     */
    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}
