<?php

namespace App\Http\Requests\WorldCharacterOptions;

use Illuminate\Validation\Rule;

class StoreWorldSpeciesOptionRequest extends WorldCharacterOptionsRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'key' => [
                'required',
                'string',
                'alpha_dash',
                'max:80',
                Rule::unique('world_species', 'key')->where(
                    fn ($query) => $query->where('world_id', $this->worldId())
                ),
            ],
            'label' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'modifiers_json' => ['nullable', 'json'],
            'le_bonus' => ['nullable', 'integer', 'between:-50,50'],
            'ae_bonus' => ['nullable', 'integer', 'between:-50,50'],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_magic_capable' => ['sometimes', 'boolean'],
            'is_template' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
