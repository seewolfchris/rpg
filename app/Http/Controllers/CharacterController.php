<?php

namespace App\Http\Controllers;

use App\Http\Requests\Character\StoreCharacterRequest;
use App\Http\Requests\Character\UpdateCharacterRequest;
use App\Models\Character;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CharacterController extends Controller
{
    public function index(): View
    {
        $characters = Character::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(12);

        return view('characters.index', compact('characters'));
    }

    public function create(): View
    {
        return view('characters.create');
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

        return redirect()
            ->route('characters.show', $character)
            ->with('status', 'Charakter erstellt.');
    }

    public function show(Character $character): View
    {
        $this->ensureOwnership($character);

        return view('characters.show', compact('character'));
    }

    public function edit(Character $character): View
    {
        $this->ensureOwnership($character);

        return view('characters.edit', compact('character'));
    }

    public function update(UpdateCharacterRequest $request, Character $character): RedirectResponse
    {
        $this->ensureOwnership($character);

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

        return redirect()
            ->route('characters.show', $character)
            ->with('status', 'Charakter aktualisiert.');
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
            ->with('status', 'Charakter geloescht.');
    }

    private function ensureOwnership(Character $character): void
    {
        abort_unless($character->user_id === auth()->id(), 403);
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
        $data['inventory'] = is_array($data['inventory'] ?? null)
            ? $this->sanitizeInventoryEntries($data['inventory'])
            : $this->sanitizeInventoryEntries($character?->inventory ?? []);
        $data['weapons'] = is_array($data['weapons'] ?? null)
            ? $this->sanitizeWeapons($data['weapons'])
            : $this->sanitizeWeapons($character?->weapons ?? []);

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
     * @param  mixed  $entries
     * @return array<int, string>
     */
    private function sanitizeInventoryEntries(mixed $entries): array
    {
        if (! is_array($entries)) {
            return [];
        }

        $normalized = [];

        foreach ($entries as $entry) {
            $value = trim((string) $entry);
            if ($value === '') {
                continue;
            }

            $normalized[] = $value;
        }

        return array_values($normalized);
    }

    /**
     * @param  mixed  $weapons
     * @return array<int, array{name: string, attack: int, parry: int, damage: string}>
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
            $damage = trim((string) ($weapon['damage'] ?? ''));
            $attack = (int) ($weapon['attack'] ?? 0);
            $parry = (int) ($weapon['parry'] ?? 0);

            if ($name === '' || $damage === '') {
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
}
