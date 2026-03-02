<?php

namespace App\Http\Requests\Post;

use App\Models\Character;
use App\Models\Scene;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePostRequest extends FormRequest
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
            'post_type' => ['required', Rule::in(['ic', 'ooc'])],
            'character_id' => ['nullable', 'integer', 'exists:characters,id'],
            'content_format' => ['required', Rule::in(['markdown', 'bbcode', 'plain'])],
            'content' => ['required', 'string', 'min:5', 'max:10000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Scene|null $scene */
            $scene = $this->route('scene');

            if (! $scene) {
                $validator->errors()->add('scene', 'Szene konnte nicht gefunden werden.');

                return;
            }

            $postType = (string) $this->input('post_type');
            $characterId = $this->filled('character_id')
                ? (int) $this->input('character_id')
                : null;

            if ($postType === 'ooc' && ! $scene->allow_ooc) {
                $validator->errors()->add('post_type', 'OOC-Beitraege sind in dieser Szene deaktiviert.');
            }

            if ($postType === 'ic' && ! $characterId) {
                $validator->errors()->add('character_id', 'Fuer IC-Beitraege ist ein Charakter erforderlich.');
            }

            if ($characterId) {
                $character = Character::query()->find($characterId);

                if ($character && $character->user_id !== (int) $this->user()?->id) {
                    $validator->errors()->add('character_id', 'Du kannst nur eigene Charaktere verwenden.');
                }
            }
        });
    }
}
