<?php

declare(strict_types=1);

namespace App\Http\Requests\Scene;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Domain\Magic\MagicService;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\DiceRoll;
use App\Models\Scene;
use App\Models\SceneConflictActor;
use App\Support\SensitiveFeatureGate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSceneMagicActionRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'actor_conflict_actor_id' => ['nullable', 'integer', 'exists:scene_conflict_actors,id'],
            'actor_type' => ['required', Rule::in(['character', 'npc'])],
            'actor_character_id' => ['nullable', 'integer', 'exists:characters,id', Rule::requiredIf(function (): bool {
                return ! $this->filled('actor_conflict_actor_id') && (string) $this->input('actor_type') === 'character';
            })],
            'actor_name' => ['nullable', 'string', 'max:120', Rule::requiredIf(function (): bool {
                return ! $this->filled('actor_conflict_actor_id') && (string) $this->input('actor_type') === 'npc';
            })],
            'actor_ae_current' => ['nullable', 'integer', 'between:0,999'],
            'actor_ae_max' => ['nullable', 'integer', 'between:0,999'],

            'target_conflict_actor_id' => ['nullable', 'integer', 'exists:scene_conflict_actors,id'],
            'target_type' => ['required', Rule::in(['character', 'npc'])],
            'target_character_id' => ['nullable', 'integer', 'exists:characters,id', Rule::requiredIf(function (): bool {
                return ! $this->filled('target_conflict_actor_id') && (string) $this->input('target_type') === 'character';
            })],
            'target_name' => ['nullable', 'string', 'max:120', Rule::requiredIf(function (): bool {
                return ! $this->filled('target_conflict_actor_id') && (string) $this->input('target_type') === 'npc';
            })],
            'target_le_current' => ['nullable', 'integer', 'between:0,999'],
            'target_le_max' => ['nullable', 'integer', 'between:0,999'],
            'target_ae_current' => ['nullable', 'integer', 'between:0,999'],
            'target_ae_max' => ['nullable', 'integer', 'between:0,999'],

            'spell_name' => ['required', 'string', 'max:120'],
            'spell_target_value' => ['nullable', 'integer', 'between:0,100'],
            'spell_roll_mode' => ['nullable', Rule::in(DiceRoll::ALLOWED_MODES)],
            'spell_modifier' => ['nullable', 'integer', 'between:-100,100'],
            'ae_cost' => ['required', 'integer', 'between:0,999'],

            'defense_label' => ['nullable', 'string', 'max:80'],
            'defense_target_value' => ['nullable', 'integer', 'between:0,100'],
            'defense_roll_mode' => ['nullable', Rule::in(DiceRoll::ALLOWED_MODES)],
            'defense_modifier' => ['nullable', 'integer', 'between:-100,100'],

            'effect_type' => ['required', Rule::in([
                MagicService::EFFECT_LE_DAMAGE,
                MagicService::EFFECT_LE_HEAL,
                MagicService::EFFECT_AE_DAMAGE,
                MagicService::EFFECT_ATTRIBUTE_DELTA,
                MagicService::EFFECT_NARRATIVE,
            ])],
            'effect_amount' => ['nullable', 'integer', 'between:-999,999'],
            'target_attribute_key' => ['nullable', 'string', Rule::in(['mu', 'kl', 'in', 'ch', 'ff', 'ge', 'ko', 'kk']), 'required_if:effect_type,'.MagicService::EFFECT_ATTRIBUTE_DELTA],

            'intent_text' => ['nullable', 'string', 'max:500'],
            'resolution_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $actorType = strtolower(trim((string) $this->input('actor_type', '')));
        $targetType = strtolower(trim((string) $this->input('target_type', '')));
        $effectType = strtolower(trim((string) $this->input('effect_type', '')));

        $targetAttributeKey = $this->nullIfBlank($this->input('target_attribute_key'));
        if ($effectType !== MagicService::EFFECT_ATTRIBUTE_DELTA) {
            $targetAttributeKey = null;
        }

        $effectAmount = $this->filled('effect_amount')
            ? (int) $this->input('effect_amount')
            : 0;

        $this->merge([
            'actor_type' => $actorType,
            'target_type' => $targetType,
            'effect_type' => $effectType,
            'actor_conflict_actor_id' => $this->filled('actor_conflict_actor_id') ? (int) $this->input('actor_conflict_actor_id') : null,
            'target_conflict_actor_id' => $this->filled('target_conflict_actor_id') ? (int) $this->input('target_conflict_actor_id') : null,
            'actor_name' => $this->nullIfBlank($this->input('actor_name')),
            'target_name' => $this->nullIfBlank($this->input('target_name')),
            'spell_name' => $this->nullIfBlank($this->input('spell_name')),
            'defense_label' => $this->nullIfBlank($this->input('defense_label')),
            'intent_text' => $this->nullIfBlank($this->input('intent_text')),
            'resolution_note' => $this->nullIfBlank($this->input('resolution_note')),
            'spell_roll_mode' => $this->resolveRollMode('spell_roll_mode'),
            'defense_roll_mode' => $this->resolveRollMode('defense_roll_mode'),
            'spell_modifier' => $this->filled('spell_modifier') ? (int) $this->input('spell_modifier') : 0,
            'defense_modifier' => $this->filled('defense_modifier') ? (int) $this->input('defense_modifier') : 0,
            'effect_amount' => $effectAmount,
            'target_attribute_key' => $targetAttributeKey,
            'actor_ae_current' => $this->filled('actor_ae_current') ? (int) $this->input('actor_ae_current') : null,
            'actor_ae_max' => $this->filled('actor_ae_max') ? (int) $this->input('actor_ae_max') : null,
            'target_le_current' => $this->filled('target_le_current') ? (int) $this->input('target_le_current') : null,
            'target_le_max' => $this->filled('target_le_max') ? (int) $this->input('target_le_max') : null,
            'target_ae_current' => $this->filled('target_ae_current') ? (int) $this->input('target_ae_current') : null,
            'target_ae_max' => $this->filled('target_ae_max') ? (int) $this->input('target_ae_max') : null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Scene|null $scene */
            $scene = $this->route('scene');

            if (! $scene instanceof Scene) {
                $validator->errors()->add('scene', 'Szene konnte nicht gefunden werden.');

                return;
            }

            /** @var Campaign $campaign */
            $campaign = $scene->campaign;
            $resolver = $this->campaignParticipantResolver();
            $participantUserIds = $resolver->participantUserIds($campaign);
            $actorConflictActor = $this->resolveConflictActor(
                validator: $validator,
                scene: $scene,
                field: 'actor_conflict_actor_id',
            );
            $this->resolveConflictActor(
                validator: $validator,
                scene: $scene,
                field: 'target_conflict_actor_id',
            );

            $this->validateCharacterContext(
                validator: $validator,
                campaign: $campaign,
                participantUserIds: $participantUserIds,
                fieldPrefix: 'actor',
            );

            $this->validateCharacterContext(
                validator: $validator,
                campaign: $campaign,
                participantUserIds: $participantUserIds,
                fieldPrefix: 'target',
            );

            if (! $this->filled('spell_target_value') && (! $actorConflictActor instanceof SceneConflictActor || $actorConflictActor->spell_value === null)) {
                $validator->errors()->add('spell_target_value', 'Der Zauberwert ist erforderlich.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'actor_type' => 'Zaubernder-Typ',
            'actor_conflict_actor_id' => 'Zaubernder aus Szenenbeteiligten',
            'actor_character_id' => 'Zaubernder-Charakter',
            'actor_name' => 'Zaubernder-NPC Name',
            'target_type' => 'Ziel-Typ',
            'target_conflict_actor_id' => 'Ziel aus Szenenbeteiligten',
            'target_character_id' => 'Ziel-Charakter',
            'target_name' => 'Ziel-NPC Name',
            'spell_name' => 'Zaubername',
            'spell_target_value' => 'Zauberwert',
            'spell_roll_mode' => 'Wurfmodus (Zauber)',
            'spell_modifier' => 'Modifikator (Zauber)',
            'ae_cost' => 'AE-Kosten',
            'defense_label' => 'Magieabwehr-Bezeichnung',
            'defense_target_value' => 'Magieabwehr-Wert',
            'defense_roll_mode' => 'Wurfmodus (Magieabwehr)',
            'defense_modifier' => 'Modifikator (Magieabwehr)',
            'effect_type' => 'Effektart',
            'effect_amount' => 'Effektbetrag',
            'target_attribute_key' => 'Zielattribut',
            'intent_text' => 'Absicht',
            'resolution_note' => 'Auflösungsnotiz',
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int<1, max>>  $participantUserIds
     */
    private function validateCharacterContext(
        Validator $validator,
        Campaign $campaign,
        \Illuminate\Support\Collection $participantUserIds,
        string $fieldPrefix,
    ): void {
        $type = (string) $this->input($fieldPrefix.'_type', '');
        if ($this->filled($fieldPrefix.'_conflict_actor_id')) {
            return;
        }

        if ($type !== 'character') {
            return;
        }

        $characterId = $this->filled($fieldPrefix.'_character_id')
            ? (int) $this->input($fieldPrefix.'_character_id')
            : 0;

        if ($characterId <= 0) {
            return;
        }

        /** @var Character|null $character */
        $character = Character::query()
            ->select(['id', 'user_id', 'world_id'])
            ->find($characterId);

        if (! $character instanceof Character) {
            $validator->errors()->add(
                $fieldPrefix.'_character_id',
                $fieldPrefix === 'actor'
                    ? 'Der Zauberer-Charakter konnte nicht gefunden werden.'
                    : 'Der Ziel-Charakter konnte nicht gefunden werden.'
            );

            return;
        }

        if ((int) $character->world_id !== (int) $campaign->world_id) {
            $validator->errors()->add(
                $fieldPrefix.'_character_id',
                $fieldPrefix === 'actor'
                    ? 'Der Zauberer-Charakter gehört nicht zur Welt dieser Kampagne.'
                    : 'Der Ziel-Charakter gehört nicht zur Welt dieser Kampagne.'
            );

            return;
        }

        $characterUserId = (int) $character->user_id;
        if ($characterUserId <= 0 || ! $participantUserIds->contains($characterUserId)) {
            $validator->errors()->add(
                $fieldPrefix.'_character_id',
                $fieldPrefix === 'actor'
                    ? 'Der Zauberer-Charakter muss ein aktiver Teilnehmer dieser Kampagne sein.'
                    : 'Der Ziel-Charakter muss ein aktiver Teilnehmer dieser Kampagne sein.'
            );
        }
    }

    private function resolveConflictActor(Validator $validator, Scene $scene, string $field): ?SceneConflictActor
    {
        $actorId = $this->filled($field) ? (int) $this->input($field) : 0;
        if ($actorId <= 0) {
            return null;
        }

        /** @var SceneConflictActor|null $conflictActor */
        $conflictActor = SceneConflictActor::query()
            ->select(['id', 'scene_id', 'campaign_id', 'actor_type', 'spell_value', 'defense_value'])
            ->find($actorId);

        if (! $conflictActor instanceof SceneConflictActor || (int) $conflictActor->scene_id !== (int) $scene->id) {
            $validator->errors()->add($field, 'Der gewählte Szenenbeteiligte gehört nicht zu dieser Szene.');

            return null;
        }

        return $conflictActor;
    }

    private function resolveRollMode(string $field): string
    {
        $raw = strtolower(trim((string) $this->input($field, '')));

        if ($raw === '') {
            return DiceRoll::MODE_NORMAL;
        }

        return in_array($raw, DiceRoll::ALLOWED_MODES, true)
            ? $raw
            : DiceRoll::MODE_NORMAL;
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
