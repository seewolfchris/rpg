<?php

declare(strict_types=1);

namespace App\Actions\Encyclopedia;

use App\Actions\Encyclopedia\Concerns\InteractsWithEncyclopediaWorldContext;
use App\Models\EncyclopediaEntry;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

final class RejectEncyclopediaEntryAction
{
    use InteractsWithEncyclopediaWorldContext;

    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function execute(EncyclopediaEntry $entry, User $reviewer): void
    {
        $this->db->transaction(function () use ($entry, $reviewer): void {
            $lockedEntry = $this->lockAndVerifyPendingEntryContext($entry);

            $this->persistReviewTransition($lockedEntry, $reviewer, [
                'status' => EncyclopediaEntry::STATUS_REJECTED,
                'published_at' => null,
            ]);
        }, 3);
    }
}
