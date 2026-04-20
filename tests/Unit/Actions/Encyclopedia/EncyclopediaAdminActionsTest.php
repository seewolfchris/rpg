<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Encyclopedia;

use App\Actions\Encyclopedia\CreateEncyclopediaCategoryAction;
use App\Actions\Encyclopedia\CreateEncyclopediaEntryAction;
use App\Actions\Encyclopedia\DeleteEncyclopediaEntryAction;
use App\Actions\Encyclopedia\UpdateEncyclopediaCategoryAction;
use App\Actions\Encyclopedia\UpdateEncyclopediaEntryAction;
use App\Models\EncyclopediaCategory;
use App\Models\EncyclopediaEntry;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EncyclopediaAdminActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_category_persists_world_context_in_action(): void
    {
        $world = World::factory()->create();

        $category = app(CreateEncyclopediaCategoryAction::class)->execute($world, [
            'name' => 'Fraktionen',
            'slug' => 'fraktionen',
            'summary' => 'Machtblöcke',
            'position' => 10,
            'is_public' => true,
        ]);

        $this->assertDatabaseHas('encyclopedia_categories', [
            'id' => $category->id,
            'world_id' => $world->id,
            'slug' => 'fraktionen',
        ]);
    }

    public function test_update_category_throws_for_foreign_world_context(): void
    {
        $world = World::factory()->create();
        $otherWorld = World::factory()->create();
        $category = EncyclopediaCategory::query()->create([
            'world_id' => $world->id,
            'name' => 'Regionen',
            'slug' => 'regionen',
            'summary' => null,
            'position' => 0,
            'is_public' => true,
        ]);

        $this->expectException(ModelNotFoundException::class);

        app(UpdateEncyclopediaCategoryAction::class)->execute($otherWorld, $category, [
            'name' => 'Ungueltig',
        ]);
    }

    public function test_create_entry_sets_actor_fields_and_published_at_when_missing(): void
    {
        $world = World::factory()->create();
        $actor = User::factory()->gm()->create();
        $category = EncyclopediaCategory::query()->create([
            'world_id' => $world->id,
            'name' => 'Chroniken',
            'slug' => 'chroniken',
            'summary' => null,
            'position' => 0,
            'is_public' => true,
        ]);

        $entry = app(CreateEncyclopediaEntryAction::class)->execute($world, $category, $actor, [
            'title' => 'Erster Funken',
            'slug' => 'erster-funken',
            'excerpt' => 'Kurz',
            'content' => 'Inhalt',
            'status' => EncyclopediaEntry::STATUS_PUBLISHED,
            'position' => 10,
            'published_at' => null,
            'game_relevance' => null,
        ]);

        $this->assertDatabaseHas('encyclopedia_entries', [
            'id' => $entry->id,
            'encyclopedia_category_id' => $category->id,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
            'status' => EncyclopediaEntry::STATUS_PUBLISHED,
        ]);
        $this->assertNotNull($entry->fresh()?->published_at);
    }

    public function test_update_entry_resets_published_at_when_status_is_not_published(): void
    {
        $world = World::factory()->create();
        $actor = User::factory()->gm()->create();
        $category = EncyclopediaCategory::query()->create([
            'world_id' => $world->id,
            'name' => 'Chroniken',
            'slug' => 'chroniken',
            'summary' => null,
            'position' => 0,
            'is_public' => true,
        ]);
        $entry = EncyclopediaEntry::query()->create([
            'encyclopedia_category_id' => $category->id,
            'title' => 'Alt',
            'slug' => 'alt',
            'excerpt' => 'Alt',
            'content' => 'Alt',
            'status' => EncyclopediaEntry::STATUS_PUBLISHED,
            'position' => 1,
            'published_at' => now(),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        app(UpdateEncyclopediaEntryAction::class)->execute($world, $category, $entry, $actor, [
            'title' => 'Neu',
            'slug' => 'neu',
            'excerpt' => 'Neu',
            'content' => 'Neu',
            'status' => EncyclopediaEntry::STATUS_DRAFT,
            'position' => 2,
            'published_at' => now()->addDay(),
            'game_relevance' => null,
        ]);

        $this->assertDatabaseHas('encyclopedia_entries', [
            'id' => $entry->id,
            'title' => 'Neu',
            'status' => EncyclopediaEntry::STATUS_DRAFT,
            'updated_by' => $actor->id,
            'published_at' => null,
        ]);
    }

    public function test_delete_entry_enforces_category_world_context(): void
    {
        $world = World::factory()->create();
        $otherWorld = World::factory()->create();

        $category = EncyclopediaCategory::query()->create([
            'world_id' => $world->id,
            'name' => 'Chroniken',
            'slug' => 'chroniken',
            'summary' => null,
            'position' => 0,
            'is_public' => true,
        ]);
        $foreignCategory = EncyclopediaCategory::query()->create([
            'world_id' => $otherWorld->id,
            'name' => 'Fremd',
            'slug' => 'fremd',
            'summary' => null,
            'position' => 0,
            'is_public' => true,
        ]);
        $entry = EncyclopediaEntry::query()->create([
            'encyclopedia_category_id' => $category->id,
            'title' => 'Alt',
            'slug' => 'alt',
            'excerpt' => null,
            'content' => 'Alt',
            'status' => EncyclopediaEntry::STATUS_DRAFT,
            'position' => 1,
            'published_at' => null,
            'created_by' => null,
            'updated_by' => null,
        ]);

        $this->expectException(ModelNotFoundException::class);

        app(DeleteEncyclopediaEntryAction::class)->execute($otherWorld, $foreignCategory, $entry);
    }
}
