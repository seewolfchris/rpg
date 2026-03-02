<?php

namespace App\Http\Requests\Scene;

use App\Models\Campaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
            'summary' => ['nullable', 'string', 'max:1200'],
            'description' => ['nullable', 'string', 'max:15000'],
            'status' => ['required', Rule::in(['open', 'closed', 'archived'])],
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
            'allow_ooc' => $this->boolean('allow_ooc'),
        ]);
    }
}
