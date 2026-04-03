<?php

namespace App\Http\Requests\Scene;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\Scene;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSceneInventoryActionRequest extends FormRequest
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
            'inventory_action_character_id' => ['required', 'integer', 'exists:characters,id'],
            'inventory_action_type' => ['required', Rule::in(['add', 'remove'])],
            'inventory_action_item' => ['required', 'string', 'min:2', 'max:180'],
            'inventory_action_quantity' => ['required', 'integer', 'between:1,999'],
            'inventory_action_equipped' => ['nullable', 'boolean'],
            'inventory_action_note' => ['nullable', 'string', 'max:180'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'inventory_action_item' => trim((string) $this->input('inventory_action_item', '')),
            'inventory_action_quantity' => (int) $this->input('inventory_action_quantity', 1),
            'inventory_action_equipped' => $this->boolean('inventory_action_equipped'),
            'inventory_action_note' => trim((string) $this->input('inventory_action_note', '')),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Scene|null $scene */
            $scene = $this->route('scene');

            if (! $scene) {
                $validator->errors()->add('scene', 'Szene konnte nicht gefunden werden.');

                return;
            }

            /** @var Campaign $campaign */
            $campaign = $scene->campaign;

            $user = $this->user();
            $canModerate = $user
                && ($user->isGmOrAdmin() || $campaign->isCoGm($user));

            if (! $canModerate) {
                $validator->errors()->add(
                    'inventory_action_character_id',
                    'Nur GM oder Co-GM dürfen Inventar-Schnellaktionen ausführen.'
                );

                return;
            }

            $characterId = $this->filled('inventory_action_character_id')
                ? (int) $this->input('inventory_action_character_id')
                : 0;

            if ($characterId <= 0) {
                return;
            }

            /** @var Character|null $targetCharacter */
            $targetCharacter = Character::query()
                ->select(['id', 'user_id', 'world_id'])
                ->find($characterId);

            if (! $targetCharacter instanceof Character) {
                $validator->errors()->add('inventory_action_character_id', 'Der Ziel-Held konnte nicht gefunden werden.');

                return;
            }

            $campaignParticipantUserIds = $this->campaignParticipantResolver()
                ->participantUserIds($campaign);

            if ((int) $targetCharacter->user_id <= 0) {
                $validator->errors()->add(
                    'inventory_action_character_id',
                    'Der Ziel-Held muss ein aktiver Teilnehmer dieser Kampagne sein.'
                );
            } elseif (! $campaignParticipantUserIds->contains((int) $targetCharacter->user_id)) {
                $validator->errors()->add(
                    'inventory_action_character_id',
                    'Der Ziel-Held muss ein aktiver Teilnehmer dieser Kampagne sein.'
                );
            } elseif ((int) $targetCharacter->world_id !== (int) $campaign->world_id) {
                $validator->errors()->add(
                    'inventory_action_character_id',
                    'Der Ziel-Held gehört nicht zur Welt dieser Kampagne.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'inventory_action_character_id' => 'Ziel-Held',
            'inventory_action_type' => 'Inventar-Aktion',
            'inventory_action_item' => 'Gegenstand',
            'inventory_action_quantity' => 'Menge',
            'inventory_action_equipped' => 'Ausgerüstet',
            'inventory_action_note' => 'Notiz',
        ];
    }

    private function campaignParticipantResolver(): CampaignParticipantResolver
    {
        /** @var CampaignParticipantResolver $resolver */
        $resolver = app(CampaignParticipantResolver::class);

        return $resolver;
    }
}
