<?php

namespace App\Http\Requests\Character;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

abstract class CharacterSheetRequest extends FormRequest
{
    /**
     * @var array<string, mixed>
     */
    protected array $sheetConfig = [];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function baseRules(): array
    {
        $speciesKeys = array_keys((array) data_get($this->sheet(), 'species', []));
        $callingKeys = array_keys((array) data_get($this->sheet(), 'callings', []));
        $originKeys = array_keys((array) data_get($this->sheet(), 'origins', []));
        $traitMin = (int) data_get($this->sheet(), 'traits.min', 1);
        $traitMax = (int) data_get($this->sheet(), 'traits.max', 3);

        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'epithet' => ['nullable', 'string', 'max:120'],
            'bio' => ['required', 'string', 'min:20', 'max:5000'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:3072'],

            'origin' => ['required', 'string', Rule::in($originKeys)],
            'species' => ['required', 'string', Rule::in($speciesKeys)],
            'calling' => ['required', 'string', Rule::in($callingKeys)],
            'calling_custom_name' => ['nullable', 'string', 'max:120'],
            'calling_custom_description' => ['nullable', 'string', 'max:2000'],

            'concept' => ['nullable', 'string', 'min:8', 'max:180'],
            'gm_secret' => ['nullable', 'string', 'min:10', 'max:3000'],
            'world_connection' => ['nullable', 'string', 'min:10', 'max:2000'],
            'gm_note' => ['nullable', 'string', 'max:2000'],

            'advantages' => ['required', 'array', 'min:'.$traitMin, 'max:'.$traitMax],
            'advantages.*' => ['required', 'string', 'min:2', 'max:120', 'distinct'],
            'disadvantages' => ['required', 'array', 'min:'.$traitMin, 'max:'.$traitMax],
            'disadvantages.*' => ['required', 'string', 'min:2', 'max:120', 'distinct'],

            // Altes 6-Werte-Schema bleibt als technische Persistenz erhalten.
            'strength' => ['sometimes', 'integer', 'between:0,100'],
            'dexterity' => ['sometimes', 'integer', 'between:0,100'],
            'constitution' => ['sometimes', 'integer', 'between:0,100'],
            'intelligence' => ['sometimes', 'integer', 'between:0,100'],
            'wisdom' => ['sometimes', 'integer', 'between:0,100'],
            'charisma' => ['sometimes', 'integer', 'between:0,100'],
        ];

        foreach ($this->attributeKeys() as $key) {
            $rules[$key] = ['required', 'integer', 'between:30,60'];
            $rules[$key.'_note'] = ['nullable', 'string', 'max:800'];
        }

        return $rules;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function extraRules(): array
    {
        return [];
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge($this->baseRules(), $this->extraRules());
    }

    public function validated($key = null, $default = null): mixed
    {
        /** @var array<string, mixed> $validated */
        $validated = parent::validated();
        $withDerived = array_merge($validated, $this->derivedPools());

        if ($key === null) {
            return $withDerived;
        }

        return data_get($withDerived, $key, $default);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateAttributeAverage($validator);
            $this->validateCallingRequirements($validator);
            $this->validateTraitPairing($validator);
            $this->validateCustomCalling($validator);
            $this->validateOriginSpeciesCompatibility($validator);
        });
    }

    protected function prepareForValidation(): void
    {
        $merged = [
            'species' => Str::lower(trim((string) $this->input('species', ''))),
            'calling' => Str::lower(trim((string) $this->input('calling', ''))),
            'origin' => Str::lower(trim((string) $this->input('origin', ''))),
            'advantages' => $this->normalizeTraitInput($this->input('advantages')),
            'disadvantages' => $this->normalizeTraitInput($this->input('disadvantages')),
            'calling_custom_name' => trim((string) $this->input('calling_custom_name', '')),
            'calling_custom_description' => trim((string) $this->input('calling_custom_description', '')),
            'concept' => $this->nullIfEmpty((string) $this->input('concept', '')),
            'gm_secret' => $this->nullIfEmpty((string) $this->input('gm_secret', '')),
            'world_connection' => $this->nullIfEmpty((string) $this->input('world_connection', '')),
            'gm_note' => trim((string) $this->input('gm_note', '')),
        ];

        foreach ($this->attributeKeys() as $key) {
            $merged[$key] = (int) $this->input($key);
            $merged[$key.'_note'] = trim((string) $this->input($key.'_note', ''));
        }

        // Rueckwaertskompatibilitaet: Falls nur alte Werte geliefert werden, in Prozent umrechnen.
        foreach ($this->legacyColumnMap() as $legacyColumn => $attributeKey) {
            $attributeMissing = ! $this->filled($attributeKey);
            $legacyPresent = $this->filled($legacyColumn);

            if ($attributeMissing && $legacyPresent) {
                $merged[$attributeKey] = $this->convertLegacyValueToPercent((int) $this->input($legacyColumn));
            }
        }

        // Persistenz-Mapping fuer alte Spalten.
        foreach ($this->legacyColumnMap() as $legacyColumn => $attributeKey) {
            if (array_key_exists($attributeKey, $merged)) {
                $merged[$legacyColumn] = (int) $merged[$attributeKey];
            }
        }

        $merged = array_merge($merged, $this->resolveDerivedPools($merged));

        $this->merge($merged);
    }

    /**
     * @return array<string, mixed>
     */
    protected function sheet(): array
    {
        if ($this->sheetConfig === []) {
            /** @var array<string, mixed> $config */
            $config = config('character_sheet', []);
            $this->sheetConfig = $config;
        }

        return $this->sheetConfig;
    }

    /**
     * @return list<string>
     */
    protected function attributeKeys(): array
    {
        /** @var list<string> $keys */
        $keys = array_keys((array) data_get($this->sheet(), 'attributes', []));

        return $keys;
    }

    /**
     * @return array<string, string>
     */
    protected function legacyColumnMap(): array
    {
        /** @var array<string, string> $map */
        $map = (array) data_get($this->sheet(), 'legacy_column_map', []);

        return $map;
    }

    /**
     * @param  mixed  $input
     * @return array<int, string>
     */
    protected function normalizeTraitInput(mixed $input): array
    {
        if (is_array($input)) {
            return array_values(array_filter(array_map(
                fn ($value): string => trim((string) $value),
                $input
            ), fn (string $value): bool => $value !== ''));
        }

        if (is_string($input)) {
            $parts = preg_split('/[\r\n,]+/', $input) ?: [];

            return array_values(array_filter(array_map(
                fn (string $value): string => trim($value),
                $parts
            ), fn (string $value): bool => $value !== ''));
        }

        return [];
    }

    protected function nullIfEmpty(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    protected function convertLegacyValueToPercent(int $legacyValue): int
    {
        $converted = $legacyValue <= 20
            ? (int) round($legacyValue * 5)
            : $legacyValue;

        return (int) max(30, min(60, $converted));
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, int>
     */
    protected function resolveAttributes(array $source): array
    {
        $attributes = [];

        foreach ($this->attributeKeys() as $key) {
            $attributes[$key] = (int) ($source[$key] ?? 0);
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, int>
     */
    protected function applySpeciesModifiers(array $source): array
    {
        $attributes = $this->resolveAttributes($source);
        $speciesKey = Str::lower((string) ($source['species'] ?? ''));
        $modifiers = (array) data_get($this->sheet(), 'species.'.$speciesKey.'.modifiers', []);

        foreach ($modifiers as $attributeKey => $delta) {
            if (! array_key_exists($attributeKey, $attributes)) {
                continue;
            }

            $attributes[$attributeKey] += (int) $delta;
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, int>
     */
    protected function calculateDerivedPools(array $source): array
    {
        $effective = $this->applySpeciesModifiers($source);
        $speciesKey = (string) ($source['species'] ?? '');
        $callingKey = (string) ($source['calling'] ?? '');

        $leBase = (int) round((($effective['ko'] ?? 0) + ($effective['kk'] ?? 0) + ($effective['mu'] ?? 0)) / 3);
        $aeBase = (int) round((($effective['kl'] ?? 0) + ($effective['in'] ?? 0) + ($effective['ch'] ?? 0)) / 3);

        $le = $leBase + (int) data_get($this->sheet(), 'species.'.$speciesKey.'.le_bonus', 0);
        $ae = $aeBase + (int) data_get($this->sheet(), 'species.'.$speciesKey.'.ae_bonus', 0);

        $calling = (array) data_get($this->sheet(), 'callings.'.$callingKey, []);
        $callingBonuses = (array) ($calling['bonuses'] ?? []);

        $le += (int) Arr::get($callingBonuses, 'le_flat', 0);
        $ae += (int) Arr::get($callingBonuses, 'ae_flat', 0);

        $aePercent = (int) Arr::get($callingBonuses, 'ae_percent', 0);
        if ($aePercent > 0) {
            $ae += (int) round($aeBase * ($aePercent / 100));
        }

        return [
            'le_max' => max($le, 1),
            'ae_max' => max($ae, 0),
        ];
    }

    /**
     * @return array{le_max: int, le_current: int, ae_max: int, ae_current: int}
     */
    public function derivedPools(): array
    {
        return $this->resolveDerivedPools($this->all());
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array{le_max: int, le_current: int, ae_max: int, ae_current: int}
     */
    protected function resolveDerivedPools(array $source): array
    {
        $derived = $this->calculateDerivedPools($source);

        $leMax = (int) $derived['le_max'];
        $aeMax = (int) $derived['ae_max'];

        $leCurrent = (int) ($source['le_current'] ?? $leMax);
        $aeCurrent = (int) ($source['ae_current'] ?? $aeMax);

        return [
            'le_max' => $leMax,
            'le_current' => max(0, min($leCurrent, $leMax)),
            'ae_max' => $aeMax,
            'ae_current' => max(0, min($aeCurrent, $aeMax)),
        ];
    }

    protected function validateAttributeAverage(Validator $validator): void
    {
        $attributes = $this->resolveAttributes($this->all());
        if ($attributes === []) {
            return;
        }

        $average = array_sum($attributes) / count($attributes);
        $maxAverage = (float) data_get($this->sheet(), 'average_max', 50);

        if ($average > $maxAverage) {
            $validator->errors()->add(
                'mu',
                'Der Durchschnitt aller 8 Grundeigenschaften darf maximal '.$maxAverage.' % betragen.'
            );
        }
    }

    protected function validateCallingRequirements(Validator $validator): void
    {
        $callingKey = (string) $this->input('calling', '');
        if ($callingKey === '' || $callingKey === 'eigene') {
            return;
        }

        $minimums = (array) data_get($this->sheet(), 'callings.'.$callingKey.'.minimums', []);
        if ($minimums === []) {
            return;
        }

        $effectiveAttributes = $this->applySpeciesModifiers($this->all());

        foreach ($minimums as $attributeKey => $minimum) {
            $current = (int) ($effectiveAttributes[$attributeKey] ?? 0);
            $minimumValue = (int) $minimum;

            if ($current < $minimumValue) {
                $label = (string) data_get($this->sheet(), 'attributes.'.$attributeKey.'.label', strtoupper($attributeKey));
                $validator->errors()->add(
                    $attributeKey,
                    'Berufungsvoraussetzung nicht erfuellt: '.$label.' muss mindestens '.$minimumValue.' % betragen.'
                );
            }
        }
    }

    protected function validateTraitPairing(Validator $validator): void
    {
        $advantages = $this->normalizeTraitInput($this->input('advantages'));
        $disadvantages = $this->normalizeTraitInput($this->input('disadvantages'));

        if (count($advantages) !== count($disadvantages)) {
            $validator->errors()->add(
                'advantages',
                'Vorteile und Nachteile muessen 1:1 gepaart sein (gleiche Anzahl).'
            );
        }
    }

    protected function validateCustomCalling(Validator $validator): void
    {
        if ($this->input('calling') !== 'eigene') {
            return;
        }

        if (! $this->filled('calling_custom_name')) {
            $validator->errors()->add(
                'calling_custom_name',
                'Bei Berufung "Eigene" ist ein Name erforderlich.'
            );
        }

        if (! $this->filled('calling_custom_description')) {
            $validator->errors()->add(
                'calling_custom_description',
                'Bei Berufung "Eigene" ist eine kurze Beschreibung erforderlich.'
            );
        }
    }

    protected function validateOriginSpeciesCompatibility(Validator $validator): void
    {
        $origin = (string) $this->input('origin', '');
        $species = (string) $this->input('species', '');
        $allowedSpecies = $this->allowedSpeciesForOrigin($origin);

        if ($allowedSpecies === null || in_array($species, $allowedSpecies, true)) {
            return;
        }

        $originLabel = (string) data_get($this->sheet(), 'origins.'.$origin, $origin);
        $allowedLabels = implode(', ', array_map(
            fn (string $speciesKey): string => (string) data_get($this->sheet(), 'species.'.$speciesKey.'.label', $speciesKey),
            $allowedSpecies,
        ));

        $validator->errors()->add(
            'species',
            'Fuer Herkunft "'.$originLabel.'" sind nur folgende Spezies erlaubt: '.$allowedLabels.'.'
        );
    }

    /**
     * @return list<string>|null
     */
    protected function allowedSpeciesForOrigin(string $origin): ?array
    {
        $constraints = (array) data_get($this->sheet(), 'origin_species_constraints', []);
        $allowed = $constraints[$origin] ?? null;

        if (! is_array($allowed) || $allowed === []) {
            return null;
        }

        /** @var list<string> $normalized */
        $normalized = array_values(array_filter(array_map(
            static fn ($value): string => Str::lower(trim((string) $value)),
            $allowed
        ), static fn (string $value): bool => $value !== ''));

        return $normalized === [] ? null : $normalized;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'gm_secret' => 'Geheimnis (nur GM)',
            'world_connection' => 'Besondere Verbindung zur Welt',
            'gm_note' => 'GM-Notiz',
            'calling_custom_name' => 'Eigene Berufung (Name)',
            'calling_custom_description' => 'Eigene Berufung (Beschreibung)',
        ];

        foreach ((array) data_get($this->sheet(), 'attributes', []) as $key => $meta) {
            $label = (string) ($meta['label'] ?? strtoupper($key));
            $attributes[$key] = $label;
            $attributes[$key.'_note'] = $label.' (narrative Auspraegung)';
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.required' => 'Dieses Feld ist erforderlich.',
            '*.string' => 'Bitte einen gueltigen Text eingeben.',
            '*.integer' => 'Bitte eine ganze Zahl eingeben.',
            '*.array' => 'Dieses Feld hat ein ungueltiges Listenformat.',
            '*.in' => 'Bitte eine gueltige Option auswaehlen.',
            '*.distinct' => 'Doppelte Eintraege sind nicht erlaubt.',
            '*.min.string' => 'Bitte mindestens :min Zeichen eingeben.',
            '*.max.string' => 'Bitte maximal :max Zeichen eingeben.',
            '*.min.array' => 'Bitte mindestens :min Eintrag auswaehlen.',
            '*.max.array' => 'Bitte hoechstens :max Eintraege auswaehlen.',
            '*.between.integer' => 'Der Wert muss zwischen :min und :max liegen.',
            '*.mimes' => 'Bitte eine Datei vom Typ :values hochladen.',
            '*.image' => 'Bitte eine gueltige Bilddatei hochladen.',
        ];
    }
}
