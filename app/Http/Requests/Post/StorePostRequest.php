<?php

namespace App\Http\Requests\Post;

use App\Models\CampaignInvitation;
use App\Models\Character;
use App\Models\DiceRoll;
use App\Models\Scene;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePostRequest extends FormRequest
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
            'post_type' => ['required', Rule::in(['ic', 'ooc'])],
            'character_id' => ['nullable', 'integer', 'exists:characters,id'],
            'content_format' => ['required', Rule::in(['markdown', 'bbcode', 'plain'])],
            'content' => ['required', 'string', 'min:5', 'max:10000'],
            'probe_enabled' => ['nullable', 'boolean'],
            'probe_character_id' => ['nullable', 'integer', 'required_if:probe_enabled,1', 'exists:characters,id'],
            'probe_roll_mode' => ['nullable', 'required_if:probe_enabled,1', Rule::in(DiceRoll::ALLOWED_MODES)],
            'probe_modifier' => ['nullable', 'required_if:probe_enabled,1', 'integer', 'between:-40,40'],
            'probe_attribute_key' => ['nullable', 'required_if:probe_enabled,1', Rule::in($this->probeAttributeKeys())],
            'probe_explanation' => ['nullable', 'required_if:probe_enabled,1', 'string', 'min:3', 'max:180'],
            'probe_le_delta' => ['nullable', 'required_if:probe_enabled,1', 'integer', 'between:-200,200'],
            'probe_ae_delta' => ['nullable', 'required_if:probe_enabled,1', 'integer', 'between:-200,200'],
            'inventory_award_enabled' => ['nullable', 'boolean'],
            'inventory_award_character_id' => ['nullable', 'integer', 'required_if:inventory_award_enabled,1', 'exists:characters,id'],
            'inventory_award_item' => ['nullable', 'required_if:inventory_award_enabled,1', 'string', 'min:2', 'max:180'],
            'inventory_award_quantity' => ['nullable', 'required_if:inventory_award_enabled,1', 'integer', 'between:1,999'],
            'inventory_award_equipped' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $probeEnabled = $this->boolean('probe_enabled');
        $inventoryAwardEnabled = $this->boolean('inventory_award_enabled');

        $normalized = [
            'probe_enabled' => $probeEnabled,
            'inventory_award_enabled' => $inventoryAwardEnabled,
            'inventory_award_item' => trim((string) $this->input('inventory_award_item', '')),
            'inventory_award_quantity' => (int) $this->input('inventory_award_quantity', 1),
            'inventory_award_equipped' => $this->boolean('inventory_award_equipped'),
        ];

        if ($probeEnabled && ! $this->filled('probe_modifier')) {
            $normalized['probe_modifier'] = 0;
        }

        if ($probeEnabled && ! $this->filled('probe_le_delta')) {
            $normalized['probe_le_delta'] = 0;
        }

        if ($probeEnabled && ! $this->filled('probe_ae_delta')) {
            $normalized['probe_ae_delta'] = 0;
        }

        if ($inventoryAwardEnabled && ! $this->filled('inventory_award_quantity')) {
            $normalized['inventory_award_quantity'] = 1;
        }

        $this->merge($normalized);
    }

    /**
     * @return list<string>
     */
    private function probeAttributeKeys(): array
    {
        /** @var list<string> $keys */
        $keys = array_keys((array) config('character_sheet.attributes', []));

        return $keys;
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

            $postType = (string) $this->input('post_type');
            $characterId = $this->filled('character_id')
                ? (int) $this->input('character_id')
                : null;

            if ($postType === 'ooc' && ! $scene->allow_ooc) {
                $validator->errors()->add('post_type', 'OOC-Beiträge sind in dieser Szene deaktiviert.');
            }

            if ($postType === 'ic' && ! $characterId) {
                $validator->errors()->add('character_id', 'Für IC-Beiträge ist ein Charakter erforderlich.');
            }

            if ($characterId) {
                $character = Character::query()->find($characterId);

                if ($character && $character->user_id !== (int) $this->user()?->id) {
                    $validator->errors()->add('character_id', 'Du kannst nur eigene Charaktere verwenden.');
                } elseif ($character && (int) $character->world_id !== (int) $scene->campaign->world_id) {
                    $validator->errors()->add('character_id', 'Der gewählte Charakter gehört nicht zur Welt dieser Kampagne.');
                }
            }

            $user = $this->user();
            $canModerate = $user
                && ($user->isGmOrAdmin() || $scene->campaign->isCoGm($user));

            $probeEnabled = (bool) $this->boolean('probe_enabled');
            $inventoryAwardEnabled = (bool) $this->boolean('inventory_award_enabled');

            $campaignParticipantUserIds = collect();
            if ($probeEnabled || $inventoryAwardEnabled) {
                $campaignParticipantUserIds = $scene->campaign->invitations()
                    ->where('status', CampaignInvitation::STATUS_ACCEPTED)
                    ->pluck('user_id')
                    ->push((int) $scene->campaign->owner_id)
                    ->unique();
            }

            if ($probeEnabled) {
                if (! $canModerate) {
                    $validator->errors()->add('probe_enabled', 'Nur GM oder Co-GM dürfen Proben ausführen.');
                } else {
                    $probeCharacterId = $this->filled('probe_character_id')
                        ? (int) $this->input('probe_character_id')
                        : null;

                    if (! $probeCharacterId) {
                        $validator->errors()->add('probe_character_id', 'Für die Probe muss ein Ziel-Held gewählt werden.');
                    } else {
                        $probeCharacter = Character::query()
                            ->select(['id', 'user_id', 'world_id'])
                            ->find($probeCharacterId);

                        if (! $probeCharacter) {
                            $validator->errors()->add('probe_character_id', 'Der Ziel-Held konnte nicht gefunden werden.');
                        } elseif ((int) $probeCharacter->world_id !== (int) $scene->campaign->world_id) {
                            $validator->errors()->add(
                                'probe_character_id',
                                'Der Ziel-Held gehört nicht zur Welt dieser Kampagne.'
                            );
                        } elseif (! $campaignParticipantUserIds->contains((int) $probeCharacter->user_id)) {
                            $validator->errors()->add(
                                'probe_character_id',
                                'Der Ziel-Held muss ein aktiver Teilnehmer dieser Kampagne sein.'
                            );
                        }
                    }
                }
            }

            if ($inventoryAwardEnabled) {
                if (! $canModerate) {
                    $validator->errors()->add('inventory_award_enabled', 'Nur GM oder Co-GM dürfen Inventar-Funde vergeben.');
                } else {
                    $awardCharacterId = $this->filled('inventory_award_character_id')
                        ? (int) $this->input('inventory_award_character_id')
                        : null;

                    if (! $awardCharacterId) {
                        $validator->errors()->add(
                            'inventory_award_character_id',
                            'Für den Inventar-Fund muss ein Ziel-Held gewählt werden.'
                        );
                    } else {
                        $awardCharacter = Character::query()
                            ->select(['id', 'user_id', 'world_id'])
                            ->find($awardCharacterId);

                        if (! $awardCharacter) {
                            $validator->errors()->add('inventory_award_character_id', 'Der Ziel-Held konnte nicht gefunden werden.');
                        } elseif ((int) $awardCharacter->world_id !== (int) $scene->campaign->world_id) {
                            $validator->errors()->add(
                                'inventory_award_character_id',
                                'Der Ziel-Held gehört nicht zur Welt dieser Kampagne.'
                            );
                        } elseif (! $campaignParticipantUserIds->contains((int) $awardCharacter->user_id)) {
                            $validator->errors()->add(
                                'inventory_award_character_id',
                                'Der Ziel-Held muss ein aktiver Teilnehmer dieser Kampagne sein.'
                            );
                        }
                    }
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'probe_attribute_key' => 'Probe-Eigenschaft',
            'probe_character_id' => 'Ziel-Held',
            'probe_explanation' => 'Erklärung / Anlass',
            'inventory_award_character_id' => 'Ziel-Held (Inventar-Fund)',
            'inventory_award_item' => 'Inventar-Fund',
            'inventory_award_quantity' => 'Menge (Inventar-Fund)',
            'inventory_award_equipped' => 'Ausgerüstet (Inventar-Fund)',
        ];
    }
}
