<?php

namespace Tests\Feature;

use App\Models\EncyclopediaCategory;
use App\Models\EncyclopediaEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EncyclopediaManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_encyclopedia_shows_seeded_content(): void
    {
        $this->get(route('knowledge.encyclopedia'))
            ->assertOk()
            ->assertSeeText('Enzyklopaedie von Vhal')
            ->assertSeeText('Zeitalter der Sonnenkronen');
    }

    public function test_player_cannot_access_encyclopedia_admin(): void
    {
        $player = User::factory()->create();

        $this->actingAs($player)
            ->get(route('knowledge.admin.kategorien.index'))
            ->assertForbidden();
    }

    public function test_gm_can_create_category_and_entry(): void
    {
        $gm = User::factory()->gm()->create();

        $this->actingAs($gm)
            ->post(route('knowledge.admin.kategorien.store'), [
                'name' => 'Mythen',
                'slug' => 'mythen',
                'summary' => 'Ueberlieferte Geschichten und Verzeichnungen.',
                'position' => 60,
                'is_public' => '1',
            ])
            ->assertRedirect(route('knowledge.admin.kategorien.index'));

        $category = EncyclopediaCategory::query()->where('slug', 'mythen')->first();

        $this->assertNotNull($category);

        $this->actingAs($gm)
            ->post(route('knowledge.admin.kategorien.eintraege.store', $category), [
                'title' => 'Schwurhort von Carron',
                'slug' => 'schwurhort-von-carron',
                'excerpt' => 'Zentraler Ort fuer Eide der alten Haeuser.',
                'content' => 'Im Schwurhort werden Erbbuendnisse, Blutvertraege und Bannschwure registriert.',
                'status' => EncyclopediaEntry::STATUS_PUBLISHED,
                'position' => 5,
            ])
            ->assertRedirect(route('knowledge.admin.kategorien.edit', $category));

        $this->assertDatabaseHas('encyclopedia_entries', [
            'encyclopedia_category_id' => $category->id,
            'title' => 'Schwurhort von Carron',
            'slug' => 'schwurhort-von-carron',
            'status' => EncyclopediaEntry::STATUS_PUBLISHED,
            'created_by' => $gm->id,
            'updated_by' => $gm->id,
        ]);
    }

    public function test_draft_entry_is_hidden_on_public_encyclopedia(): void
    {
        $category = EncyclopediaCategory::query()->firstOrFail();

        EncyclopediaEntry::query()->create([
            'encyclopedia_category_id' => $category->id,
            'title' => 'Verborgene Wahrheit',
            'slug' => 'verborgene-wahrheit',
            'excerpt' => 'Darf nicht oeffentlich auftauchen.',
            'content' => 'Dieser Text ist ein Entwurf.',
            'status' => EncyclopediaEntry::STATUS_DRAFT,
            'position' => 999,
            'published_at' => null,
        ]);

        $this->get(route('knowledge.encyclopedia'))
            ->assertOk()
            ->assertDontSeeText('Verborgene Wahrheit');
    }

    public function test_entry_edit_route_returns_404_for_category_mismatch(): void
    {
        $gm = User::factory()->gm()->create();

        $categories = EncyclopediaCategory::query()->take(2)->get();
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
            ->get(route('knowledge.admin.kategorien.eintraege.edit', [$categoryB, $entry]))
            ->assertNotFound();
    }
}
