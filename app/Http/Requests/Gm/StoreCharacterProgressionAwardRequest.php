<?php

namespace App\Http\Requests\Gm;

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Character;
use App\Models\Scene;
use App\Models\World;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCharacterProgressionAwardRequest extends FormRequest
{
    private bool $campaignResolved = false;

    private ?Campaign $resolvedCampaign = null;

    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        if ($user->isGmOrAdmin()) {
            return true;
        }

        $campaign = $this->resolveCampaign();
        if (! $campaign) {
            return true;
        }

        return $campaign->isCoGm($user);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            'scene_id' => ['nullable', 'integer', 'exists:scenes,id'],
            'event_mode' => ['required', Rule::in(['milestone', 'correction'])],
            'reason' => ['nullable', 'string', 'max:500'],
            'awards' => ['required', 'array', 'min:1', 'max:120'],
            'awards.*.character_id' => ['required', 'integer', 'distinct', 'exists:characters,id'],
            'awards.*.xp_delta' => ['required', 'integer', 'not_in:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'campaign_id' => (int) $this->input('campaign_id'),
            'scene_id' => $this->filled('scene_id') ? (int) $this->input('scene_id') : null,
            'event_mode' => strtolower(trim((string) $this->input('event_mode', 'milestone'))),
            'reason' => trim((string) $this->input('reason', '')),
            'awards' => $this->normalizeAwards($this->input('awards')),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $campaign = $this->resolveCampaign();
            if (! $campaign) {
                $validator->errors()->add('campaign_id', 'Kampagne konnte nicht geladen werden.');

                return;
            }

            /** @var World|string|null $routeWorld */
            $routeWorld = $this->route('world');
            $routeWorldId = $routeWorld instanceof World ? (int) $routeWorld->id : null;
            if ($routeWorldId !== null && $routeWorldId !== (int) $campaign->world_id) {
                $validator->errors()->add('campaign_id', 'Kampagne gehört nicht zur gewählten Welt.');
            }

            $sceneId = (int) ($this->input('scene_id') ?? 0);
            if ($sceneId > 0) {
                $sceneBelongsToCampaign = Scene::query()
                    ->whereKey($sceneId)
                    ->where('campaign_id', $campaign->id)
                    ->exists();

                if (! $sceneBelongsToCampaign) {
                    $validator->errors()->add('scene_id', 'Szene gehört nicht zur gewählten Kampagne.');
                }
            }

            $eventMode = (string) $this->input('event_mode', 'milestone');
            $participantUserIds = $campaign->invitations()
                ->where('status', CampaignInvitation::STATUS_ACCEPTED)
                ->pluck('user_id')
                ->push((int) $campaign->owner_id)
                ->map(static fn ($id): int => (int) $id)
                ->unique();

            $awards = $this->normalizeAwards($this->input('awards', []));
            $characterIds = collect($awards)
                ->pluck('character_id')
                ->map(static fn ($id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->unique()
                ->values();

            /** @var \Illuminate\Support\Collection<int, Character> $characters */
            $characters = Character::query()
                ->whereIn('id', $characterIds)
                ->get(['id', 'user_id', 'world_id'])
                ->keyBy('id');

            foreach ($awards as $index => $award) {
                $characterId = (int) ($award['character_id'] ?? 0);
                $xpDelta = (int) ($award['xp_delta'] ?? 0);
                /** @var Character|null $character */
                $character = $characters->get($characterId);

                if (! $character) {
                    $validator->errors()->add('awards.'.$index.'.character_id', 'Charakter wurde nicht gefunden.');

                    continue;
                }

                if ((int) $character->world_id !== (int) $campaign->world_id) {
                    $validator->errors()->add('awards.'.$index.'.character_id', 'Charakter gehört nicht zur Kampagnen-Welt.');
                }

                if (! $participantUserIds->contains((int) $character->user_id)) {
                    $validator->errors()->add('awards.'.$index.'.character_id', 'Charakter ist kein aktiver Kampagnen-Teilnehmer.');
                }

                if ($eventMode === 'milestone' && $xpDelta <= 0) {
                    $validator->errors()->add('awards.'.$index.'.xp_delta', 'Für Meilensteine sind nur positive XP erlaubt.');
                }
            }
        });
    }

    /**
     * @return array<int, array{character_id: int, xp_delta: int}>
     */
    private function normalizeAwards(mixed $awards): array
    {
        if (! is_array($awards)) {
            return [];
        }

        $normalized = [];

        foreach ($awards as $award) {
            if (! is_array($award)) {
                continue;
            }

            $characterId = (int) ($award['character_id'] ?? 0);
            $xpDelta = (int) ($award['xp_delta'] ?? 0);

            if ($characterId <= 0 || $xpDelta === 0) {
                continue;
            }

            $normalized[] = [
                'character_id' => $characterId,
                'xp_delta' => $xpDelta,
            ];
        }

        return $normalized;
    }

    private function resolveCampaign(): ?Campaign
    {
        if ($this->campaignResolved) {
            return $this->resolvedCampaign;
        }

        $campaignId = (int) $this->input('campaign_id');
        $this->resolvedCampaign = $campaignId > 0
            ? Campaign::query()->find($campaignId)
            : null;
        $this->campaignResolved = true;

        return $this->resolvedCampaign;
    }
}
