<?php

declare(strict_types=1);

namespace App\Http\Requests\Scene;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Models\Campaign;
use App\Models\Scene;
use App\Support\SensitiveFeatureGate;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSceneConflictActorRequest extends FormRequest
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
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
            'le_current' => ['nullable', 'integer', 'between:0,999'],
            'le_max' => ['nullable', 'integer', 'between:0,999'],
            'ae_current' => ['nullable', 'integer', 'between:0,999'],
            'ae_max' => ['nullable', 'integer', 'between:0,999'],
            'attack_value' => ['nullable', 'integer', 'between:0,100'],
            'defense_value' => ['nullable', 'integer', 'between:0,100'],
            'armor_protection' => ['nullable', 'integer', 'between:0,999'],
            'damage_value' => ['nullable', 'integer', 'between:0,999'],
            'spell_value' => ['nullable', 'integer', 'between:0,100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->nullIfBlank($this->input('name')),
            'notes' => $this->nullIfBlank($this->input('notes')),
            'le_current' => $this->filled('le_current') ? (int) $this->input('le_current') : null,
            'le_max' => $this->filled('le_max') ? (int) $this->input('le_max') : null,
            'ae_current' => $this->filled('ae_current') ? (int) $this->input('ae_current') : null,
            'ae_max' => $this->filled('ae_max') ? (int) $this->input('ae_max') : null,
            'attack_value' => $this->filled('attack_value') ? (int) $this->input('attack_value') : null,
            'defense_value' => $this->filled('defense_value') ? (int) $this->input('defense_value') : null,
            'armor_protection' => $this->filled('armor_protection') ? (int) $this->input('armor_protection') : null,
            'damage_value' => $this->filled('damage_value') ? (int) $this->input('damage_value') : null,
            'spell_value' => $this->filled('spell_value') ? (int) $this->input('spell_value') : null,
            'sort_order' => $this->filled('sort_order') ? (int) $this->input('sort_order') : null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'Name',
            'le_current' => 'LE aktuell',
            'le_max' => 'LE max',
            'ae_current' => 'AE aktuell',
            'ae_max' => 'AE max',
            'attack_value' => 'Angriffswert',
            'defense_value' => 'Verteidigungswert',
            'armor_protection' => 'Rüstungsschutz',
            'damage_value' => 'Schaden',
            'spell_value' => 'Zauberwert',
            'notes' => 'Notizen',
            'sort_order' => 'Sortierung',
        ];
    }

    private function nullIfBlank(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function campaignParticipantResolver(): CampaignParticipantResolver
    {
        /** @var CampaignParticipantResolver $resolver */
        $resolver = app(CampaignParticipantResolver::class);

        return $resolver;
    }
}

