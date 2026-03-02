<?php

namespace App\Http\Requests\SceneSubscription;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateSceneSubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bulk_action' => ['required', Rule::in([
                'mute_filtered',
                'unmute_filtered',
                'unfollow_filtered',
                'mute_all_active',
                'unmute_all_muted',
                'unfollow_all_muted',
            ])],
            'status' => ['nullable', Rule::in(['all', 'active', 'muted'])],
            'q' => ['nullable', 'string', 'max:120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->filled('status') ? (string) $this->input('status') : 'all',
            'q' => trim((string) $this->input('q', '')),
        ]);
    }
}
