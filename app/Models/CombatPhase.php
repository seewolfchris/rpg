<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CombatPhase extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory> */
    use HasFactory;

    public const STATUS_COLLECTING = 'collecting';

    public const STATUS_RESOLVED = 'resolved';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'campaign_id',
        'scene_id',
        'phase_number',
        'status',
        'started_by',
        'resolved_by',
        'resolved_at',
        'resolution_summary',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'campaign_id' => 'integer',
            'scene_id' => 'integer',
            'phase_number' => 'integer',
            'started_by' => 'integer',
            'resolved_by' => 'integer',
            'resolved_at' => 'datetime',
            'resolution_summary' => 'array',
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
     * @return BelongsTo<Scene, $this>
     */
    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * @return HasMany<CombatPhaseAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(CombatPhaseAction::class, 'combat_phase_id');
    }

    public function isCollecting(): bool
    {
        return (string) $this->status === self::STATUS_COLLECTING;
    }

    public function isResolved(): bool
    {
        return (string) $this->status === self::STATUS_RESOLVED;
    }
}
