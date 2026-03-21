<?php

namespace App\Support;

use App\Models\World;
use App\Models\WorldCalling;
use App\Models\WorldSpecies;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class WorldCharacterOptionTemplateService
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function templates(): array
    {
        /** @var array<string, array<string, mixed>> $templates */
        $templates = (array) config('world_character_options.templates', []);

        return $templates;
    }

    /**
     * @return array<string, string>
     */
    public function templateSelectOptions(): array
    {
        $options = [];

        foreach ($this->templates() as $templateKey => $template) {
            $options[$templateKey] = (string) ($template['label'] ?? $templateKey);
        }

        return $options;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function template(string $templateKey): ?array
    {
        $template = $this->templates()[$templateKey] ?? null;

        return is_array($template) ? $template : null;
    }

    public function inferTemplateKeyForWorld(World $world): ?string
    {
        $template = $this->template($world->slug);

        return $template ? $world->slug : null;
    }

    /**
     * @return array{species:int,callings:int}
     */
    public function importTemplate(World $world, string $templateKey): array
    {
        $template = $this->template($templateKey);

        if ($template === null) {
            throw new InvalidArgumentException('Unbekanntes Template: '.$templateKey);
        }

        /** @var array<string, array<string, mixed>> $speciesCatalog */
        $speciesCatalog = (array) config('world_character_options.species', []);
        /** @var array<string, array<string, mixed>> $callingCatalog */
        $callingCatalog = (array) config('world_character_options.callings', []);

        $speciesKeys = array_values(array_filter(
            (array) ($template['species'] ?? []),
            static fn ($value): bool => is_string($value) && $value !== ''
        ));
        $callingKeys = array_values(array_filter(
            (array) ($template['callings'] ?? []),
            static fn ($value): bool => is_string($value) && $value !== ''
        ));

        $speciesCount = 0;
        foreach ($speciesKeys as $index => $speciesKey) {
            $species = $speciesCatalog[$speciesKey] ?? null;
            if (! is_array($species)) {
                continue;
            }

            WorldSpecies::query()->updateOrCreate(
                [
                    'world_id' => (int) $world->id,
                    'key' => $speciesKey,
                ],
                [
                    'label' => (string) ($species['label'] ?? ucfirst($speciesKey)),
                    'description' => (string) ($species['description'] ?? ''),
                    'modifiers_json' => (array) ($species['modifiers'] ?? []),
                    'le_bonus' => (int) ($species['le_bonus'] ?? 0),
                    'ae_bonus' => (int) ($species['ae_bonus'] ?? 0),
                    'is_magic_capable' => (bool) ($species['is_magic_capable'] ?? false),
                    'position' => ($index + 1) * 10,
                    'is_active' => true,
                    'is_template' => true,
                ]
            );

            $speciesCount++;
        }

        $callingsCount = 0;
        foreach ($callingKeys as $index => $callingKey) {
            $calling = $callingCatalog[$callingKey] ?? null;
            if (! is_array($calling)) {
                continue;
            }

            WorldCalling::query()->updateOrCreate(
                [
                    'world_id' => (int) $world->id,
                    'key' => $callingKey,
                ],
                [
                    'label' => (string) ($calling['label'] ?? ucfirst($callingKey)),
                    'description' => (string) ($calling['description'] ?? ''),
                    'minimums_json' => (array) ($calling['minimums'] ?? []),
                    'bonuses_json' => (array) ($calling['bonuses'] ?? []),
                    'is_magic_capable' => (bool) ($calling['is_magic_capable'] ?? false),
                    'is_custom' => (bool) ($calling['is_custom'] ?? false),
                    'position' => ($index + 1) * 10,
                    'is_active' => true,
                    'is_template' => true,
                ]
            );

            $callingsCount++;
        }

        return [
            'species' => $speciesCount,
            'callings' => $callingsCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function catalogSpecies(string $key): array
    {
        $catalog = (array) config('world_character_options.species', []);

        return (array) Arr::get($catalog, $key, []);
    }

    /**
     * @return array<string, mixed>
     */
    public function catalogCalling(string $key): array
    {
        $catalog = (array) config('world_character_options.callings', []);

        return (array) Arr::get($catalog, $key, []);
    }
}
