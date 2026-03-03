<?php

namespace App\Http\Requests\Encyclopedia;

use App\Models\EncyclopediaEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateEncyclopediaEntryRequest extends FormRequest
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
        $entryId = $this->route('encyclopediaEntry')?->getKey();

        return [
            'title' => ['required', 'string', 'max:150'],
            'slug' => [
                'required',
                'string',
                'max:170',
                'alpha_dash',
                Rule::unique('encyclopedia_entries', 'slug')
                    ->where(fn ($query) => $query->where('encyclopedia_category_id', $categoryId))
                    ->ignore($entryId),
            ],
            'excerpt' => ['nullable', 'string', 'max:4000'],
            'content' => ['required', 'string', 'max:50000'],
            'status' => ['required', Rule::in([
                EncyclopediaEntry::STATUS_DRAFT,
                EncyclopediaEntry::STATUS_PUBLISHED,
                EncyclopediaEntry::STATUS_ARCHIVED,
            ])],
            'position' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $slugInput = $this->input('slug');
        $titleInput = $this->input('title');
        $generatedSlug = Str::slug((string) ($slugInput ?: $titleInput));
        $publishedAt = $this->input('published_at');

        $this->merge([
            'slug' => $generatedSlug !== '' ? $generatedSlug : 'eintrag-'.Str::lower(Str::random(8)),
            'status' => Str::lower((string) $this->input('status', EncyclopediaEntry::STATUS_DRAFT)),
            'position' => (int) $this->input('position', 0),
            'published_at' => $publishedAt !== '' ? $publishedAt : null,
        ]);
    }
}
