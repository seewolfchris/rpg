<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EncyclopediaCategory extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'world_id',
        'name',
        'slug',
        'summary',
        'position',
        'is_public',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'world_id' => 'integer',
            'position' => 'integer',
            'is_public' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EncyclopediaCategory $category): void {
            if (! $category->world_id) {
                $category->world_id = max(0, World::resolveDefaultId());
            }
        });
    }

    /**
     * @return BelongsTo<World, $this>
     */
    public function world(): BelongsTo
    {
        return $this->belongsTo(World::class);
    }

    /**
     * @return HasMany<EncyclopediaEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(EncyclopediaEntry::class)
            ->orderBy('position')
            ->orderBy('title');
    }

    /**
     * @return HasMany<EncyclopediaEntry, $this>
     */
    public function encyclopediaEntries(): HasMany
    {
        return $this->hasMany(EncyclopediaEntry::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForWorld(Builder $query, World|int $world): Builder
    {
        $worldId = $world instanceof World ? (int) $world->id : (int) $world;

        return $query->where('world_id', $worldId);
    }
}
