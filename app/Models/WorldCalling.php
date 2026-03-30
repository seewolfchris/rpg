<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorldCalling extends Model
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
        'minimums_json',
        'bonuses_json',
        'is_magic_capable',
        'is_custom',
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
            'minimums_json' => 'array',
            'bonuses_json' => 'array',
            'is_magic_capable' => 'boolean',
            'is_custom' => 'boolean',
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
