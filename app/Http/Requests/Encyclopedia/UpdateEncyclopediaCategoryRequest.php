<?php

namespace App\Http\Requests\Encyclopedia;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateEncyclopediaCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isGmOrAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $categoryId = $this->route('encyclopediaCategory')?->getKey();
        $worldId = $this->route('world')?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required',
                'string',
                'max:140',
                'alpha_dash',
                Rule::unique('encyclopedia_categories', 'slug')
                    ->where(fn ($query) => $query->where('world_id', $worldId))
                    ->ignore($categoryId),
            ],
            'summary' => ['nullable', 'string', 'max:2000'],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_public' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $slugInput = $this->input('slug');
        $nameInput = $this->input('name');
        $generatedSlug = Str::slug((string) ($slugInput ?: $nameInput));

        $this->merge([
            'slug' => $generatedSlug !== '' ? $generatedSlug : 'kategorie-'.Str::lower(Str::random(8)),
            'position' => (int) $this->input('position', 0),
            'is_public' => $this->boolean('is_public'),
        ]);
    }
}
