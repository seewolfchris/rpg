<?php

declare(strict_types=1);

namespace App\Actions\Encyclopedia;

use App\Models\EncyclopediaCategory;
use App\Models\EncyclopediaEntry;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;

final class CreateEncyclopediaEntryAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(World $world, EncyclopediaCategory $category, User $actor, array $data): EncyclopediaEntry
    {
        /** @var EncyclopediaEntry $entry */
        $entry = $this->db->transaction(function () use ($world, $category, $actor, $data): EncyclopediaEntry {
            $lockedCategory = $this->lockAndVerifyContext($world, $category);
            $normalizedPublishedAt = $this->resolveAndValidatePublishedAt(
                status: (string) ($data['status'] ?? EncyclopediaEntry::STATUS_DRAFT),
                publishedAt: $data['published_at'] ?? null,
            );

            return $this->persistEntry($lockedCategory, $actor, $data, $normalizedPublishedAt);
        }, 3);

        return $entry;
    }

    private function lockAndVerifyContext(World $world, EncyclopediaCategory $category): EncyclopediaCategory
    {
        /** @var World $lockedWorld */
        $lockedWorld = World::query()
            ->whereKey((int) $world->id)
            ->lockForUpdate()
            ->firstOrFail();

        /** @var EncyclopediaCategory $lockedCategory */
        $lockedCategory = EncyclopediaCategory::query()
            ->whereKey((int) $category->id)
            ->where('world_id', (int) $lockedWorld->id)
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedCategory;
    }

    private function resolveAndValidatePublishedAt(string $status, mixed $publishedAt): Carbon|string|null
    {
        if ($status !== EncyclopediaEntry::STATUS_PUBLISHED) {
            return null;
        }

        if ($publishedAt === null || $publishedAt === '') {
            return now();
        }

        if ($publishedAt instanceof Carbon) {
            return $publishedAt;
        }

        return (string) $publishedAt;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistEntry(
        EncyclopediaCategory $category,
        User $actor,
        array $data,
        Carbon|string|null $publishedAt,
    ): EncyclopediaEntry {
        /** @var EncyclopediaEntry $entry */
        $entry = EncyclopediaEntry::query()->create(array_merge($data, [
            'encyclopedia_category_id' => (int) $category->id,
            'created_by' => (int) $actor->id,
            'updated_by' => (int) $actor->id,
            'published_at' => $publishedAt,
        ]));

        return $entry;
    }
}
