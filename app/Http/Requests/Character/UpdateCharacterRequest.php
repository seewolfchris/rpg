<?php

namespace App\Http\Requests\Character;

use App\Models\Character;
use Illuminate\Contracts\Validation\Validator;

class UpdateCharacterRequest extends CharacterSheetRequest
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function extraRules(): array
    {
        return [
            'remove_avatar' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator): void {
            /** @var Character|null $character */
            $character = $this->route('character');
            if (! $character) {
                return;
            }

            foreach ($this->attributeKeys() as $attributeKey) {
                $incomingValue = (int) $this->input($attributeKey);
                $existingValue = $this->resolveExistingAttributeValue($character, $attributeKey);

                if ($incomingValue !== $existingValue) {
                    $validator->errors()->add(
                        $attributeKey,
                        'Grundeigenschaften können nach der Erstellung nur über Stufenaufstieg erhöht werden.'
                    );
                }
            }
        });
    }

    /**
     * @return list<string>
     */
    protected function allowedSpeciesKeys(): array
    {
        $keys = parent::allowedSpeciesKeys();
        $character = $this->route('character');

        if (! $character instanceof Character) {
            return $keys;
        }

        $incomingWorldId = (int) $this->input('world_id', (int) $character->world_id);

        if ($incomingWorldId === (int) $character->world_id) {
            $existingSpecies = (string) $character->species;

            if ($existingSpecies !== '' && ! in_array($existingSpecies, $keys, true)) {
                $keys[] = $existingSpecies;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return list<string>
     */
    protected function allowedCallingKeys(): array
    {
        $keys = parent::allowedCallingKeys();
        $character = $this->route('character');

        if (! $character instanceof Character) {
            return $keys;
        }

        $incomingWorldId = (int) $this->input('world_id', (int) $character->world_id);

        if ($incomingWorldId === (int) $character->world_id) {
            $existingCalling = (string) $character->calling;

            if ($existingCalling !== '' && ! in_array($existingCalling, $keys, true)) {
                $keys[] = $existingCalling;
            }
        }

        return array_values(array_unique($keys));
    }

    private function resolveExistingAttributeValue(Character $character, string $attributeKey): int
    {
        $current = $character->{$attributeKey};
        if ($current !== null) {
            return (int) $current;
        }

        $legacyColumn = array_search($attributeKey, $this->legacyColumnMap(), true);
        if (is_string($legacyColumn) && $legacyColumn !== '' && $character->{$legacyColumn} !== null) {
            return $this->convertLegacyValueToPercent((int) $character->{$legacyColumn});
        }

        return 40;
    }
}
