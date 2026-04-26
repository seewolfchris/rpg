<?php

namespace App\Http\Requests\Handout;

use App\Models\Campaign;
use App\Models\Scene;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreHandoutRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'scene_id' => ['nullable', 'integer', 'exists:scenes,id'],
            'version_label' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'handout_file' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:4096',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $description = trim((string) $this->input('description', ''));
        $versionLabel = trim((string) $this->input('version_label', ''));

        $this->merge([
            'title' => trim((string) $this->input('title', '')),
            'description' => $description !== '' ? $description : null,
            'scene_id' => $this->filled('scene_id') ? (int) $this->input('scene_id') : null,
            'version_label' => $versionLabel !== '' ? $versionLabel : null,
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
            'description' => 'Beschreibung',
            'scene_id' => 'Szene',
            'version_label' => 'Version',
            'sort_order' => 'Sortierung',
            'handout_file' => 'Handout-Datei',
        ];
    }
}
