<?php

namespace App\Http\Requests\Scene;

use App\Models\CampaignInvitation;
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
            'inventory_action_note' => ['nullable', 'string', 'max:180'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'inventory_action_item' => trim((string) $this->input('inventory_action_item', '')),
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

            $user = $this->user();
            $canModerate = $user
                && ($user->isGmOrAdmin() || $scene->campaign->isCoGm($user));

            if (! $canModerate) {
                $validator->errors()->add(
                    'inventory_action_character_id',
                    'Nur GM oder Co-GM duerfen Inventar-Schnellaktionen ausfuehren.'
                );

                return;
            }

            $characterId = $this->filled('inventory_action_character_id')
                ? (int) $this->input('inventory_action_character_id')
                : 0;

            if ($characterId <= 0) {
                return;
            }

            $targetCharacter = Character::query()
                ->select(['id', 'user_id'])
                ->find($characterId);

            if (! $targetCharacter) {
                $validator->errors()->add('inventory_action_character_id', 'Der Ziel-Held konnte nicht gefunden werden.');

                return;
            }

            $campaignParticipantUserIds = $scene->campaign->invitations()
                ->where('status', CampaignInvitation::STATUS_ACCEPTED)
                ->pluck('user_id')
                ->push((int) $scene->campaign->owner_id)
                ->unique();

            if (! $campaignParticipantUserIds->contains((int) $targetCharacter->user_id)) {
                $validator->errors()->add(
                    'inventory_action_character_id',
                    'Der Ziel-Held muss ein aktiver Teilnehmer dieser Kampagne sein.'
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
            'inventory_action_note' => 'Notiz',
        ];
    }
}
