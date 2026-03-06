<?php

namespace App\Http\Requests\Post;

use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePostRequest extends FormRequest
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
            'moderation_status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
            'moderation_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Post|null $post */
            $post = $this->route('post');

            if (! $post) {
                $validator->errors()->add('post', 'Beitrag konnte nicht gefunden werden.');

                return;
            }

            /** @var Scene $scene */
            $scene = $post->scene;
            $postType = (string) $this->input('post_type');
            $characterId = $this->filled('character_id')
                ? (int) $this->input('character_id')
                : null;

            if ($postType === 'ooc' && ! $scene->allow_ooc && ! $this->user()?->isGmOrAdmin()) {
                $validator->errors()->add('post_type', 'OOC-Beiträge sind in dieser Szene deaktiviert.');
            }

            if ($postType === 'ic' && ! $characterId) {
                $validator->errors()->add('character_id', 'Für IC-Beiträge ist ein Charakter erforderlich.');
            }

            if ($characterId) {
                $allowedUserIds = [(int) $this->user()?->id];

                if ($this->user()?->isGmOrAdmin()) {
                    $allowedUserIds[] = (int) $post->user_id;
                }

                $isAllowed = Character::query()
                    ->whereKey($characterId)
                    ->whereIn('user_id', array_unique($allowedUserIds))
                    ->exists();

                if (! $isAllowed) {
                    $validator->errors()->add('character_id', 'Charakter passt nicht zu den erlaubten Besitzern.');
                }
            }
        });
    }
}
