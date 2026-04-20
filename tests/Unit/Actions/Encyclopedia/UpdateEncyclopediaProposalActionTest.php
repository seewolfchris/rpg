<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Encyclopedia;

use App\Actions\Encyclopedia\UpdateEncyclopediaProposalAction;
use App\Models\EncyclopediaCategory;
use App\Models\EncyclopediaEntry;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class UpdateEncyclopediaProposalActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_writes_revision_and_resubmits_pending(): void
    {
        [$world, $actor, $entry] = $this->seedEntryContext();
        $newCategory = EncyclopediaCategory::query()->create([
            'world_id' => $world->id,
            'name' => 'Neue Kategorie',
            'slug' => 'neue-kategorie',
            'summary' => 'Neu',
            'position' => 40,
            'is_public' => true,
        ]);

        app(UpdateEncyclopediaProposalAction::class)->execute(
            entry: $entry,
            actor: $actor,
            data: [
                'encyclopedia_category_id' => (int) $newCategory->id,
                'title' => 'Neuer Titel',
                'slug' => 'neuer-titel',
                'excerpt' => 'Neuer Auszug',
                'content' => 'Neuer Inhalt',
            ],
        );

        $entry->refresh();

        $this->assertSame((int) $newCategory->id, (int) $entry->encyclopedia_category_id);
        $this->assertSame('Neuer Titel', (string) $entry->title);
        $this->assertSame('neuer-titel', (string) $entry->slug);
        $this->assertSame('Neuer Auszug', (string) $entry->excerpt);
        $this->assertSame('Neuer Inhalt', (string) $entry->content);
        $this->assertSame(EncyclopediaEntry::STATUS_PENDING, (string) $entry->status);
        $this->assertSame(0, (int) $entry->position);
        $this->assertNull($entry->published_at);
        $this->assertNull($entry->reviewed_by);
        $this->assertNull($entry->reviewed_at);

        $this->assertDatabaseHas('encyclopedia_entry_revisions', [
            'encyclopedia_entry_id' => $entry->id,
            'editor_id' => $actor->id,
            'title_before' => 'Alter Titel',
            'excerpt_before' => 'Alter Auszug',
            'content_before' => 'Alter Inhalt',
            'status_before' => EncyclopediaEntry::STATUS_REJECTED,
        ]);
    }

    public function test_it_throws_for_tampered_entry_context(): void
    {
        [, $actor, $entry] = $this->seedEntryContext();
        $foreignWorld = World::factory()->create([
            'slug' => 'encyclopedia-update-foreign',
            'is_active' => true,
        ]);
        $foreignCategory = EncyclopediaCategory::query()->create([
            'world_id' => $foreignWorld->id,
            'name' => 'Fremd',
            'slug' => 'fremd',
            'summary' => 'Foreign summary',
            'position' => 50,
            'is_public' => true,
        ]);

        $entry->setAttribute('encyclopedia_category_id', (int) $foreignCategory->id);

        $this->expectException(ModelNotFoundException::class);

        try {
            app(UpdateEncyclopediaProposalAction::class)->execute(
                entry: $entry,
                actor: $actor,
                data: [
                    'encyclopedia_category_id' => (int) $foreignCategory->id,
                    'title' => 'Nicht gespeichert',
                    'slug' => 'nicht-gespeichert',
                    'excerpt' => null,
                    'content' => 'Nicht gespeichert',
                ],
            );
        } finally {
            $this->assertDatabaseHas('encyclopedia_entries', [
                'id' => $entry->id,
                'title' => 'Alter Titel',
            ]);
        }
    }

    public function test_it_rolls_back_when_transaction_fails_after_mutation(): void
    {
        [, $actor, $entry] = $this->seedEntryContext();

        $realDb = app(DatabaseManager::class);
        $mockedDb = Mockery::mock(DatabaseManager::class);
        $mockedDb->shouldReceive('transaction')
            ->once()
            ->withArgs(static fn (mixed $callback, mixed $attempts): bool => is_callable($callback) && $attempts === 3)
            ->andReturnUsing(function (callable $callback) use ($realDb): mixed {
                $connection = $realDb->connection();
                $connection->beginTransaction();

                try {
                    $callback();

                    throw new RuntimeException('Forced proposal update failure');
                } catch (Throwable $throwable) {
                    $connection->rollBack();

                    throw $throwable;
                }
            });

        $action = new UpdateEncyclopediaProposalAction($mockedDb);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forced proposal update failure');

        try {
            $action->execute(
                entry: $entry,
                actor: $actor,
                data: [
                    'encyclopedia_category_id' => (int) $entry->encyclopedia_category_id,
                    'title' => 'Rollback Titel',
                    'slug' => 'rollback-titel',
                    'excerpt' => 'Rollback',
                    'content' => 'Rollback Inhalt',
                ],
            );
        } finally {
            $this->assertDatabaseHas('encyclopedia_entries', [
                'id' => $entry->id,
                'title' => 'Alter Titel',
                'status' => EncyclopediaEntry::STATUS_REJECTED,
            ]);
            $this->assertDatabaseMissing('encyclopedia_entry_revisions', [
                'encyclopedia_entry_id' => $entry->id,
                'title_before' => 'Alter Titel',
            ]);
        }
    }

    /**
     * @return array{0: World, 1: User, 2: EncyclopediaEntry}
     */
    private function seedEntryContext(): array
    {
        $world = World::factory()->create([
            'slug' => 'encyclopedia-update-world',
            'is_active' => true,
        ]);
        $actor = User::factory()->create();
        $reviewer = User::factory()->gm()->create();
        $category = EncyclopediaCategory::query()->create([
            'world_id' => $world->id,
            'name' => 'Ausgangskategorie',
            'slug' => 'ausgangskategorie',
            'summary' => 'Start',
            'position' => 10,
            'is_public' => true,
        ]);
        $entry = EncyclopediaEntry::query()->create([
            'encyclopedia_category_id' => $category->id,
            'title' => 'Alter Titel',
            'slug' => 'alter-titel',
            'excerpt' => 'Alter Auszug',
            'content' => 'Alter Inhalt',
            'status' => EncyclopediaEntry::STATUS_REJECTED,
            'position' => 5,
            'published_at' => null,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now()->subDay(),
        ]);

        return [$world, $actor, $entry];
    }
}
