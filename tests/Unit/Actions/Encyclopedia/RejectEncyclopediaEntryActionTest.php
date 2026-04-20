<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Encyclopedia;

use App\Actions\Encyclopedia\RejectEncyclopediaEntryAction;
use App\Models\EncyclopediaCategory;
use App\Models\EncyclopediaEntry;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RejectEncyclopediaEntryActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_pending_entry_and_clears_published_at(): void
    {
        [, $reviewer, $entry] = $this->seedPendingEntryWithPublishedAt();

        app(RejectEncyclopediaEntryAction::class)->execute(
            entry: $entry,
            reviewer: $reviewer,
        );

        $entry->refresh();

        $this->assertSame(EncyclopediaEntry::STATUS_REJECTED, (string) $entry->status);
        $this->assertSame((int) $reviewer->id, (int) $entry->updated_by);
        $this->assertSame((int) $reviewer->id, (int) $entry->reviewed_by);
        $this->assertNotNull($entry->reviewed_at);
        $this->assertNull($entry->published_at);
    }

    public function test_it_throws_for_tampered_entry_context(): void
    {
        [, $reviewer, $entry] = $this->seedPendingEntryWithPublishedAt();
        $foreignWorld = World::factory()->create([
            'slug' => 'encyclopedia-reject-foreign',
            'is_active' => true,
        ]);
        $foreignCategory = EncyclopediaCategory::query()->create([
            'world_id' => $foreignWorld->id,
            'name' => 'Fremd',
            'slug' => 'fremd',
            'summary' => 'Foreign summary',
            'position' => 40,
            'is_public' => true,
        ]);

        $entry->setAttribute('encyclopedia_category_id', (int) $foreignCategory->id);

        $this->expectException(ModelNotFoundException::class);

        try {
            app(RejectEncyclopediaEntryAction::class)->execute(
                entry: $entry,
                reviewer: $reviewer,
            );
        } finally {
            $entry->refresh();
            $this->assertSame(EncyclopediaEntry::STATUS_PENDING, (string) $entry->status);
        }
    }

    /**
     * @return array{0: World, 1: User, 2: EncyclopediaEntry}
     */
    private function seedPendingEntryWithPublishedAt(): array
    {
        $world = World::factory()->create([
            'slug' => 'encyclopedia-reject-world',
            'is_active' => true,
        ]);
        $author = User::factory()->create();
        $reviewer = User::factory()->gm()->create();
        $category = EncyclopediaCategory::query()->create([
            'world_id' => $world->id,
            'name' => 'Reject Kategorie',
            'slug' => 'reject-kategorie',
            'summary' => 'Summary',
            'position' => 10,
            'is_public' => true,
        ]);
        $entry = EncyclopediaEntry::query()->create([
            'encyclopedia_category_id' => $category->id,
            'title' => 'Pending Entry',
            'slug' => 'pending-entry-reject',
            'excerpt' => 'Pending Excerpt',
            'content' => 'Pending Content',
            'status' => EncyclopediaEntry::STATUS_PENDING,
            'position' => 0,
            'published_at' => now()->subDay(),
            'created_by' => $author->id,
            'updated_by' => $author->id,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        return [$world, $reviewer, $entry];
    }
}
