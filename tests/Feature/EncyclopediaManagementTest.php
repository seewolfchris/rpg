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

    /**
     * @return array{
     *     chroniken: EncyclopediaCategory,
     *     machtbloecke: EncyclopediaCategory,
     *     regionen: EncyclopediaCategory,
     *     zeitalterEntry: EncyclopediaEntry,
     *     machtEntry: EncyclopediaEntry,
     *     regionEntry: EncyclopediaEntry
     * }
     */
    private function encyclopediaFixture(World $world): array
    {
        $chroniken = EncyclopediaCategory::query()->firstOrCreate(
            [
                'world_id' => $world->id,
                'slug' => 'chroniken-fixture',
            ],
            [
                'name' => 'Chroniken',
                'summary' => 'Zeitlinien und Bruchkanten.',
                'position' => 10,
                'is_public' => true,
            ],
        );

        $machtbloecke = EncyclopediaCategory::query()->firstOrCreate(
            [
                'world_id' => $world->id,
                'slug' => 'machtbloecke-fixture',
            ],
            [
                'name' => 'Machtbloecke',
                'summary' => 'Wer zieht die Faeden.',
                'position' => 20,
                'is_public' => true,
            ],
        );

        $regionen = EncyclopediaCategory::query()->firstOrCreate(
            [
                'world_id' => $world->id,
                'slug' => 'regionen-fixture',
            ],
            [
                'name' => 'Regionen',
                'summary' => 'Orte mit Narben und Chancen.',
                'position' => 30,
                'is_public' => true,
            ],
        );

        $zeitalterEntry = EncyclopediaEntry::query()->firstOrCreate(
            [
                'encyclopedia_category_id' => $chroniken->id,
                'slug' => 'der-erste-funken-fixture',
            ],
            [
                'title' => 'Der Erste Funken',
                'excerpt' => 'Der Abend, an dem der Himmel aufriss.',
                'content' => 'Mit dem ersten Funken begann das neue Zeitalter der Asche.',
                'status' => EncyclopediaEntry::STATUS_PUBLISHED,
                'position' => 10,
                'published_at' => now(),
            ],
        );

        $machtEntry = EncyclopediaEntry::query()->firstOrCreate(
            [
                'encyclopedia_category_id' => $machtbloecke->id,
                'slug' => 'haus-vom-staubkamm-fixture',
            ],
            [
                'title' => 'Haus vom Staubkamm',
                'excerpt' => 'Ein Netzwerk aus Schulden und Zeichen.',
                'content' => 'Der Staubkammkodex bindet Namen, Kredite und Klingen.',
                'status' => EncyclopediaEntry::STATUS_PUBLISHED,
                'position' => 20,
                'published_at' => now(),
            ],
        );

        $regionEntry = EncyclopediaEntry::query()->firstOrCreate(
            [
                'encyclopedia_category_id' => $regionen->id,
                'slug' => 'aschebucht-nord-fixture',
            ],
            [
                'title' => 'Aschebucht Nord',
                'excerpt' => 'Grauer Hafen, schwarzes Wasser.',
                'content' => 'In Aschebucht Nord zahlt jeder Windstoss einen Preis.',
                'status' => EncyclopediaEntry::STATUS_PUBLISHED,
                'position' => 30,
                'published_at' => now(),
            ],
        );

        return [
            'chroniken' => $chroniken,
            'machtbloecke' => $machtbloecke,
            'regionen' => $regionen,
            'zeitalterEntry' => $zeitalterEntry,
            'machtEntry' => $machtEntry,
            'regionEntry' => $regionEntry,
        ];
    }

    public function test_public_encyclopedia_shows_fixture_content(): void
    {
        $world = $this->defaultWorld();
        $fixture = $this->encyclopediaFixture($world);

        $this->get(route('knowledge.encyclopedia', ['world' => $world]))
            ->assertOk()
            ->assertSeeText('Enzyklopädie · '.$world->name)
            ->assertSeeText($fixture['zeitalterEntry']->title)
            ->assertSeeText('Mehr lesen');
    }

    public function test_public_encyclopedia_filters_by_query_and_category(): void
    {
        $world = $this->defaultWorld();
        $fixture = $this->encyclopediaFixture($world);

        $this->get(route('knowledge.encyclopedia', [
            'world' => $world,
            'q' => 'Staubkammkodex',
        ]))
            ->assertOk()
            ->assertSeeText($fixture['machtEntry']->title)
            ->assertDontSeeText($fixture['zeitalterEntry']->title);

        $this->get(route('knowledge.encyclopedia', [
            'world' => $world,
            'k' => $fixture['regionen']->slug,
        ]))
            ->assertOk()
            ->assertSeeText($fixture['regionEntry']->title)
            ->assertDontSeeText($fixture['machtEntry']->title);
    }

    public function test_public_entry_detail_route_renders_published_content(): void
    {
        $world = $this->defaultWorld();
        $fixture = $this->encyclopediaFixture($world);
        $entry = $fixture['zeitalterEntry']->fresh('category');
        $this->assertNotNull($entry);

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
        $world = $this->defaultWorld();
        $fixture = $this->encyclopediaFixture($world);
        $category = $fixture['regionen'];

        $entry = EncyclopediaEntry::query()->create([
            'encyclopedia_category_id' => $category->id,
            'title' => 'Verweisknoten',
            'slug' => 'verweisknoten',
            'excerpt' => 'Testet Querverlinkungen.',
            'content' => 'Siehe [Aschebucht Nord](/wissen/enzyklopaedie/'.$fixture['regionen']->slug.'/'.$fixture['regionEntry']->slug.') und [Der Erste Funken](/wissen/enzyklopaedie/'.$fixture['chroniken']->slug.'/'.$fixture['zeitalterEntry']->slug.').',
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
            ->assertSeeText('Aschebucht Nord')
            ->assertSeeText('Der Erste Funken');
    }

    public function test_public_entry_detail_returns_404_for_category_slug_mismatch(): void
    {
        $world = $this->defaultWorld();
        $fixture = $this->encyclopediaFixture($world);
        $entry = $fixture['zeitalterEntry']->fresh('category');
        $mismatchingCategory = $fixture['machtbloecke'];
        $this->assertNotNull($entry);

        $this->get(route('knowledge.encyclopedia.entry', [
            'world' => $world,
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
        $world = $this->defaultWorld();
        $fixture = $this->encyclopediaFixture($world);
        $category = $fixture['chroniken'];

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
        $world = $this->defaultWorld();
        $fixture = $this->encyclopediaFixture($world);
        $category = $fixture['chroniken'];

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
        $world = $this->defaultWorld();
        $fixture = $this->encyclopediaFixture($world);

        $categoryA = $fixture['chroniken'];
        $categoryB = $fixture['machtbloecke'];

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
