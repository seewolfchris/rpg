<?php

namespace App\Http\Requests\Post;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\DiceRoll;
use App\Models\Post;
use App\Models\Scene;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PreviewProbeRollRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $scene = $this->route('scene');

        if (! $user || ! $scene instanceof Scene) {
            return false;
        }

        /** @var Campaign|null $campaign */
        $campaign = $scene->campaign;

        return $user->can('create', [Post::class, $scene])
            && $campaign instanceof Campaign
            && $campaign->canModeratePosts($user);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'probe_character_id' => ['required', 'integer', 'exists:characters,id'],
            'probe_roll_mode' => ['required', Rule::in(DiceRoll::ALLOWED_MODES)],
            'probe_modifier' => ['nullable', 'integer', 'between:-40,40'],
            'probe_attribute_key' => ['required', Rule::in($this->probeAttributeKeys())],
            'probe_explanation' => ['required', 'string', 'min:3', 'max:180'],
            'probe_le_delta' => ['nullable', 'integer', 'between:-200,200'],
            'probe_ae_delta' => ['nullable', 'integer', 'between:-200,200'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [
            'probe_explanation' => trim((string) $this->input('probe_explanation', '')),
        ];

        if (! $this->filled('probe_modifier')) {
            $normalized['probe_modifier'] = 0;
        }

        if (! $this->filled('probe_le_delta')) {
            $normalized['probe_le_delta'] = 0;
        }

        if (! $this->filled('probe_ae_delta')) {
            $normalized['probe_ae_delta'] = 0;
        }

        $this->merge($normalized);
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

            /** @var Campaign|null $campaign */
            $campaign = $scene->campaign;

            if (! $campaign instanceof Campaign) {
                $validator->errors()->add('scene', 'Kampagnen-Kontext konnte nicht geladen werden.');

                return;
            }

            $probeCharacterId = $this->filled('probe_character_id')
                ? (int) $this->input('probe_character_id')
                : 0;

            if ($probeCharacterId <= 0) {
                $validator->errors()->add('probe_character_id', 'Für die Probe muss ein Ziel-Held gewählt werden.');

                return;
            }

            /** @var Character|null $probeCharacter */
            $probeCharacter = Character::query()
                ->select(['id', 'user_id', 'world_id'])
                ->find($probeCharacterId);

            if (! $probeCharacter instanceof Character) {
                $validator->errors()->add('probe_character_id', 'Der Ziel-Held konnte nicht gefunden werden.');

                return;
            }

            $campaignParticipantUserIds = $this->campaignParticipantResolver()->participantUserIds($campaign);

            if ((int) $probeCharacter->world_id !== (int) $campaign->world_id) {
                $validator->errors()->add(
                    'probe_character_id',
                    'Der Ziel-Held gehört nicht zur Welt dieser Kampagne.'
                );

                return;
            }

            if ((int) $probeCharacter->user_id <= 0 || ! $campaignParticipantUserIds->contains((int) $probeCharacter->user_id)) {
                $validator->errors()->add(
                    'probe_character_id',
                    'Der Ziel-Held muss ein aktiver Teilnehmer dieser Kampagne sein.'
                );
            }
        });
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        if ($this->header('HX-Request') === 'true') {
            throw new HttpResponseException(response()
                ->view('posts.partials.probe-preview-error', [
                    'message' => (string) ($validator->errors()->first() ?: 'Probe konnte nicht gewürfelt werden. Bitte Eingaben prüfen.'),
                ], 422));
        }

        parent::failedValidation($validator);
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
            'probe_modifier' => 'Modifikator',
            'probe_roll_mode' => 'Modus',
            'probe_le_delta' => 'LE-Auswirkung',
            'probe_ae_delta' => 'AE-Auswirkung',
        ];
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

    private function campaignParticipantResolver(): CampaignParticipantResolver
    {
        /** @var CampaignParticipantResolver $resolver */
        $resolver = app(CampaignParticipantResolver::class);

        return $resolver;
    }
}
