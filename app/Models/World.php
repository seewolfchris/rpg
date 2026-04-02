<?php

namespace App\Models;

use App\Exceptions\DefaultWorldConfigurationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class World extends Model
{
    /** @use HasFactory<\Database\Factories\WorldFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'tagline',
        'description',
        'is_active',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<Campaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * @return HasMany<Character, $this>
     */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    /**
     * @return HasMany<EncyclopediaCategory, $this>
     */
    public function encyclopediaCategories(): HasMany
    {
        return $this->hasMany(EncyclopediaCategory::class);
    }

    /**
     * @return HasMany<WorldSpecies, $this>
     */
    public function speciesOptions(): HasMany
    {
        return $this->hasMany(WorldSpecies::class);
    }

    /**
     * @return HasMany<WorldCalling, $this>
     */
    public function callingOptions(): HasMany
    {
        return $this->hasMany(WorldCalling::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('position')
            ->orderBy('name');
    }

    public static function defaultSlug(): string
    {
        return (string) config('worlds.default_slug', 'chroniken-der-asche');
    }

    public static function resolveDefault(): self
    {
        return static::resolveConfiguredDefaultOrFail(requireActive: false);
    }

    public static function resolveDefaultId(): int
    {
        return (int) static::resolveConfiguredDefaultOrFail(requireActive: true)->id;
    }

    public static function resolveConfiguredDefaultOrFail(bool $requireActive = true): self
    {
        $defaultSlug = static::defaultSlug();

        if (! Schema::hasTable('worlds')) {
            throw DefaultWorldConfigurationException::worldsTableMissing($defaultSlug);
        }

        $defaultWorld = static::query()
            ->where('slug', $defaultSlug)
            ->first();

        if (! $defaultWorld instanceof self) {
            throw DefaultWorldConfigurationException::worldMissing($defaultSlug);
        }

        if ($requireActive && ! (bool) $defaultWorld->is_active) {
            throw DefaultWorldConfigurationException::worldInactive($defaultSlug);
        }

        return $defaultWorld;
    }
}
