<?php

namespace App\Http\Requests\CampaignGmContact;

use App\Models\Campaign;
use App\Models\Character;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCampaignGmContactThreadRequest extends FormRequest
{
    protected $errorBag = 'gmContactThread';

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
            'subject' => ['required', 'string', 'min:3', 'max:180'],
            'content' => ['required', 'string', 'min:3', 'max:10000'],
            'character_id' => ['nullable', 'integer', 'exists:characters,id'],
            'scene_id' => ['nullable', 'integer', 'exists:scenes,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'subject' => trim((string) $this->input('subject', '')),
            'content' => trim((string) $this->input('content', '')),
            'character_id' => $this->filled('character_id') ? (int) $this->input('character_id') : null,
            'scene_id' => $this->filled('scene_id') ? (int) $this->input('scene_id') : null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Campaign|null $campaign */
            $campaign = $this->route('campaign');
            $user = $this->user();

            if (! $campaign instanceof Campaign || ! $user instanceof User) {
                $validator->errors()->add('campaign', 'Kampagne konnte nicht bestimmt werden.');

                return;
            }

            $sceneId = (int) ($this->input('scene_id') ?? 0);
            if ($sceneId > 0) {
                $sceneInCampaign = Scene::query()
                    ->whereKey($sceneId)
                    ->where('campaign_id', (int) $campaign->id)
                    ->exists();

                if (! $sceneInCampaign) {
                    $validator->errors()->add('scene_id', 'Die gewählte Szene gehört nicht zu dieser Kampagne.');
                }
            }

            $characterId = (int) ($this->input('character_id') ?? 0);
            if ($characterId > 0) {
                /** @var Character|null $character */
                $character = Character::query()
                    ->select(['id', 'user_id', 'world_id'])
                    ->find($characterId);

                if (! $character instanceof Character) {
                    $validator->errors()->add('character_id', 'Der gewählte Charakter existiert nicht.');

                    return;
                }

                if ((int) $character->user_id !== (int) $user->id) {
                    $validator->errors()->add('character_id', 'Du kannst nur eigene Charaktere verknüpfen.');
                }

                if ((int) $character->world_id !== (int) $campaign->world_id) {
                    $validator->errors()->add('character_id', 'Der Charakter gehört nicht zur Welt dieser Kampagne.');
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
            'subject' => 'Betreff',
            'content' => 'Nachricht',
            'character_id' => 'Charakter',
            'scene_id' => 'Szene',
        ];
    }
}
