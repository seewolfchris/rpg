<?php

namespace App\Http\Requests\WorldCharacterOptions;

use Illuminate\Validation\Rule;

class StoreWorldCallingOptionRequest extends WorldCharacterOptionsRequest
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
                Rule::unique('world_callings', 'key')->where(
                    fn ($query) => $query->where('world_id', $this->worldId())
                ),
            ],
            'label' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'minimums_json' => ['nullable', 'json'],
            'bonuses_json' => ['nullable', 'json'],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_magic_capable' => ['sometimes', 'boolean'],
            'is_custom' => ['sometimes', 'boolean'],
            'is_template' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
