<?php

namespace App\Http\Requests\Campaign;

use App\Models\Campaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCampaignRequest extends FormRequest
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
                Rule::unique('campaigns', 'slug')->ignore($campaign->id),
            ],
            'summary' => ['nullable', 'string', 'max:1000'],
            'lore' => ['nullable', 'string', 'max:15000'],
            'status' => ['required', Rule::in(['draft', 'active', 'archived'])],
            'is_public' => ['sometimes', 'boolean'],
            'requires_post_moderation' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $slugInput = $this->input('slug');
        $titleInput = $this->input('title');

        $this->merge([
            'slug' => Str::slug((string) ($slugInput ?: $titleInput)),
            'is_public' => $this->boolean('is_public'),
            'requires_post_moderation' => $this->boolean('requires_post_moderation'),
        ]);
    }
}
