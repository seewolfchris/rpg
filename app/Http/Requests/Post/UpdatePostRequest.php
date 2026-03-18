<?php

namespace App\Http\Requests\Post;

use App\Models\Campaign;
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
            'ic_quote' => ['nullable', 'string', 'max:180'],
            'moderation_status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
            'moderation_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $quote = trim((string) $this->input('ic_quote', ''));

        $this->merge([
            'ic_quote' => $quote !== '' ? $quote : null,
            'moderation_note' => trim((string) $this->input('moderation_note', '')),
        ]);
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
            /** @var Campaign $campaign */
            $campaign = $scene->campaign;
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

            if ($postType !== 'ic' && trim((string) ($this->input('ic_quote') ?? '')) !== '') {
                $validator->errors()->add('ic_quote', 'Ein IC-Zitat ist nur für IC-Beiträge erlaubt.');
            }

            if ($characterId) {
                $user = $this->user();
                $allowedUserIds = [(int) $user?->id];

                if ($user && ($user->isGmOrAdmin() || $campaign->isCoGm($user))) {
                    $allowedUserIds[] = (int) $post->user_id;
                }

                $isAllowed = Character::query()
                    ->whereKey($characterId)
                    ->where('world_id', (int) $campaign->world_id)
                    ->whereIn('user_id', array_unique($allowedUserIds))
                    ->exists();

                if (! $isAllowed) {
                    $validator->errors()->add('character_id', 'Charakter passt nicht zur Welt oder zu den erlaubten Besitzern.');
                }
            }
        });
    }
}
