<?php

namespace Tests\Feature;

use App\Models\EncyclopediaCategory;
use App\Models\EncyclopediaEntry;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EncyclopediaManagementTest extends TestCase
{
    use RefreshDatabase;

    private function defaultWorld(): World
    {
        return World::query()
            ->where('slug', (string) config('worlds.default_slug'))
            ->firstOrFail();
    }

    public function test_public_encyclopedia_shows_seeded_content(): void
    {
        $world = $this->defaultWorld();

        $this->get(route('knowledge.encyclopedia', ['world' => $world]))
            ->assertOk()
            ->assertSeeText('Enzyklopädie · '.$world->name)
            ->assertSeeText('Zeitalter der Sonnenkronen')
            ->assertSeeText('Mehr lesen');
    }

    public function test_public_encyclopedia_filters_by_query_and_category(): void
    {
        $world = $this->defaultWorld();

        $this->get(route('knowledge.encyclopedia', [
            'world' => $world,
            'q' => 'Schattenhaeuser',
        ]))
            ->assertOk()
            ->assertSeeText('Schattenhaeuser von Nerez')
            ->assertDontSeeText('Zeitalter der Sonnenkronen');

        $this->get(route('knowledge.encyclopedia', [
            'world' => $world,
            'k' => 'regionen',
        ]))
            ->assertOk()
            ->assertSeeText('Aschelande')
            ->assertDontSeeText('Schattenhaeuser von Nerez');
    }

    public function test_public_entry_detail_route_renders_published_content(): void
    {
        $entry = EncyclopediaEntry::query()
            ->where('status', EncyclopediaEntry::STATUS_PUBLISHED)
            ->with('category')
            ->firstOrFail();

        $this->get(route('knowledge.encyclopedia.entry', [
            'world' => $entry->category->world,
            'categorySlug' => $entry->category->slug,
            'entrySlug' => $entry->slug,
        ]))
            ->assertOk()
            ->assertSeeText($entry->title)
            ->assertSeeText('Alle Einträge')
            ->assertSeeText('Bild-Prompt-Vorschläge');
    }

    public function test_public_entry_detail_shows_extracted_cross_links_when_markdown_contains_them(): void
    {
        $category = EncyclopediaCategory::query()->firstOrFail();

        $entry = EncyclopediaEntry::query()->create([
            'encyclopedia_category_id' => $category->id,
            'title' => 'Verweisknoten',
            'slug' => 'verweisknoten',
            'excerpt' => 'Testet Querverlinkungen.',
            'content' => 'Siehe [Aschelande](/wissen/enzyklopaedie/regionen/aschelande) und [Der Aschenfall](/wissen/enzyklopaedie/zeitalter/der-aschenfall).',
            'status' => EncyclopediaEntry::STATUS_PUBLISHED,
            'position' => 77,
            'published_at' => now(),
        ]);

        $this->get(route('knowledge.encyclopedia.entry', [
            'world' => $category->world,
            'categorySlug' => $category->slug,
            'entrySlug' => $entry->slug,
        ]))
            ->assertOk()
            ->assertSeeText('Querverlinkungen')
            ->assertSeeText('Aschelande')
            ->assertSeeText('Der Aschenfall');
    }

    public function test_public_entry_detail_returns_404_for_category_slug_mismatch(): void
    {
        $entry = EncyclopediaEntry::query()
            ->where('status', EncyclopediaEntry::STATUS_PUBLISHED)
            ->with('category')
            ->firstOrFail();

        $mismatchingCategory = EncyclopediaCategory::query()
            ->where('id', '!=', $entry->encyclopedia_category_id)
            ->firstOrFail();

        $this->get(route('knowledge.encyclopedia.entry', [
            'world' => $mismatchingCategory->world,
            'categorySlug' => $mismatchingCategory->slug,
            'entrySlug' => $entry->slug,
        ]))
            ->assertNotFound();
    }

    public function test_player_cannot_access_encyclopedia_admin(): void
    {
        $player = User::factory()->create();
        $world = $this->defaultWorld();

        $this->actingAs($player)
            ->get(route('knowledge.admin.kategorien.index', ['world' => $world]))
            ->assertNotFound();
    }

    public function test_gm_can_create_category_and_entry_with_game_relevance(): void
    {
        $gm = User::factory()->gm()->create();
        $world = $this->defaultWorld();

        $this->actingAs($gm)
            ->post(route('knowledge.admin.kategorien.store', ['world' => $world]), [
                'name' => 'Mythen',
                'slug' => 'mythen',
                'summary' => 'Ueberlieferte Geschichten und Verzeichnungen.',
                'position' => 60,
                'is_public' => '1',
            ])
            ->assertRedirect(route('knowledge.admin.kategorien.index', ['world' => $world]));

        $category = EncyclopediaCategory::query()->where('slug', 'mythen')->first();

        $this->assertNotNull($category);

        $this->actingAs($gm)
            ->post(route('knowledge.admin.kategorien.eintraege.store', [
                'world' => $category->world,
                'encyclopediaCategory' => $category,
            ]), [
                'title' => 'Schwurhort von Carron',
                'slug' => 'schwurhort-von-carron',
                'excerpt' => 'Zentraler Ort fuer Eide der alten Haeuser.',
                'content' => 'Im Schwurhort werden Erbbuendnisse, Blutvertraege und Bannschwure registriert.',
                'status' => EncyclopediaEntry::STATUS_PUBLISHED,
                'position' => 5,
                'game_relevance_le' => 'Frontlastige Szenen koennen schnelle LE-Verluste erzeugen.',
                'game_relevance_probe' => 'Mut und Charisma sind hier haeufige GM-Proben.',
            ])
            ->assertRedirect(route('knowledge.admin.kategorien.edit', [
                'world' => $category->world,
                'encyclopediaCategory' => $category,
            ]));

        $entry = EncyclopediaEntry::query()
            ->where('encyclopedia_category_id', $category->id)
            ->where('slug', 'schwurhort-von-carron')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame('Frontlastige Szenen koennen schnelle LE-Verluste erzeugen.', data_get($entry->game_relevance, 'le_hint'));
        $this->assertSame('Mut und Charisma sind hier haeufige GM-Proben.', data_get($entry->game_relevance, 'probe_hint'));

        $this->actingAs($gm)
            ->put(route('knowledge.admin.kategorien.eintraege.update', [
                'world' => $category->world,
                'encyclopediaCategory' => $category,
                'encyclopediaEntry' => $entry,
            ]), [
                'title' => 'Schwurhort von Carron',
                'slug' => 'schwurhort-von-carron',
                'excerpt' => 'Zentraler Ort fuer Eide der alten Haeuser.',
                'content' => 'Im Schwurhort werden Erbbuendnisse, Blutvertraege und Bannschwure registriert.',
                'status' => EncyclopediaEntry::STATUS_PUBLISHED,
                'position' => 5,
                'game_relevance_le' => '',
                'game_relevance_ae' => 'Keine AE ohne magische Veranlagung.',
                'game_relevance_real_world' => 'Real-World-Anfaenger bleiben in der Regel Mensch.',
            ])
            ->assertRedirect(route('knowledge.admin.kategorien.edit', [
                'world' => $category->world,
                'encyclopediaCategory' => $category,
            ]));

        $entry->refresh();

        $this->assertNull(data_get($entry->game_relevance, 'le_hint'));
        $this->assertSame('Keine AE ohne magische Veranlagung.', data_get($entry->game_relevance, 'ae_hint'));
        $this->assertSame('Real-World-Anfaenger bleiben in der Regel Mensch.', data_get($entry->game_relevance, 'real_world_hint'));
    }

    public function test_draft_and_archived_entries_are_hidden_on_public_index_and_detail(): void
    {
        $category = EncyclopediaCategory::query()->firstOrFail();

        $draft = EncyclopediaEntry::query()->create([
            'encyclopedia_category_id' => $category->id,
            'title' => 'Verborgene Wahrheit',
            'slug' => 'verborgene-wahrheit',
            'excerpt' => 'Darf nicht oeffentlich auftauchen.',
            'content' => 'Dieser Text ist ein Entwurf.',
            'status' => EncyclopediaEntry::STATUS_DRAFT,
            'position' => 999,
            'published_at' => null,
        ]);

        $archived = EncyclopediaEntry::query()->create([
            'encyclopedia_category_id' => $category->id,
            'title' => 'Vergessene Chronik',
            'slug' => 'vergessene-chronik',
            'excerpt' => 'Archivmaterial',
            'content' => 'Dieser Text ist archiviert.',
            'status' => EncyclopediaEntry::STATUS_ARCHIVED,
            'position' => 1000,
            'published_at' => now(),
        ]);

        $this->get(route('knowledge.encyclopedia', ['world' => $category->world]))
            ->assertOk()
            ->assertDontSeeText('Verborgene Wahrheit')
            ->assertDontSeeText('Vergessene Chronik');

        $this->get(route('knowledge.encyclopedia.entry', [
            'world' => $category->world,
            'categorySlug' => $category->slug,
            'entrySlug' => $draft->slug,
        ]))
            ->assertNotFound();

        $this->get(route('knowledge.encyclopedia.entry', [
            'world' => $category->world,
            'categorySlug' => $category->slug,
            'entrySlug' => $archived->slug,
        ]))
            ->assertNotFound();
    }

    public function test_game_relevance_box_is_only_visible_when_data_exists(): void
    {
        $category = EncyclopediaCategory::query()->firstOrFail();

        $withRelevance = EncyclopediaEntry::query()->create([
            'encyclopedia_category_id' => $category->id,
            'title' => 'Archiv der Narben',
            'slug' => 'archiv-der-narben',
            'excerpt' => 'Sammlung blutiger Fallberichte.',
            'content' => '## Akte\n\nAlles ist dokumentiert.',
            'game_relevance' => [
                'probe_hint' => 'Inquisitionsszenen nutzen oft Klugheit und Intuition.',
            ],
            'status' => EncyclopediaEntry::STATUS_PUBLISHED,
            'position' => 120,
            'published_at' => now(),
        ]);

        $withoutRelevance = EncyclopediaEntry::query()->create([
            'encyclopedia_category_id' => $category->id,
            'title' => 'Leere Chronik',
            'slug' => 'leere-chronik',
            'excerpt' => 'Nur Lore ohne Spielwerte.',
            'content' => 'Nur Text.',
            'status' => EncyclopediaEntry::STATUS_PUBLISHED,
            'position' => 121,
            'published_at' => now(),
        ]);

        $this->get(route('knowledge.encyclopedia.entry', [
            'world' => $category->world,
            'categorySlug' => $category->slug,
            'entrySlug' => $withRelevance->slug,
        ]))
            ->assertOk()
            ->assertSeeText('Spielrelevanz')
            ->assertSeeText('Inquisitionsszenen nutzen oft Klugheit und Intuition.');

        $this->get(route('knowledge.encyclopedia.entry', [
            'world' => $category->world,
            'categorySlug' => $category->slug,
            'entrySlug' => $withoutRelevance->slug,
        ]))
            ->assertOk()
            ->assertDontSeeText('Spielrelevanz');
    }

    public function test_entry_edit_route_returns_404_for_category_mismatch(): void
    {
        $gm = User::factory()->gm()->create();

        $categories = EncyclopediaCategory::query()
            ->with('world')
            ->take(2)
            ->get();
        $categoryA = $categories->get(0);
        $categoryB = $categories->get(1);

        $this->assertNotNull($categoryA);
        $this->assertNotNull($categoryB);

        $entry = EncyclopediaEntry::query()->create([
            'encyclopedia_category_id' => $categoryA->id,
            'title' => 'Grenzstein',
            'slug' => 'grenzstein',
            'excerpt' => 'Grenzmarkierung der alten Linie.',
            'content' => 'Der Stein markiert die alte Provinzgrenze.',
            'status' => EncyclopediaEntry::STATUS_PUBLISHED,
            'position' => 10,
            'published_at' => now(),
            'created_by' => $gm->id,
            'updated_by' => $gm->id,
        ]);

        $this->actingAs($gm)
            ->get(route('knowledge.admin.kategorien.eintraege.edit', [
                'world' => $categoryB->world,
                'encyclopediaCategory' => $categoryB,
                'encyclopediaEntry' => $entry,
            ]))
            ->assertNotFound();
    }
}
