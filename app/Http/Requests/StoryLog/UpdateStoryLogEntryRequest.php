<?php

namespace App\Http\Requests\StoryLog;

use App\Models\Campaign;
use App\Models\Scene;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateStoryLogEntryRequest extends FormRequest
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
            'sort_order' => $this->filled('sort_order') ? (int) $this->input('sort_order') : null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Campaign|null $campaign */
            $campaign = $this->route('campaign');
            $sceneId = (int) ($this->input('scene_id') ?? 0);

            if (! $campaign instanceof Campaign || $sceneId <= 0) {
                return;
            }

            $sceneBelongsToCampaign = Scene::query()
                ->whereKey($sceneId)
                ->where('campaign_id', (int) $campaign->id)
                ->exists();

            if (! $sceneBelongsToCampaign) {
                $validator->errors()->add('scene_id', 'Die gewählte Szene gehört nicht zu dieser Kampagne.');
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
            'body' => 'Eintragstext',
            'scene_id' => 'Szene',
            'sort_order' => 'Sortierung',
        ];
    }
}
