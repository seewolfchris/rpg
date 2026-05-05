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
use InvalidArgumentException;

/**
 * @phpstan-type CharacterAttributePool array{
 *     key: string,
 *     column: string,
 *     base: int,
 *     max: int,
 *     current: int,
 *     is_reduced: bool,
 *     is_modified: bool
 * }
 */
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
        'mu_current',
        'kl_current',
        'in_current',
        'ch_current',
        'ff_current',
        'ge_current',
        'ko_current',
        'kk_current',
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
            'mu_current' => 'integer',
            'kl_current' => 'integer',
            'in_current' => 'integer',
            'ch_current' => 'integer',
            'ff_current' => 'integer',
            'ge_current' => 'integer',
            'ko_current' => 'integer',
            'kk_current' => 'integer',
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

    /**
     * @return HasMany<PlayerNote, $this>
     */
    public function playerNotes(): HasMany
    {
        return $this->hasMany(PlayerNote::class);
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

    public function currentAttributeColumn(string $key): string
    {
        $this->assertKnownAttributeKey($key);

        return $key.'_current';
    }

    public function effectiveAttributeMax(string $key): int
    {
        $this->assertKnownAttributeKey($key);
        $effective = (array) ($this->effective_attributes ?? []);
        $fallback = $this->resolveBaseAttributeValue($key, $this->getAttributes());

        return max(0, (int) ($effective[$key] ?? $fallback));
    }

    public function currentAttributeValue(string $key): int
    {
        $column = $this->currentAttributeColumn($key);
        $max = $this->effectiveAttributeMax($key);
        $stored = $this->getAttributeValue($column);

        if ($stored === null) {
            return $max;
        }

        return max(0, min((int) $stored, $max));
    }

    /**
     * @return CharacterAttributePool
     */
    public function attributePool(string $key): array
    {
        $this->assertKnownAttributeKey($key);

        $base = $this->resolveBaseAttributeValue($key, $this->getAttributes());
        $max = $this->effectiveAttributeMax($key);
        $current = $this->currentAttributeValue($key);

        return [
            'key' => $key,
            'column' => $this->currentAttributeColumn($key),
            'base' => $base,
            'max' => $max,
            'current' => $current,
            'is_reduced' => $current < $max,
            'is_modified' => $max !== $base,
        ];
    }

    /**
     * @return array<string, CharacterAttributePool>
     */
    public function attributePools(): array
    {
        $pools = [];

        foreach ($this->attributeKeys() as $key) {
            $pools[$key] = $this->attributePool($key);
        }

        return $pools;
    }

    /**
     * @return array<int, array{name: string, protection: int, equipped: bool}>
     */
    public function normalizedArmors(): array
    {
        $entries = $this->armors;
        if (! is_array($entries)) {
            return [];
        }

        $normalized = [];

        foreach ($entries as $entry) {
            $normalizedEntry = $this->normalizeArmorEntry($entry);
            if ($normalizedEntry === null) {
                continue;
            }

            $normalized[] = $normalizedEntry;
        }

        return $normalized;
    }

    public function armorProtectionValue(): int
    {
        $armors = $this->normalizedArmors();
        $equipped = array_filter($armors, static fn (array $armor): bool => $armor['equipped']);
        $effectiveArmors = $equipped !== [] ? $equipped : $armors;
        $protection = 0;

        foreach ($effectiveArmors as $armor) {
            $protection += max(0, (int) $armor['protection']);
        }

        return $protection;
    }

    /**
     * @return array{name: string, protection: int, equipped: bool}|null
     */
    private function normalizeArmorEntry(mixed $entry): ?array
    {
        if (is_string($entry)) {
            $name = trim($entry);
            if ($name === '') {
                return null;
            }

            return [
                'name' => $name,
                'protection' => 0,
                'equipped' => false,
            ];
        }

        if (! is_array($entry)) {
            return null;
        }

        $name = trim((string) Arr::get($entry, 'name', Arr::get($entry, 'item', '')));
        if ($name === '') {
            return null;
        }

        $protection = (int) Arr::get($entry, 'protection', Arr::get($entry, 'rs', 0));

        return [
            'name' => $name,
            'protection' => max(0, min(99, $protection)),
            'equipped' => (bool) Arr::get($entry, 'equipped', false),
        ];
    }

    private function assertKnownAttributeKey(string $key): void
    {
        if (! in_array($key, $this->attributeKeys(), true)) {
            throw new InvalidArgumentException('Unknown character attribute key: '.$key);
        }
    }
}
