<?php

namespace App\Actions\StoryLog;

use App\Models\Campaign;
use App\Models\StoryLogEntry;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

class StoreStoryLogEntryAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Campaign $campaign, User $actor, array $data): StoryLogEntry
    {
        /** @var StoryLogEntry $storyLogEntry */
        $storyLogEntry = $this->db->transaction(function () use ($campaign, $actor, $data): StoryLogEntry {
            /** @var StoryLogEntry $createdStoryLogEntry */
            $createdStoryLogEntry = StoryLogEntry::query()->create([
                'campaign_id' => (int) $campaign->id,
                'scene_id' => isset($data['scene_id']) ? (int) $data['scene_id'] : null,
                'created_by' => (int) $actor->id,
                'updated_by' => null,
                'title' => (string) ($data['title'] ?? ''),
                'body' => $data['body'] ?? null,
                'revealed_at' => null,
                'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : null,
            ]);

            return $createdStoryLogEntry;
        });

        $storyLogEntry->load(['campaign.world', 'scene', 'createdBy', 'updatedBy']);

        return $storyLogEntry;
    }
}
