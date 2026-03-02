<?php

namespace App\Http\Requests\Dice;

use App\Models\Character;
use App\Models\DiceRoll;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDiceRollRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'dice_character_id' => ['nullable', 'integer', 'exists:characters,id'],
            'dice_roll_mode' => ['required', Rule::in(DiceRoll::ALLOWED_MODES)],
            'dice_modifier' => ['nullable', 'integer', 'between:-30,30'],
            'dice_label' => ['nullable', 'string', 'max:80'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('dice_modifier')) {
            $this->merge(['dice_modifier' => 0]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('dice_character_id')) {
                return;
            }

            $character = Character::query()->find((int) $this->input('dice_character_id'));
            $user = $this->user();

            if (! $character || ! $user) {
                return;
            }

            if ($character->user_id === (int) $user->id || $user->isGmOrAdmin()) {
                return;
            }

            $validator->errors()->add('dice_character_id', 'Du kannst nur eigene Charaktere fuer Wuerfe verwenden.');
        });
    }
}
