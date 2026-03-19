<?php

namespace App\Http\Controllers;

use App\Domain\Character\CharacterProgressionService;
use App\Http\Requests\Character\StoreCharacterRequest;
use App\Http\Requests\Character\UpdateCharacterRequest;
use App\Models\Character;
use App\Models\World;
use App\Support\CharacterInventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CharacterController extends Controller
{
    public function __construct(
        private readonly CharacterInventoryService $inventoryService,
        private readonly CharacterProgressionService $progressionService,
    ) {}

    public function index(Request $request): View
    {
        $user = auth()->user();
        $selectedWorldSlug = trim((string) $request->query('world', (string) $request->session()->get('world_slug', World::defaultSlug())));
        $characterStatusOptions = array_keys((array) config('characters.statuses', []));
        $selectedStatus = (string) $request->query('status', 'all');

        if (! in_array($selectedStatus, array_merge(['all'], $characterStatusOptions), true)) {
            $selectedStatus = 'all';
        }

        $worlds = World::query()->active()->ordered()->get(['id', 'name', 'slug']);
        $selectedWorld = $worlds->firstWhere('slug', $selectedWorldSlug) ?? $worlds->first();

        if ($selectedWorld) {
            $request->session()->put('world_slug', $selectedWorld->slug);
        }

        $characters = Character::query()
            ->when($selectedWorld, fn ($query) => $query->where('world_id', $selectedWorld->id))
            ->when($selectedStatus !== 'all', fn ($query) => $query->where('status', $selectedStatus))
            ->when(
                ! $user->isGmOrAdmin(),
                fn ($query) => $query->where('user_id', $user->id)
            )
            ->with(['user', 'world'])
            ->latest()
            ->paginate(12);

        return view('characters.index', [
            'characters' => $characters,
            'worlds' => $worlds,
            'selectedWorld' => $selectedWorld,
            'selectedStatus' => $selectedStatus,
            'characterStatuses' => (array) config('characters.statuses', []),
        ]);
    }

    public function create(Request $request): View
    {
        $worlds = World::query()->active()->ordered()->get(['id', 'name', 'slug']);
        $selectedWorldSlug = trim((string) $request->query('world', (string) $request->session()->get('world_slug', World::defaultSlug())));
        $selectedWorld = $worlds->firstWhere('slug', $selectedWorldSlug) ?? $worlds->first();

        return view('characters.create', compact('worlds', 'selectedWorld'));
    }

    public function store(StoreCharacterRequest $request): RedirectResponse
    {
        $data = array_merge($request->validated(), $request->derivedPools());
        unset($data['avatar'], $data['remove_avatar']);
        $data = $this->backfillLegacyCharacterData($data);
        $data = $this->sanitizePoolState($data);

        $character = new Character($data);
        $character->user_id = auth()->id();

        if ($request->hasFile('avatar')) {
            $character->avatar_path = $request->file('avatar')->store('character-avatars', 'public');
        }

        $character->save();
        $normalizedInventory = $this->inventoryService->normalize($character->inventory ?? []);
        $this->inventoryService->log(
            character: $character,
            actorUserId: (int) auth()->id(),
            source: 'character_sheet_create',
            operations: $this->inventoryService->diff([], $normalizedInventory),
            context: ['character_id' => $character->id],
        );

        return redirect()
            ->route('characters.show', $character)
            ->with('status', 'Charakter erstellt.');
    }

    public function show(Character $character): View
    {
        $this->ensureCanManageCharacter($character);
        $inventoryLogs = $character->inventoryLogs()
            ->with('actor:id,name')
            ->limit(25)
            ->get();
        $progressionEvents = $character->progressionEvents()
            ->with(['actorUser:id,name', 'campaign:id,title', 'scene:id,title'])
            ->limit(20)
            ->get();
        $progressionState = $this->progressionService->describe($character);

        return view('characters.show', compact('character', 'inventoryLogs', 'progressionEvents', 'progressionState'));
    }

    public function edit(Character $character): View
    {
        $this->ensureCanManageCharacter($character);

        $worlds = World::query()->active()->ordered()->get(['id', 'name', 'slug']);

        return view('characters.edit', compact('character', 'worlds'));
    }

    public function update(UpdateCharacterRequest $request, Character $character): RedirectResponse
    {
        $this->ensureCanManageCharacter($character);
        $previousInventory = $this->inventoryService->normalize($character->inventory ?? []);

        $data = array_merge($request->validated(), $request->derivedPools());
        unset($data['avatar'], $data['remove_avatar']);
        $data = $this->backfillLegacyCharacterData($data, $character);
        $data = $this->sanitizePoolState($data, $character);

        $character->fill($data);

        if ($request->boolean('remove_avatar') && $character->avatar_path) {
            Storage::disk('public')->delete($character->avatar_path);
            $character->avatar_path = null;
        }

        if ($request->hasFile('avatar')) {
            if ($character->avatar_path) {
                Storage::disk('public')->delete($character->avatar_path);
            }

            $character->avatar_path = $request->file('avatar')->store('character-avatars', 'public');
        }

        $character->save();
        $nextInventory = $this->inventoryService->normalize($character->inventory ?? []);
        $this->inventoryService->log(
            character: $character,
            actorUserId: (int) auth()->id(),
            source: 'character_sheet_update',
            operations: $this->inventoryService->diff($previousInventory, $nextInventory),
            context: ['character_id' => $character->id],
        );

        return redirect()
            ->route('characters.show', $character)
            ->with('status', 'Charakter aktualisiert.');
    }

    public function inlineUpdate(Request $request, Character $character): View|RedirectResponse
    {
        $this->ensureCanManageCharacter($character);

        $statusOptions = array_keys((array) config('characters.statuses', []));

        $validated = $request->validate([
            'epithet' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in($statusOptions)],
            'bio' => ['required', 'string', 'max:12000'],
            'concept' => ['nullable', 'string', 'max:180'],
            'world_connection' => ['nullable', 'string', 'max:2000'],
            'gm_secret' => ['nullable', 'string', 'max:3000'],
            'gm_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $character->fill($validated);
        $character->save();

        if ($request->header('HX-Request') === 'true') {
            return view('characters.partials.inline-editor', compact('character'));
        }

        return redirect()
            ->route('characters.show', $character)
            ->with('status', 'Charakter-Schnellbearbeitung gespeichert.');
    }

    public function destroy(Character $character): RedirectResponse
    {
        $this->ensureOwnership($character);

        if ($character->avatar_path) {
            Storage::disk('public')->delete($character->avatar_path);
        }

        $character->delete();

        return redirect()
            ->route('characters.index')
            ->with('status', 'Charakter gelöscht.');
    }

    private function ensureOwnership(Character $character): void
    {
        abort_unless($character->user_id === auth()->id(), 403);
    }

    private function ensureCanManageCharacter(Character $character): void
    {
        $user = auth()->user();

        abort_unless(
            $character->user_id === $user->id || $user->isGmOrAdmin(),
            403
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function backfillLegacyCharacterData(array $data, ?Character $character = null): array
    {
        $sheet = (array) config('character_sheet', []);
        $origins = (array) Arr::get($sheet, 'origins', []);
        $speciesOptions = (array) Arr::get($sheet, 'species', []);
        $callingOptions = (array) Arr::get($sheet, 'callings', []);
        $legacyMap = (array) Arr::get($sheet, 'legacy_column_map', []);

        $defaultOrigin = array_key_exists('native_vhaltor', $origins)
            ? 'native_vhaltor'
            : (array_key_first($origins) ?? null);
        $defaultSpecies = array_key_exists('mensch', $speciesOptions)
            ? 'mensch'
            : (array_key_first($speciesOptions) ?? null);
        $defaultCalling = array_key_exists('abenteurer', $callingOptions)
            ? 'abenteurer'
            : (array_key_first($callingOptions) ?? null);

        $data['origin'] = (string) ($data['origin'] ?? $character?->origin ?? $defaultOrigin ?? '');
        $data['species'] = (string) ($data['species'] ?? $character?->species ?? $defaultSpecies ?? '');
        $data['calling'] = (string) ($data['calling'] ?? $character?->calling ?? $defaultCalling ?? '');

        foreach ([
            'calling_custom_name',
            'calling_custom_description',
            'concept',
            'gm_secret',
            'world_connection',
            'gm_note',
            'mu_note',
            'kl_note',
            'in_note',
            'ch_note',
            'ff_note',
            'ge_note',
            'ko_note',
            'kk_note',
        ] as $key) {
            if (! array_key_exists($key, $data) && $character) {
                $data[$key] = $character->{$key};
            }
        }

        foreach ($legacyMap as $legacyColumn => $attributeKey) {
            if (! array_key_exists($attributeKey, $data) || $data[$attributeKey] === null) {
                if ($character && $character->{$attributeKey} !== null) {
                    $data[$attributeKey] = (int) $character->{$attributeKey};
                } elseif ($character && $character->{$legacyColumn} !== null) {
                    $data[$attributeKey] = $this->convertLegacyValueToPercent((int) $character->{$legacyColumn});
                }
            }

            if (array_key_exists($attributeKey, $data) && $data[$attributeKey] !== null) {
                $data[$legacyColumn] = (int) $data[$attributeKey];
            }
        }

        $data['advantages'] = is_array($data['advantages'] ?? null)
            ? array_values($data['advantages'])
            : ($character?->advantages ?? []);
        $data['disadvantages'] = is_array($data['disadvantages'] ?? null)
            ? array_values($data['disadvantages'])
            : ($character?->disadvantages ?? []);
        $data['inventory'] = $this->inventoryService->normalize(
            is_array($data['inventory'] ?? null)
                ? $data['inventory']
                : ($character?->inventory ?? [])
        );
        $data['weapons'] = is_array($data['weapons'] ?? null)
            ? $this->sanitizeWeapons($data['weapons'])
            : $this->sanitizeWeapons($character?->weapons ?? []);
        $data['armors'] = is_array($data['armors'] ?? null)
            ? $this->sanitizeArmors($data['armors'])
            : $this->sanitizeArmors($character?->armors ?? []);

        foreach (['le_max', 'le_current', 'ae_max', 'ae_current'] as $poolKey) {
            if (! array_key_exists($poolKey, $data) && $character) {
                $data[$poolKey] = $character->{$poolKey};
            }
        }

        return $data;
    }

    private function convertLegacyValueToPercent(int $legacyValue): int
    {
        $converted = $legacyValue <= 20
            ? (int) round($legacyValue * 5)
            : $legacyValue;

        return (int) max(30, min(60, $converted));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizePoolState(array $data, ?Character $character = null): array
    {
        foreach (['le', 'ae'] as $prefix) {
            $maxKey = $prefix.'_max';
            $currentKey = $prefix.'_current';

            $maxValue = max(0, (int) ($data[$maxKey] ?? 0));
            $existingCurrent = $character?->{$currentKey};

            $data[$maxKey] = $maxValue;
            $data[$currentKey] = $existingCurrent === null
                ? $maxValue
                : $this->clampInt((int) $existingCurrent, 0, $maxValue);
        }

        return $data;
    }

    private function clampInt(int $value, int $min, int $max): int
    {
        return max($min, min($value, $max));
    }

    /**
     * @return array<int, array{name: string, attack: int, parry: int, damage: int}>
     */
    private function sanitizeWeapons(mixed $weapons): array
    {
        if (! is_array($weapons)) {
            return [];
        }

        $normalized = [];

        foreach ($weapons as $weapon) {
            if (! is_array($weapon)) {
                continue;
            }

            $name = trim((string) ($weapon['name'] ?? ''));
            $damage = $this->normalizeWeaponDamageValue($weapon['damage'] ?? null);
            $attack = (int) ($weapon['attack'] ?? 0);
            $parry = (int) ($weapon['parry'] ?? 0);

            if ($name === '' || $damage <= 0) {
                continue;
            }

            $normalized[] = [
                'name' => $name,
                'attack' => max(0, min(100, $attack)),
                'parry' => max(0, min(100, $parry)),
                'damage' => $damage,
            ];
        }

        return array_values($normalized);
    }

    /**
     * @return array<int, array{name: string, protection: int, equipped: bool}>
     */
    private function sanitizeArmors(mixed $armors): array
    {
        if (! is_array($armors)) {
            return [];
        }

        $normalized = [];

        foreach ($armors as $armor) {
            if (! is_array($armor) && ! is_string($armor)) {
                continue;
            }

            if (is_string($armor)) {
                $name = trim($armor);
                $protection = 0;
                $equipped = false;
            } else {
                $name = trim((string) ($armor['name'] ?? $armor['item'] ?? ''));
                $protection = (int) ($armor['protection'] ?? $armor['rs'] ?? 0);
                $equipped = (bool) ($armor['equipped'] ?? false);
            }

            if ($name === '') {
                continue;
            }

            $normalized[] = [
                'name' => $name,
                'protection' => max(0, min(99, $protection)),
                'equipped' => $equipped,
            ];
        }

        return array_values($normalized);
    }

    private function normalizeWeaponDamageValue(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return max(1, min(999, (int) $value));
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return 0;
        }

        if (preg_match('/^(\d+)\s*[wWdD]\s*(\d+)\s*([+-]\s*\d+)?$/', $raw, $matches) === 1) {
            $count = (int) ($matches[1] ?? 0);
            $faces = (int) ($matches[2] ?? 0);
            $bonus = (int) str_replace(' ', '', (string) ($matches[3] ?? '0'));
            $estimated = (int) round(($count * (($faces + 1) / 2)) + $bonus);

            return max(1, min(999, $estimated));
        }

        if (preg_match('/-?\d+/', $raw, $matches) === 1) {
            return max(1, min(999, (int) $matches[0]));
        }

        return 0;
    }
}
