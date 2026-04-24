<?php

namespace App\Http\Requests\Post;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\DiceRoll;
use App\Models\Post;
use App\Models\Scene;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $scene = $this->route('scene');

        return $user !== null
            && $scene instanceof Scene
            && $user->can('create', [Post::class, $scene]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'post_type' => ['required', Rule::in(['ic', 'ooc'])],
            'post_mode' => ['nullable', Rule::in(['character', 'gm'])],
            'character_id' => ['nullable', 'integer'],
            'content_format' => ['required', Rule::in(['markdown', 'bbcode', 'plain'])],
            'content' => ['required', 'string', 'min:5', 'max:10000'],
            'ic_quote' => ['nullable', 'string', 'max:180'],
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
        $postType = (string) $this->input('post_type', 'ic');
        $postMode = strtolower(trim((string) $this->input('post_mode', '')));

        if ($postMode === '') {
            $postMode = 'character';
        }

        if ($postType !== 'ic') {
            $postMode = 'character';
        }

        $normalized = [
            'post_mode' => $postMode,
            'probe_enabled' => $probeEnabled,
            'inventory_award_enabled' => $inventoryAwardEnabled,
            'ic_quote' => ($quote = trim((string) $this->input('ic_quote', ''))) !== '' ? $quote : null,
            'inventory_award_item' => trim((string) $this->input('inventory_award_item', '')),
            'inventory_award_quantity' => (int) $this->input('inventory_award_quantity', 1),
            'inventory_award_equipped' => $this->boolean('inventory_award_equipped'),
        ];

        if ($postType !== 'ic') {
            $normalized['character_id'] = null;
        }

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

            /** @var Campaign $campaign */
            $campaign = $scene->campaign;

            $postType = (string) $this->input('post_type');
            $postMode = $postType === 'ic'
                ? (string) $this->input('post_mode', 'character')
                : 'character';
            $characterId = $this->filled('character_id')
                ? (int) $this->input('character_id')
                : null;
            $user = $this->user();
            $canModerate = $user
                && $campaign->canModeratePosts($user);

            if ($postType === 'ooc' && ! $scene->allow_ooc && ! $canModerate) {
                $validator->errors()->add('post_type', 'OOC-Beiträge sind in dieser Szene deaktiviert.');
            }

            if ($postType === 'ic' && $postMode === 'character' && ! $characterId) {
                $validator->errors()->add('character_id', 'Für IC-Beiträge als Charakter ist ein Charakter erforderlich.');
            }

            if ($postType === 'ic' && $postMode === 'gm') {
                if (! $canModerate) {
                    $validator->errors()->add('post_mode', 'Nur GM oder Co-GM dürfen als Spielleitung posten.');
                }

                if ($characterId !== null) {
                    $validator->errors()->add('character_id', 'Für Spielleitungsbeiträge darf kein Charakter gesetzt sein.');
                }
            }

            if ($postType !== 'ic' && trim((string) ($this->input('ic_quote') ?? '')) !== '') {
                $validator->errors()->add('ic_quote', 'Ein IC-Zitat ist nur für IC-Beiträge erlaubt.');
            }

            if ($postType === 'ic' && $postMode === 'character' && $characterId) {
                /** @var Character|null $character */
                $character = Character::query()->find($characterId);

                if (! $character instanceof Character) {
                    $validator->errors()->add('character_id', 'Charakter konnte nicht gefunden werden.');
                } elseif ($character->user_id !== (int) $this->user()?->id) {
                    $validator->errors()->add('character_id', 'Du kannst nur eigene Charaktere verwenden.');
                } elseif ((int) $character->world_id !== (int) $campaign->world_id) {
                    $validator->errors()->add('character_id', 'Der gewählte Charakter gehört nicht zur Welt dieser Kampagne.');
                }
            }

            $probeEnabled = (bool) $this->boolean('probe_enabled');
            $inventoryAwardEnabled = (bool) $this->boolean('inventory_award_enabled');

            $campaignParticipantUserIds = ($probeEnabled || $inventoryAwardEnabled)
                ? $this->campaignParticipantResolver()->participantUserIds($campaign)
                : collect();

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
                        /** @var Character|null $probeCharacter */
                        $probeCharacter = Character::query()
                            ->select(['id', 'user_id', 'world_id'])
                            ->find($probeCharacterId);

                        if (! $probeCharacter instanceof Character) {
                            $validator->errors()->add('probe_character_id', 'Der Ziel-Held konnte nicht gefunden werden.');
                        } elseif ((int) $probeCharacter->world_id !== (int) $campaign->world_id) {
                            $validator->errors()->add(
                                'probe_character_id',
                                'Der Ziel-Held gehört nicht zur Welt dieser Kampagne.'
                            );
                        } elseif ((int) $probeCharacter->user_id <= 0) {
                            $validator->errors()->add(
                                'probe_character_id',
                                'Der Ziel-Held muss ein aktiver Teilnehmer dieser Kampagne sein.'
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
                        /** @var Character|null $awardCharacter */
                        $awardCharacter = Character::query()
                            ->select(['id', 'user_id', 'world_id'])
                            ->find($awardCharacterId);

                        if (! $awardCharacter instanceof Character) {
                            $validator->errors()->add('inventory_award_character_id', 'Der Ziel-Held konnte nicht gefunden werden.');
                        } elseif ((int) $awardCharacter->world_id !== (int) $campaign->world_id) {
                            $validator->errors()->add(
                                'inventory_award_character_id',
                                'Der Ziel-Held gehört nicht zur Welt dieser Kampagne.'
                            );
                        } elseif ((int) $awardCharacter->user_id <= 0) {
                            $validator->errors()->add(
                                'inventory_award_character_id',
                                'Der Ziel-Held muss ein aktiver Teilnehmer dieser Kampagne sein.'
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
            'post_mode' => 'Beitragsmodus',
            'ic_quote' => 'IC-Zitat',
            'inventory_award_character_id' => 'Ziel-Held (Inventar-Fund)',
            'inventory_award_item' => 'Inventar-Fund',
            'inventory_award_quantity' => 'Menge (Inventar-Fund)',
            'inventory_award_equipped' => 'Ausgerüstet (Inventar-Fund)',
        ];
    }

    private function campaignParticipantResolver(): CampaignParticipantResolver
    {
        /** @var CampaignParticipantResolver $resolver */
        $resolver = app(CampaignParticipantResolver::class);

        return $resolver;
    }
}
