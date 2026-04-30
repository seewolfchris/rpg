<?php

namespace App\Http\Requests\PlayerNote;

use App\Models\Campaign;
use App\Models\Character;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePlayerNoteRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:180'],
            'body' => ['nullable', 'string', 'max:10000'],
            'scene_id' => ['nullable', 'integer', 'exists:scenes,id'],
            'character_id' => ['nullable', 'integer', 'exists:characters,id'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $title = trim((string) $this->input('title', ''));
        $body = trim((string) $this->input('body', ''));

        $this->merge([
            'title' => $title,
            'body' => $body !== '' ? $body : null,
            'scene_id' => $this->filled('scene_id') ? (int) $this->input('scene_id') : null,
            'character_id' => $this->filled('character_id') ? (int) $this->input('character_id') : null,
            'sort_order' => $this->filled('sort_order') ? (int) $this->input('sort_order') : null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Campaign|null $campaign */
            $campaign = $this->route('campaign');
            $sceneId = (int) ($this->input('scene_id') ?? 0);
            $characterId = (int) ($this->input('character_id') ?? 0);
            $user = $this->user();
            $userId = $user instanceof User ? (int) $user->id : 0;

            if ($campaign instanceof Campaign && $sceneId > 0) {
                $sceneBelongsToCampaign = Scene::query()
                    ->whereKey($sceneId)
                    ->where('campaign_id', (int) $campaign->id)
                    ->exists();

                if (! $sceneBelongsToCampaign) {
                    $validator->errors()->add('scene_id', 'Die gewählte Szene gehört nicht zu dieser Kampagne.');
                }
            }

            if ($campaign instanceof Campaign && $characterId > 0 && $userId > 0) {
                $characterIsValid = Character::query()
                    ->whereKey($characterId)
                    ->where('user_id', $userId)
                    ->where('world_id', (int) $campaign->world_id)
                    ->exists();

                if (! $characterIsValid) {
                    $validator->errors()->add('character_id', 'Der gewählte Charakter ist für diese Kampagne nicht zulässig.');
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'Titel',
            'body' => 'Notiztext',
            'scene_id' => 'Szene',
            'character_id' => 'Charakter',
            'sort_order' => 'Sortierung',
        ];
    }
}
