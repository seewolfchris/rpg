<?php

namespace App\Http\Requests\Encyclopedia;

use App\Models\World;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreEncyclopediaCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $world = $this->route('world');
        $worldId = $world instanceof World ? $world->id : null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required',
                'string',
                'max:140',
                'alpha_dash',
                Rule::unique('encyclopedia_categories', 'slug')
                    ->where(fn ($query) => $query->where('world_id', $worldId)),
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
