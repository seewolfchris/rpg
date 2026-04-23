<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserModerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in([
                UserRole::PLAYER->value,
                UserRole::ADMIN->value,
            ])],
            'can_create_campaigns' => ['required', 'boolean'],
            'can_post_without_moderation' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'role' => strtolower(trim((string) $this->input('role', UserRole::PLAYER->value))),
            'can_create_campaigns' => $this->boolean('can_create_campaigns'),
            'can_post_without_moderation' => $this->boolean('can_post_without_moderation'),
        ]);
    }
}
