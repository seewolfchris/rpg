<?php

namespace App\Actions\StoryLog;

use App\Models\StoryLogEntry;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

class UpdateStoryLogEntryAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(StoryLogEntry $storyLogEntry, User $actor, array $data): StoryLogEntry
    {
        $this->db->transaction(function () use ($storyLogEntry, $actor, $data): void {
            $storyLogEntry->update([
                'scene_id' => isset($data['scene_id']) ? (int) $data['scene_id'] : null,
                'updated_by' => (int) $actor->id,
                'title' => (string) ($data['title'] ?? ''),
                'body' => $data['body'] ?? null,
                'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : null,
            ]);
        });

        $storyLogEntry->load(['campaign.world', 'scene', 'createdBy', 'updatedBy']);

        return $storyLogEntry;
    }
}
