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
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'exists:users,email'],
            'role' => ['required', Rule::in([CampaignInvitation::ROLE_PLAYER, CampaignInvitation::ROLE_CO_GM])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'role' => (string) $this->input('role', CampaignInvitation::ROLE_PLAYER),
        ]);
    }
}
