<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SceneConflictActor extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory> */
    use HasFactory;

    public const TYPE_CHARACTER = 'character';

    public const TYPE_NPC = 'npc';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'campaign_id',
        'scene_id',
        'actor_type',
        'character_id',
        'name',
        'le_current',
        'le_max',
        'ae_current',
        'ae_max',
        'attack_value',
        'defense_value',
        'armor_protection',
        'damage_value',
        'spell_value',
        'notes',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'campaign_id' => 'integer',
            'scene_id' => 'integer',
            'character_id' => 'integer',
            'le_current' => 'integer',
            'le_max' => 'integer',
            'ae_current' => 'integer',
            'ae_max' => 'integer',
            'attack_value' => 'integer',
            'defense_value' => 'integer',
            'armor_protection' => 'integer',
            'damage_value' => 'integer',
            'spell_value' => 'integer',
            'sort_order' => 'integer',
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
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function displayName(): string
    {
        if ($this->character instanceof Character) {
            $name = trim((string) $this->character->name);
            if ($name !== '') {
                return $name;
            }
        }

        $name = trim((string) $this->name);

        return $name !== '' ? $name : 'Unbekannt';
    }

    public function isCharacter(): bool
    {
        return (string) $this->actor_type === self::TYPE_CHARACTER;
    }

    public function isNpc(): bool
    {
        return (string) $this->actor_type === self::TYPE_NPC;
    }
}

