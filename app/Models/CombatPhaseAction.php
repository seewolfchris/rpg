<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CombatPhaseAction extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory> */
    use HasFactory;

    public const TYPE_CHARACTER = 'character';

    public const TYPE_NPC = 'npc';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'combat_phase_id',
        'position',
        'actor_type',
        'actor_character_id',
        'actor_name',
        'actor_snapshot',
        'target_type',
        'target_character_id',
        'target_name',
        'target_snapshot',
        'weapon_name',
        'attack_target_value',
        'attack_roll_mode',
        'attack_modifier',
        'defense_label',
        'defense_target_value',
        'defense_roll_mode',
        'defense_modifier',
        'damage',
        'armor_protection',
        'intent_text',
        'resolution_note',
        'result',
        'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'combat_phase_id' => 'integer',
            'position' => 'integer',
            'actor_character_id' => 'integer',
            'actor_snapshot' => 'array',
            'target_character_id' => 'integer',
            'target_snapshot' => 'array',
            'attack_target_value' => 'integer',
            'attack_modifier' => 'integer',
            'defense_target_value' => 'integer',
            'defense_modifier' => 'integer',
            'damage' => 'integer',
            'armor_protection' => 'integer',
            'result' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CombatPhase, $this>
     */
    public function combatPhase(): BelongsTo
    {
        return $this->belongsTo(CombatPhase::class, 'combat_phase_id');
    }

    /**
     * @return BelongsTo<Character, $this>
     */
    public function actorCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'actor_character_id');
    }

    /**
     * @return BelongsTo<Character, $this>
     */
    public function targetCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'target_character_id');
    }
}
