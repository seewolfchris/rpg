<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Character extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'epithet',
        'bio',
        'avatar_path',
        'origin',
        'species',
        'calling',
        'calling_custom_name',
        'calling_custom_description',
        'concept',
        'gm_secret',
        'world_connection',
        'gm_note',
        'mu',
        'kl',
        'in',
        'ch',
        'ff',
        'ge',
        'ko',
        'kk',
        'mu_note',
        'kl_note',
        'in_note',
        'ch_note',
        'ff_note',
        'ge_note',
        'ko_note',
        'kk_note',
        'advantages',
        'disadvantages',
        'le_max',
        'le_current',
        'ae_max',
        'ae_current',
        'strength',
        'dexterity',
        'constitution',
        'intelligence',
        'wisdom',
        'charisma',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mu' => 'integer',
            'kl' => 'integer',
            'in' => 'integer',
            'ch' => 'integer',
            'ff' => 'integer',
            'ge' => 'integer',
            'ko' => 'integer',
            'kk' => 'integer',
            'mu_note' => 'string',
            'kl_note' => 'string',
            'in_note' => 'string',
            'ch_note' => 'string',
            'ff_note' => 'string',
            'ge_note' => 'string',
            'ko_note' => 'string',
            'kk_note' => 'string',
            'advantages' => 'array',
            'disadvantages' => 'array',
            'le_max' => 'integer',
            'le_current' => 'integer',
            'ae_max' => 'integer',
            'ae_current' => 'integer',
            'strength' => 'integer',
            'dexterity' => 'integer',
            'constitution' => 'integer',
            'intelligence' => 'integer',
            'wisdom' => 'integer',
            'charisma' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function diceRolls(): HasMany
    {
        return $this->hasMany(DiceRoll::class);
    }

    public function avatarUrl(): string
    {
        if ($this->avatar_path) {
            return asset('storage/'.$this->avatar_path);
        }

        return asset('images/character-placeholder.svg');
    }

    protected function effectiveAttributes(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): array => $this->resolveEffectiveAttributes($attributes),
            set: fn (mixed $value, array $attributes): array => $this->resolveBaseAttributesFromEffective($value, $attributes),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, int>
     */
    private function resolveEffectiveAttributes(array $attributes): array
    {
        $keys = $this->attributeKeys();
        $species = Str::lower((string) ($attributes['species'] ?? $this->species ?? ''));
        $speciesModifiers = (array) data_get($this->characterSheet(), 'species.'.$species.'.modifiers', []);

        $effective = [];

        foreach ($keys as $key) {
            $baseValue = $this->resolveBaseAttributeValue($key, $attributes);
            $effective[$key] = $baseValue + (int) ($speciesModifiers[$key] ?? 0);
        }

        return $effective;
    }

    /**
     * @param  mixed  $value
     * @param  array<string, mixed>  $attributes
     * @return array<string, int>
     */
    private function resolveBaseAttributesFromEffective(mixed $value, array $attributes): array
    {
        if (! is_array($value)) {
            return [];
        }

        $keys = $this->attributeKeys();
        $species = Str::lower((string) ($attributes['species'] ?? $this->species ?? ''));
        $speciesModifiers = (array) data_get($this->characterSheet(), 'species.'.$species.'.modifiers', []);
        $legacyMap = $this->legacyColumnMap();

        $normalized = [];

        foreach ($keys as $key) {
            $effectiveValue = (int) Arr::get($value, $key, $this->resolveBaseAttributeValue($key, $attributes));
            $speciesBonus = (int) ($speciesModifiers[$key] ?? 0);
            $baseValue = max(0, min(100, $effectiveValue - $speciesBonus));

            $normalized[$key] = $baseValue;

            $legacyColumn = array_search($key, $legacyMap, true);
            if (is_string($legacyColumn)) {
                $normalized[$legacyColumn] = $baseValue;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveBaseAttributeValue(string $key, array $attributes): int
    {
        if (array_key_exists($key, $attributes) && $attributes[$key] !== null) {
            return (int) $attributes[$key];
        }

        $legacyColumn = array_search($key, $this->legacyColumnMap(), true);
        if (
            is_string($legacyColumn)
            && array_key_exists($legacyColumn, $attributes)
            && $attributes[$legacyColumn] !== null
        ) {
            return $this->convertLegacyValueToPercent((int) $attributes[$legacyColumn]);
        }

        return 40;
    }

    /**
     * @return list<string>
     */
    private function attributeKeys(): array
    {
        return array_keys((array) data_get($this->characterSheet(), 'attributes', []));
    }

    /**
     * @return array<string, string>
     */
    private function legacyColumnMap(): array
    {
        return (array) data_get($this->characterSheet(), 'legacy_column_map', []);
    }

    /**
     * @return array<string, mixed>
     */
    private function characterSheet(): array
    {
        $sheet = config('character_sheet', []);

        return is_array($sheet) ? $sheet : [];
    }

    private function convertLegacyValueToPercent(int $legacyValue): int
    {
        $converted = $legacyValue <= 20
            ? (int) round($legacyValue * 5)
            : $legacyValue;

        return (int) max(30, min(60, $converted));
    }
}
