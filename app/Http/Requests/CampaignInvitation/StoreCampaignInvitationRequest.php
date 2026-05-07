<?php

namespace App\Http\Requests\CampaignInvitation;

use App\Models\CampaignInvitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampaignInvitationRequest extends FormRequest
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
        return [
            'email' => ['nullable', 'string', 'email:rfc', 'max:255', 'required_without:user_ids'],
            'user_ids' => ['nullable', 'array', 'required_without:email'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'role' => ['required', Rule::in([
                CampaignInvitation::ROLE_PLAYER,
                CampaignInvitation::ROLE_TRUSTED_PLAYER,
                CampaignInvitation::ROLE_CO_GM,
            ])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $rawUserIds = $this->input('user_ids', []);
        if (! is_array($rawUserIds)) {
            $rawUserIds = [];
        }

        $normalizedUserIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $userId): int => (int) $userId,
            $rawUserIds
        ), static fn (int $userId): bool => $userId > 0)));

        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'role' => (string) $this->input('role', CampaignInvitation::ROLE_PLAYER),
            'user_ids' => $normalizedUserIds,
        ]);
    }
}
