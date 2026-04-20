<?php

declare(strict_types=1);

namespace App\Actions\Encyclopedia;

use App\Actions\Encyclopedia\Concerns\InteractsWithEncyclopediaWorldContext;
use App\Models\EncyclopediaCategory;
use App\Models\EncyclopediaEntry;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

final class UpdateEncyclopediaProposalAction
{
    use InteractsWithEncyclopediaWorldContext;

    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array{
     *   encyclopedia_category_id: int|string,
     *   title: string,
     *   slug: string,
     *   excerpt?: string|null,
     *   content: string
     * }  $data
     */
    public function execute(EncyclopediaEntry $entry, User $actor, array $data): void
    {
        $this->db->transaction(function () use ($entry, $actor, $data): void {
            $lockedEntry = $this->lockAndVerifyEntryContext($entry);
            $targetCategory = $this->resolveAndValidateTargetCategory(
                $lockedEntry,
                (int) $data['encyclopedia_category_id'],
            );

            $this->createRevisionSnapshot($lockedEntry, $actor);
            $this->persistPendingResubmission($lockedEntry, $targetCategory, $actor, $data);
        }, 3);
    }

    private function createRevisionSnapshot(EncyclopediaEntry $entry, User $actor): void
    {
        $entry->revisions()->create([
            'editor_id' => (int) $actor->id,
            'title_before' => $entry->title,
            'excerpt_before' => $entry->excerpt,
            'content_before' => $entry->content,
            'status_before' => $entry->status,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array{
     *   title: string,
     *   slug: string,
     *   excerpt?: string|null,
     *   content: string
     * }  $data
     */
    private function persistPendingResubmission(
        EncyclopediaEntry $entry,
        EncyclopediaCategory $category,
        User $actor,
        array $data,
    ): void {
        $entry->update([
            'encyclopedia_category_id' => (int) $category->id,
            'title' => (string) $data['title'],
            'slug' => (string) $data['slug'],
            'excerpt' => $this->normalizeExcerpt($data['excerpt'] ?? null),
            'content' => (string) $data['content'],
            'status' => EncyclopediaEntry::STATUS_PENDING,
            'position' => 0,
            'published_at' => null,
            'updated_by' => (int) $actor->id,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);
    }

    private function normalizeExcerpt(mixed $excerpt): ?string
    {
        if (! is_string($excerpt)) {
            return null;
        }

        $normalized = trim($excerpt);

        return $normalized !== '' ? $normalized : null;
    }
}
