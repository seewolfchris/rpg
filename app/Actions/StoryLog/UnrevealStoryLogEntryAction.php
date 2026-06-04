<?php

namespace App\Actions\StoryLog;

use App\Domain\StoryLog\StoryLogEntryMutationService;
use App\Models\StoryLogEntry;
use App\Models\User;

final class UnrevealStoryLogEntryAction
{
    public function __construct(
        private readonly StoryLogEntryMutationService $mutationService,
    ) {}

    public function execute(StoryLogEntry $storyLogEntry, User $actor): StoryLogEntry
    {
        return $this->mutationService->unreveal($storyLogEntry, $actor);
    }
}
