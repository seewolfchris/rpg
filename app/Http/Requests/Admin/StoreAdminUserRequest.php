<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::ADMIN) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::notIn([User::DELETED_USER_SYSTEM_EMAIL]),
                Rule::unique('users', 'email'),
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in([
                UserRole::PLAYER->value,
                UserRole::ADMIN->value,
            ])],
            'status' => ['required', Rule::in([
                UserStatus::PENDING->value,
                UserStatus::ACTIVE->value,
                UserStatus::SUSPENDED->value,
            ])],
            'can_create_campaigns' => ['required', 'boolean'],
            'can_post_without_moderation' => ['required', 'boolean'],
            'status_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'email' => mb_strtolower(trim((string) $this->input('email', ''))),
            'role' => mb_strtolower(trim((string) $this->input('role', UserRole::PLAYER->value))),
            'status' => mb_strtolower(trim((string) $this->input('status', UserStatus::PENDING->value))),
            'can_create_campaigns' => $this->boolean('can_create_campaigns'),
            'can_post_without_moderation' => $this->boolean('can_post_without_moderation'),
            'status_reason' => trim((string) $this->input('status_reason', '')),
        ]);
    }
}
