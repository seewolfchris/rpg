<?php

declare(strict_types=1);

namespace App\Http\Requests\Scene;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Models\Campaign;
use App\Models\Scene;
use App\Support\SensitiveFeatureGate;
use Illuminate\Foundation\Http\FormRequest;

class StoreSceneCombatPhaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless(SensitiveFeatureGate::enabled('features.combat_tools_enabled', false), 404);

        $user = $this->user();
        $scene = $this->route('scene');

        if ($user === null || ! $scene instanceof Scene) {
            return false;
        }

        /** @var Campaign $campaign */
        $campaign = $scene->campaign;

        return $this->campaignParticipantResolver()->canModerateCampaign($user, $campaign);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }

    private function campaignParticipantResolver(): CampaignParticipantResolver
    {
        /** @var CampaignParticipantResolver $resolver */
        $resolver = app(CampaignParticipantResolver::class);

        return $resolver;
    }
}
