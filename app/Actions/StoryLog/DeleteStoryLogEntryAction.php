<?php

namespace App\Actions\StoryLog;

use App\Domain\StoryLog\StoryLogEntryMutationService;
use App\Models\StoryLogEntry;

class DeleteStoryLogEntryAction
{
    public function __construct(
        private readonly StoryLogEntryMutationService $mutationService,
    ) {}

    public function execute(StoryLogEntry $storyLogEntry): void
    {
        $this->mutationService->delete($storyLogEntry);
    }
}
