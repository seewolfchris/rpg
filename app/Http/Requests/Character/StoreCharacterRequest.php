<?php

namespace App\Http\Requests\Character;

use Illuminate\Foundation\Http\FormRequest;

class StoreCharacterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'epithet' => ['nullable', 'string', 'max:120'],
            'bio' => ['required', 'string', 'min:20', 'max:5000'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:3072'],
            'strength' => ['required', 'integer', 'between:1,20'],
            'dexterity' => ['required', 'integer', 'between:1,20'],
            'constitution' => ['required', 'integer', 'between:1,20'],
            'intelligence' => ['required', 'integer', 'between:1,20'],
            'wisdom' => ['required', 'integer', 'between:1,20'],
            'charisma' => ['required', 'integer', 'between:1,20'],
        ];
    }
}
