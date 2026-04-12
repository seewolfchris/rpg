<?php

namespace App\Http\Requests\Encyclopedia;

use App\Models\World;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreProposalRequest extends FormRequest
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
        $world = $this->route('world');
        $worldId = $world instanceof World ? (int) $world->id : 0;
        $categoryId = (int) $this->input('encyclopedia_category_id', 0);

        return [
            'encyclopedia_category_id' => [
                'required',
                'integer',
                Rule::exists('encyclopedia_categories', 'id')
                    ->where(fn ($query) => $query
                        ->where('world_id', $worldId)
                        ->where('is_public', true)),
            ],
            'title' => ['required', 'string', 'max:150'],
            'slug' => [
                'required',
                'string',
                'max:170',
                'alpha_dash',
                Rule::unique('encyclopedia_entries', 'slug')
                    ->where(fn ($query) => $query->where('encyclopedia_category_id', $categoryId)),
            ],
            'excerpt' => ['nullable', 'string', 'max:4000'],
            'content' => ['required', 'string', 'max:50000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $slugInput = $this->input('slug');
        $titleInput = $this->input('title');
        $generatedSlug = Str::slug((string) ($slugInput ?: $titleInput));
        $excerpt = trim((string) $this->input('excerpt', ''));

        $this->merge([
            'encyclopedia_category_id' => (int) $this->input('encyclopedia_category_id', 0),
            'slug' => $generatedSlug !== '' ? $generatedSlug : 'eintrag-'.Str::lower(Str::random(8)),
            'excerpt' => $excerpt !== '' ? $excerpt : null,
        ]);
    }
}
