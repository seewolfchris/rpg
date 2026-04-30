<?php

namespace App\Actions\PlayerNote;

use App\Models\Campaign;
use App\Models\Character;
use App\Models\PlayerNote;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

class StorePlayerNoteAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @throws ValidationException
     */
    public function execute(Campaign $campaign, User $actor, array $data): PlayerNote
    {
        $sceneId = $this->validatedSceneId($campaign, $data);
        $characterId = $this->validatedCharacterId($campaign, $actor, $data);

        /** @var PlayerNote $playerNote */
        $playerNote = $this->db->transaction(function () use ($campaign, $actor, $data, $sceneId, $characterId): PlayerNote {
            /** @var PlayerNote $createdPlayerNote */
            $createdPlayerNote = PlayerNote::query()->create([
                'user_id' => (int) $actor->id,
                'campaign_id' => (int) $campaign->id,
                'scene_id' => $sceneId,
                'character_id' => $characterId,
                'title' => (string) ($data['title'] ?? ''),
                'body' => $data['body'] ?? null,
                'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : null,
            ]);

            return $createdPlayerNote;
        });

        $playerNote->load(['user', 'campaign.world', 'scene', 'character']);

        return $playerNote;
    }

    /**
     * @param  array<string, mixed>  $data
     * @throws ValidationException
     */
    private function validatedSceneId(Campaign $campaign, array $data): ?int
    {
        $sceneId = isset($data['scene_id']) ? (int) $data['scene_id'] : null;

        if ($sceneId === null || $sceneId <= 0) {
            return null;
        }

        $validScene = Scene::query()
            ->whereKey($sceneId)
            ->where('campaign_id', (int) $campaign->id)
            ->exists();

        if (! $validScene) {
            throw ValidationException::withMessages([
                'scene_id' => 'Die gewählte Szene gehört nicht zu dieser Kampagne.',
            ]);
        }

        return $sceneId;
    }

    /**
     * @param  array<string, mixed>  $data
     * @throws ValidationException
     */
    private function validatedCharacterId(Campaign $campaign, User $actor, array $data): ?int
    {
        $characterId = isset($data['character_id']) ? (int) $data['character_id'] : null;

        if ($characterId === null || $characterId <= 0) {
            return null;
        }

        $validCharacter = Character::query()
            ->whereKey($characterId)
            ->where('user_id', (int) $actor->id)
            ->where('world_id', (int) $campaign->world_id)
            ->exists();

        if (! $validCharacter) {
            throw ValidationException::withMessages([
                'character_id' => 'Der gewählte Charakter ist für diese Kampagne nicht zulässig.',
            ]);
        }

        return $characterId;
    }
}
