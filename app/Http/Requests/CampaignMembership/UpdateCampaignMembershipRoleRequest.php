<?php

namespace App\Http\Requests\CampaignMembership;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCampaignMembershipRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $campaign = $this->route('campaign');

        return $user instanceof User
            && $campaign instanceof Campaign
            && (int) $campaign->owner_id === (int) $user->id;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in([
                CampaignMembershipRole::GM->value,
                CampaignMembershipRole::TRUSTED_PLAYER->value,
                CampaignMembershipRole::PLAYER->value,
            ])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'role' => strtolower(trim((string) $this->input('role', CampaignMembershipRole::PLAYER->value))),
        ]);
    }
}
