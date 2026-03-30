<?php

namespace App\Models;

use App\Support\CharacterSheetResolver;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Character extends Model
{
    /** @use HasFactory<\Database\Factories\CharacterFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $resolvedSheetCache = null;

    private ?int $resolvedSheetWorldId = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'world_id',
        'name',
        'epithet',
        'bio',
        'avatar_path',
        'status',
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
        'inventory',
        'weapons',
        'armors',
        'le_max',
        'le_current',
        'ae_max',
        'ae_current',
        'xp_total',
        'level',
        'attribute_points_unspent',
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
            'user_id' => 'integer',
            'world_id' => 'integer',
            'status' => 'string',
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
            'inventory' => 'array',
            'weapons' => 'array',
            'armors' => 'array',
            'le_max' => 'integer',
            'le_current' => 'integer',
            'ae_max' => 'integer',
            'ae_current' => 'integer',
            'xp_total' => 'integer',
            'level' => 'integer',
            'attribute_points_unspent' => 'integer',
            'strength' => 'integer',
            'dexterity' => 'integer',
            'constitution' => 'integer',
            'intelligence' => 'integer',
            'wisdom' => 'integer',
            'charisma' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Character $character): void {
            if (! $character->world_id) {
                $character->world_id = max(0, World::resolveDefaultId());
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * @return HasMany<DiceRoll, $this>
     */
    public function diceRolls(): HasMany
    {
        return $this->hasMany(DiceRoll::class);
    }

    /**
     * @return HasMany<CharacterInventoryLog, $this>
     */
    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(CharacterInventoryLog::class)->latest('created_at');
    }

    /**
     * @return HasMany<CharacterProgressionEvent, $this>
     */
    public function progressionEvents(): HasMany
    {
        return $this->hasMany(CharacterProgressionEvent::class)->latest('created_at');
    }

    public function avatarUrl(): string
    {
        if ($this->avatar_path) {
            return asset('storage/'.$this->avatar_path);
        }

        return asset('images/character-placeholder.svg');
    }

    /**
     * @return Attribute<array<string, int>, array<string, int>>
     */
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
        $worldId = (int) ($this->world_id ?? World::resolveDefaultId());

        if ($this->resolvedSheetCache !== null && $this->resolvedSheetWorldId === $worldId) {
            return $this->resolvedSheetCache;
        }

        /** @var array<string, mixed> $sheet */
        $sheet = app(CharacterSheetResolver::class)->resolveForWorldId($worldId);

        $this->resolvedSheetCache = $sheet;
        $this->resolvedSheetWorldId = $worldId;

        return $sheet;
    }

    private function convertLegacyValueToPercent(int $legacyValue): int
    {
        $converted = $legacyValue <= 20
            ? (int) round($legacyValue * 5)
            : $legacyValue;

        return (int) max(30, min(60, $converted));
    }

    /**
     * @return array<int, array{name: string, protection: int, equipped: bool}>
     */
    public function normalizedArmors(): array
    {
        $entries = is_array($this->armors) ? $this->armors : [];
        $normalized = [];

        foreach ($entries as $entry) {
            if (is_string($entry)) {
                $name = trim($entry);
                $protection = 0;
                $equipped = false;
            } elseif (is_array($entry)) {
                $name = trim((string) ($entry['name'] ?? $entry['item'] ?? ''));
                $protection = max(0, min(99, (int) ($entry['protection'] ?? $entry['rs'] ?? 0)));
                $equipped = (bool) ($entry['equipped'] ?? false);
            } else {
                continue;
            }

            if ($name === '') {
                continue;
            }

            $normalized[] = [
                'name' => $name,
                'protection' => $protection,
                'equipped' => $equipped,
            ];
        }

        return array_values($normalized);
    }

    public function armorProtectionValue(): int
    {
        $armors = $this->normalizedArmors();
        $equipped = array_values(array_filter($armors, static fn (array $armor): bool => (bool) $armor['equipped']));
        $effectiveArmors = $equipped !== [] ? $equipped : $armors;

        return array_sum(array_map(
            static fn (array $armor): int => max(0, (int) ($armor['protection'] ?? 0)),
            $effectiveArmors
        ));
    }
}
