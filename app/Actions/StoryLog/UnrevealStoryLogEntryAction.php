<?php

namespace App\Actions\StoryLog;

use App\Models\StoryLogEntry;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

class UnrevealStoryLogEntryAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function execute(StoryLogEntry $storyLogEntry, User $actor): StoryLogEntry
    {
        $this->db->transaction(function () use ($storyLogEntry, $actor): void {
            $storyLogEntry->update([
                'revealed_at' => null,
                'updated_by' => (int) $actor->id,
            ]);
        });

        return $storyLogEntry;
    }
}
