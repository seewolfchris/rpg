<?php

namespace App\Actions\StoryLog;

use App\Models\StoryLogEntry;
use Illuminate\Database\DatabaseManager;

class DeleteStoryLogEntryAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function execute(StoryLogEntry $storyLogEntry): void
    {
        $this->db->transaction(function () use ($storyLogEntry): void {
            $storyLogEntry->delete();
        });
    }
}
