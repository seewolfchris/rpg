<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Encyclopedia;

use App\Actions\Encyclopedia\StoreEncyclopediaProposalAction;
use App\Models\EncyclopediaCategory;
use App\Models\EncyclopediaEntry;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreEncyclopediaProposalActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_pending_proposal_in_world_context(): void
    {
        $world = World::factory()->create([
            'slug' => 'encyclopedia-store-world',
            'is_active' => true,
        ]);
        $actor = User::factory()->create();
        $category = EncyclopediaCategory::query()->create([
            'world_id' => $world->id,
            'name' => 'Sichtbare Kategorie',
            'slug' => 'sichtbare-kategorie',
            'summary' => 'Summary',
            'position' => 10,
            'is_public' => true,
        ]);

        $entry = app(StoreEncyclopediaProposalAction::class)->execute(
            category: $category,
            actor: $actor,
            data: [
                'encyclopedia_category_id' => (int) $category->id,
                'title' => 'Neuer Vorschlag',
                'slug' => 'neuer-vorschlag',
                'excerpt' => 'Kurzfassung',
                'content' => 'Volltext',
            ],
        );

        $this->assertInstanceOf(EncyclopediaEntry::class, $entry);
        $this->assertSame(EncyclopediaEntry::STATUS_PENDING, (string) $entry->status);

        $this->assertDatabaseHas('encyclopedia_entries', [
            'id' => $entry->id,
            'encyclopedia_category_id' => $category->id,
            'title' => 'Neuer Vorschlag',
            'slug' => 'neuer-vorschlag',
            'status' => EncyclopediaEntry::STATUS_PENDING,
            'position' => 0,
            'published_at' => null,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
            'reviewed_by' => null,
        ]);
    }

    public function test_it_throws_for_non_public_category(): void
    {
        $world = World::factory()->create([
            'slug' => 'encyclopedia-store-main',
            'is_active' => true,
        ]);
        $actor = User::factory()->create();
        $hiddenCategory = EncyclopediaCategory::query()->create([
            'world_id' => $world->id,
            'name' => 'Hidden',
            'slug' => 'hidden',
            'summary' => 'Hidden summary',
            'position' => 20,
            'is_public' => false,
        ]);

        $this->expectException(ModelNotFoundException::class);

        app(StoreEncyclopediaProposalAction::class)->execute(
            category: $hiddenCategory,
            actor: $actor,
            data: [
                'encyclopedia_category_id' => (int) $hiddenCategory->id,
                'title' => 'Nicht erlaubt',
                'slug' => 'nicht-erlaubt',
                'excerpt' => null,
                'content' => 'Hidden content',
            ],
        );
    }

    public function test_it_throws_for_tampered_category_context(): void
    {
        $world = World::factory()->create([
            'slug' => 'encyclopedia-store-main-world',
            'is_active' => true,
        ]);
        $foreignWorld = World::factory()->create([
            'slug' => 'encyclopedia-store-foreign',
            'is_active' => true,
        ]);
        $actor = User::factory()->create();
        $category = EncyclopediaCategory::query()->create([
            'world_id' => $world->id,
            'name' => 'Original',
            'slug' => 'original',
            'summary' => 'Summary',
            'position' => 30,
            'is_public' => true,
        ]);

        $category->setAttribute('world_id', (int) $foreignWorld->id);

        $this->expectException(ModelNotFoundException::class);

        app(StoreEncyclopediaProposalAction::class)->execute(
            category: $category,
            actor: $actor,
            data: [
                'encyclopedia_category_id' => (int) $category->id,
                'title' => 'Fremde Welt',
                'slug' => 'fremde-welt',
                'excerpt' => null,
                'content' => 'Foreign content',
            ],
        );
    }
}
