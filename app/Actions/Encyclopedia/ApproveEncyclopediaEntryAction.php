<?php

declare(strict_types=1);

namespace App\Actions\Encyclopedia;

use App\Actions\Encyclopedia\Concerns\InteractsWithEncyclopediaWorldContext;
use App\Models\EncyclopediaEntry;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

final class ApproveEncyclopediaEntryAction
{
    use InteractsWithEncyclopediaWorldContext;

    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function execute(EncyclopediaEntry $entry, User $reviewer): void
    {
        $this->db->transaction(function () use ($entry, $reviewer): void {
            $lockedEntry = $this->lockAndVerifyPendingEntryContext($entry);

            $this->persistReviewTransition(
                $lockedEntry,
                $reviewer,
                $this->resolveApproveTransition($lockedEntry),
            );
        }, 3);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveApproveTransition(EncyclopediaEntry $entry): array
    {
        $transition = [
            'status' => EncyclopediaEntry::STATUS_PUBLISHED,
        ];

        if (! $entry->published_at) {
            $transition['published_at'] = now();
        }

        return $transition;
    }
}
