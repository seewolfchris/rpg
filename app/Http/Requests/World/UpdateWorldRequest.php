<?php

namespace App\Http\Requests\World;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateWorldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $worldId = $this->route('world')?->getKey();

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required',
                'string',
                'max:140',
                'alpha_dash',
                Rule::unique('worlds', 'slug')->ignore($worldId),
            ],
            'tagline' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $slugInput = $this->input('slug');
        $nameInput = $this->input('name');
        $generatedSlug = Str::slug((string) ($slugInput ?: $nameInput));

        $this->merge([
            'slug' => $generatedSlug !== '' ? $generatedSlug : 'welt-'.Str::lower(Str::random(8)),
            'position' => (int) $this->input('position', 0),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
