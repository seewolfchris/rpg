<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorldSpecies extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'world_id',
        'key',
        'label',
        'description',
        'modifiers_json',
        'le_bonus',
        'ae_bonus',
        'is_magic_capable',
        'position',
        'is_active',
        'is_template',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'world_id' => 'integer',
            'modifiers_json' => 'array',
            'le_bonus' => 'integer',
            'ae_bonus' => 'integer',
            'is_magic_capable' => 'boolean',
            'position' => 'integer',
            'is_active' => 'boolean',
            'is_template' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<World, $this>
     */
    public function world(): BelongsTo
    {
        return $this->belongsTo(World::class);
    }
}
