<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        $defaultSlug = static::defaultSlug();

        return static::query()->firstOrCreate(
            ['slug' => $defaultSlug],
            [
                'name' => 'Chroniken der Asche',
                'tagline' => 'Duestere Fantasy in den Aschelanden.',
                'description' => 'Die Standardwelt fuer bestehende Kampagnen und Inhalte.',
                'is_active' => true,
                'position' => 10,
            ],
        );
    }

    public static function resolveDefaultId(): int
    {
        $existingId = static::query()
            ->where('slug', static::defaultSlug())
            ->value('id');

        if ($existingId !== null) {
            return (int) $existingId;
        }

        return (int) static::resolveDefault()->id;
    }
}
