<?php

namespace App\Http\Requests\Character;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SpendCharacterAttributePointsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'attribute_allocations' => ['required', 'array'],
            'attribute_allocations.*' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $allocations = $this->input('attribute_allocations');

        if (! is_array($allocations)) {
            $allocations = [];
        }

        $normalized = [];
        foreach ($allocations as $key => $value) {
            $attributeKey = trim((string) $key);
            if ($attributeKey === '') {
                continue;
            }

            $normalized[$attributeKey] = is_numeric($value) ? (int) $value : 0;
        }

        $this->merge([
            'attribute_allocations' => $normalized,
            'note' => trim((string) $this->input('note', '')),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allocations = $this->input('attribute_allocations', []);
            if (! is_array($allocations)) {
                $validator->errors()->add('attribute_allocations', 'Ungültiges Attribut-Format.');

                return;
            }

            $attributeKeys = array_keys((array) config('character_sheet.attributes', []));
            $hasPositiveValue = false;

            foreach ($allocations as $key => $value) {
                if (! in_array((string) $key, $attributeKeys, true)) {
                    $validator->errors()->add('attribute_allocations.'.$key, 'Ungültiges Attribut.');

                    continue;
                }

                if ((int) $value > 0) {
                    $hasPositiveValue = true;
                }
            }

            if (! $hasPositiveValue) {
                $validator->errors()->add('attribute_allocations', 'Bitte mindestens einen Punkt auf ein Attribut verteilen.');
            }
        });
    }
}
