<?php

declare(strict_types=1);

namespace App\Actions\Encyclopedia;

use App\Actions\Encyclopedia\Concerns\InteractsWithEncyclopediaWorldContext;
use App\Models\EncyclopediaCategory;
use App\Models\EncyclopediaEntry;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

final class StoreEncyclopediaProposalAction
{
    use InteractsWithEncyclopediaWorldContext;

    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array{
     *   encyclopedia_category_id?: int|string,
     *   title: string,
     *   slug: string,
     *   excerpt?: string|null,
     *   content: string
     * }  $data
     */
    public function execute(EncyclopediaCategory $category, User $actor, array $data): EncyclopediaEntry
    {
        /** @var EncyclopediaEntry $entry */
        $entry = $this->db->transaction(function () use ($category, $actor, $data): EncyclopediaEntry {
            $lockedCategory = $this->lockAndVerifyPublicCategory($category);

            return $this->persistProposal($lockedCategory, $actor, $data);
        }, 3);

        return $entry;
    }

    /**
     * @param  array{
     *   title: string,
     *   slug: string,
     *   excerpt?: string|null,
     *   content: string
     * }  $data
     */
    private function persistProposal(EncyclopediaCategory $category, User $actor, array $data): EncyclopediaEntry
    {
        /** @var EncyclopediaEntry $entry */
        $entry = EncyclopediaEntry::query()->create([
            'encyclopedia_category_id' => (int) $category->id,
            'title' => (string) $data['title'],
            'slug' => (string) $data['slug'],
            'excerpt' => $this->normalizeExcerpt($data['excerpt'] ?? null),
            'content' => (string) $data['content'],
            'status' => EncyclopediaEntry::STATUS_PENDING,
            'position' => 0,
            'published_at' => null,
            'created_by' => (int) $actor->id,
            'updated_by' => (int) $actor->id,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        return $entry;
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
