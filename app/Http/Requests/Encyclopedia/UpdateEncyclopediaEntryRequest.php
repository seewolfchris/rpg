<?php

namespace App\Http\Requests\Encyclopedia;

use App\Models\EncyclopediaCategory;
use App\Models\EncyclopediaEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateEncyclopediaEntryRequest extends FormRequest
{
    private const GAME_RELEVANCE_INPUT_MAP = [
        'game_relevance_le' => 'le_hint',
        'game_relevance_rs' => 'rs_hint',
        'game_relevance_ae' => 'ae_hint',
        'game_relevance_probe' => 'probe_hint',
        'game_relevance_real_world' => 'real_world_hint',
    ];

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
        $category = $this->route('encyclopediaCategory');
        $categoryId = $category instanceof EncyclopediaCategory ? $category->getKey() : null;

        $entry = $this->route('encyclopediaEntry');
        $entryId = $entry instanceof EncyclopediaEntry ? $entry->getKey() : null;

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
            'game_relevance' => ['nullable', 'array'],
            'game_relevance.le_hint' => ['nullable', 'string', 'max:1000'],
            'game_relevance.rs_hint' => ['nullable', 'string', 'max:1000'],
            'game_relevance.ae_hint' => ['nullable', 'string', 'max:1000'],
            'game_relevance.probe_hint' => ['nullable', 'string', 'max:1000'],
            'game_relevance.real_world_hint' => ['nullable', 'string', 'max:1000'],
            'game_relevance_le' => ['nullable', 'string', 'max:1000'],
            'game_relevance_rs' => ['nullable', 'string', 'max:1000'],
            'game_relevance_ae' => ['nullable', 'string', 'max:1000'],
            'game_relevance_probe' => ['nullable', 'string', 'max:1000'],
            'game_relevance_real_world' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $slugInput = $this->input('slug');
        $titleInput = $this->input('title');
        $generatedSlug = Str::slug((string) ($slugInput ?: $titleInput));
        $publishedAt = $this->input('published_at');
        $gameRelevance = $this->compactGameRelevance();

        $this->merge([
            'slug' => $generatedSlug !== '' ? $generatedSlug : 'eintrag-'.Str::lower(Str::random(8)),
            'status' => Str::lower((string) $this->input('status', EncyclopediaEntry::STATUS_DRAFT)),
            'position' => (int) $this->input('position', 0),
            'published_at' => $publishedAt !== '' ? $publishedAt : null,
            'game_relevance' => $gameRelevance,
        ]);
    }

    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated();

        foreach (array_keys(self::GAME_RELEVANCE_INPUT_MAP) as $inputField) {
            unset($validated[$inputField]);
        }

        return $key === null ? $validated : data_get($validated, $key, $default);
    }

    /**
     * @return array<string, string>|null
     */
    private function compactGameRelevance(): ?array
    {
        $gameRelevance = [];

        foreach (self::GAME_RELEVANCE_INPUT_MAP as $inputField => $jsonKey) {
            $value = trim((string) $this->input($inputField, ''));

            if ($value === '') {
                continue;
            }

            $gameRelevance[$jsonKey] = $value;
        }

        return $gameRelevance === [] ? null : $gameRelevance;
    }
}
