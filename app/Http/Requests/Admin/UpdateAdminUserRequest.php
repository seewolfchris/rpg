<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateAdminUserRequest extends FormRequest
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
        $targetUser = $this->route('user');
        $targetUserId = $targetUser instanceof User ? (int) $targetUser->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($targetUserId),
            ],
            'password' => ['nullable', 'confirmed', Password::defaults()],
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
        $password = $this->input('password');
        $normalizedPassword = is_string($password) && $password !== '' ? $password : null;

        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'email' => mb_strtolower(trim((string) $this->input('email', ''))),
            'password' => $normalizedPassword,
            'role' => mb_strtolower(trim((string) $this->input('role', UserRole::PLAYER->value))),
            'status' => mb_strtolower(trim((string) $this->input('status', UserStatus::PENDING->value))),
            'can_create_campaigns' => $this->boolean('can_create_campaigns'),
            'can_post_without_moderation' => $this->boolean('can_post_without_moderation'),
            'status_reason' => trim((string) $this->input('status_reason', '')),
        ]);
    }
}
