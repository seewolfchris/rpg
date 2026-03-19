<?php

namespace App\Http\Requests\Post;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkModerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'moderation_status' => ['required', Rule::in(['pending', 'approved', 'rejected'])],
            'moderation_note' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', Rule::in(['all', 'pending', 'approved', 'rejected'])],
            'q' => ['nullable', 'string', 'max:120'],
            'scene_id' => ['nullable', 'integer', 'min:1'],
            'post_ids' => ['nullable', 'array', 'min:1'],
            'post_ids.*' => ['integer', 'min:1', 'distinct'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $postIds = collect((array) $this->input('post_ids', []))
            ->map(static fn ($postId): int => (int) $postId)
            ->filter(static fn (int $postId): bool => $postId > 0)
            ->unique()
            ->values()
            ->all();

        $this->merge([
            'moderation_note' => trim((string) $this->input('moderation_note', '')),
            'q' => trim((string) $this->input('q', '')),
            'status' => $this->filled('status') ? (string) $this->input('status') : 'pending',
            'scene_id' => $this->filled('scene_id') ? (int) $this->input('scene_id') : null,
            'post_ids' => $postIds,
        ]);
    }
}
