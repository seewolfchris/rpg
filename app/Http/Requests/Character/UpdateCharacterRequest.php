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
