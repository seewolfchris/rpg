<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Encyclopedia;

use App\Actions\Encyclopedia\ApproveEncyclopediaEntryAction;
use App\Models\EncyclopediaCategory;
use App\Models\EncyclopediaEntry;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApproveEncyclopediaEntryActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_publishes_pending_entry_and_sets_review_metadata(): void
    {
        [, $reviewer, $entry] = $this->seedPendingEntry();

        app(ApproveEncyclopediaEntryAction::class)->execute(
            entry: $entry,
            reviewer: $reviewer,
        );

        $entry->refresh();

        $this->assertSame(EncyclopediaEntry::STATUS_PUBLISHED, (string) $entry->status);
        $this->assertSame((int) $reviewer->id, (int) $entry->updated_by);
        $this->assertSame((int) $reviewer->id, (int) $entry->reviewed_by);
        $this->assertNotNull($entry->reviewed_at);
        $this->assertNotNull($entry->published_at);
    }

    public function test_it_keeps_existing_published_at_timestamp(): void
    {
        [, $reviewer, $entry] = $this->seedPendingEntry();
        $originalPublishedAt = now()->subDays(3);
        $entry->update([
            'published_at' => $originalPublishedAt,
        ]);

        app(ApproveEncyclopediaEntryAction::class)->execute(
            entry: $entry,
            reviewer: $reviewer,
        );

        $entry->refresh();

        $this->assertNotNull($entry->published_at);
        $this->assertSame(
            $originalPublishedAt->toDateTimeString(),
            $entry->published_at?->toDateTimeString(),
        );
    }

    public function test_it_throws_for_tampered_entry_context(): void
    {
        [, $reviewer, $entry] = $this->seedPendingEntry();
        $foreignWorld = World::factory()->create([
            'slug' => 'encyclopedia-approve-foreign',
            'is_active' => true,
        ]);
        $foreignCategory = EncyclopediaCategory::query()->create([
            'world_id' => $foreignWorld->id,
            'name' => 'Fremd',
            'slug' => 'fremd',
            'summary' => 'Foreign summary',
            'position' => 30,
            'is_public' => true,
        ]);

        $entry->setAttribute('encyclopedia_category_id', (int) $foreignCategory->id);

        $this->expectException(ModelNotFoundException::class);
        app(ApproveEncyclopediaEntryAction::class)->execute(
            entry: $entry,
            reviewer: $reviewer,
        );
    }

    public function test_it_throws_for_non_pending_entry(): void
    {
        [, $reviewer, $entry] = $this->seedPendingEntry();

        $entry->update([
            'status' => EncyclopediaEntry::STATUS_REJECTED,
        ]);

        $this->expectException(ModelNotFoundException::class);
        app(ApproveEncyclopediaEntryAction::class)->execute(
            entry: $entry,
            reviewer: $reviewer,
        );
    }

    /**
     * @return array{0: World, 1: User, 2: EncyclopediaEntry}
     */
    private function seedPendingEntry(): array
    {
        $world = World::factory()->create([
            'slug' => 'encyclopedia-approve-world',
            'is_active' => true,
        ]);
        $author = User::factory()->create();
        $reviewer = User::factory()->gm()->create();
        $category = EncyclopediaCategory::query()->create([
            'world_id' => $world->id,
            'name' => 'Review Kategorie',
            'slug' => 'review-kategorie',
            'summary' => 'Summary',
            'position' => 10,
            'is_public' => true,
        ]);
        $entry = EncyclopediaEntry::query()->create([
            'encyclopedia_category_id' => $category->id,
            'title' => 'Pending Entry',
            'slug' => 'pending-entry',
            'excerpt' => 'Pending Excerpt',
            'content' => 'Pending Content',
            'status' => EncyclopediaEntry::STATUS_PENDING,
            'position' => 0,
            'published_at' => null,
            'created_by' => $author->id,
            'updated_by' => $author->id,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        return [$world, $reviewer, $entry];
    }
}
