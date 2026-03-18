<?php

namespace App\Http\Requests\Scene;

use App\Models\Campaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSceneRequest extends FormRequest
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
        /** @var Campaign $campaign */
        $campaign = $this->route('campaign');

        return [
            'title' => ['required', 'string', 'max:150'],
            'slug' => [
                'required',
                'string',
                'max:170',
                'alpha_dash',
                Rule::unique('scenes', 'slug')->where(
                    fn ($query) => $query->where('campaign_id', $campaign->id)
                ),
            ],
            'previous_scene_id' => ['nullable', 'integer', 'exists:scenes,id'],
            'summary' => ['nullable', 'string', 'max:1200'],
            'description' => ['nullable', 'string', 'max:15000'],
            'header_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:4096'],
            'status' => ['required', Rule::in(['open', 'closed', 'archived'])],
            'mood' => ['required', Rule::in($this->moodKeys())],
            'position' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'allow_ooc' => ['sometimes', 'boolean'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after_or_equal:opens_at'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $slugInput = $this->input('slug');
        $titleInput = $this->input('title');

        $this->merge([
            'slug' => Str::slug((string) ($slugInput ?: $titleInput)),
            'mood' => (string) $this->input('mood', (string) config('scenes.default_mood', 'neutral')),
            'allow_ooc' => $this->boolean('allow_ooc'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Campaign $campaign */
            $campaign = $this->route('campaign');
            $previousSceneId = (int) ($this->input('previous_scene_id') ?? 0);

            if ($previousSceneId <= 0) {
                return;
            }

            $isSameCampaign = $campaign->scenes()
                ->whereKey($previousSceneId)
                ->exists();

            if (! $isSameCampaign) {
                $validator->errors()->add('previous_scene_id', 'Die Vorgängerszene muss zur gleichen Kampagne gehören.');
            }
        });
    }

    /**
     * @return list<string>
     */
    private function moodKeys(): array
    {
        /** @var list<string> $keys */
        $keys = array_keys((array) config('scenes.moods', []));

        return $keys;
    }
}
