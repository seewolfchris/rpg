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
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'moderation_note' => trim((string) $this->input('moderation_note', '')),
            'q' => trim((string) $this->input('q', '')),
            'status' => $this->filled('status') ? (string) $this->input('status') : 'pending',
        ]);
    }
}
