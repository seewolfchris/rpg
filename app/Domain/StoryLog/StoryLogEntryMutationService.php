<?php

declare(strict_types=1);

namespace App\Domain\StoryLog;

use App\Models\StoryLogEntry;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

class StoryLogEntryMutationService
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function delete(StoryLogEntry $storyLogEntry): void
    {
        $this->db->transaction(function () use ($storyLogEntry): void {
            $storyLogEntry->delete();
        });
    }

    public function reveal(StoryLogEntry $storyLogEntry, User $actor): StoryLogEntry
    {
        $this->db->transaction(function () use ($storyLogEntry, $actor): void {
            $storyLogEntry->update([
                'revealed_at' => now(),
                'updated_by' => (int) $actor->id,
            ]);
        });

        return $storyLogEntry;
    }

    public function unreveal(StoryLogEntry $storyLogEntry, User $actor): StoryLogEntry
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
