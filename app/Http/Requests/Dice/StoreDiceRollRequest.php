<?php

namespace App\Http\Requests\Dice;

use App\Models\Character;
use App\Models\DiceRoll;
use App\Models\Scene;
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
        /** @var Scene|null $scene */
        $scene = $this->route('scene');
        $user = $this->user();

        if (! $scene || ! $user) {
            return false;
        }

        return $user->isGmOrAdmin() || $scene->campaign->isCoGm($user);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'dice_character_id' => ['required', 'integer', 'exists:characters,id'],
            'dice_roll_mode' => ['required', Rule::in(DiceRoll::ALLOWED_MODES)],
            'dice_modifier' => ['nullable', 'integer', 'between:-30,30'],
            'dice_label' => ['required', 'string', 'min:3', 'max:80'],
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
            $character = Character::query()->find((int) $this->input('dice_character_id'));
            if (! $character) {
                $validator->errors()->add('dice_character_id', 'Der Ziel-Held konnte nicht gefunden werden.');
            }
        });
    }
}
