<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EncyclopediaCategory extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
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
            'position' => 'integer',
            'is_public' => 'boolean',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(EncyclopediaEntry::class)
            ->orderBy('position')
            ->orderBy('title');
    }

    public function encyclopediaEntries(): HasMany
    {
        return $this->hasMany(EncyclopediaEntry::class);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }
}
