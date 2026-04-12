<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\EncyclopediaCategory;
use App\Models\EncyclopediaEntry;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EncyclopediaWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_can_open_and_store_pending_proposal(): void
    {
        $player = User::factory()->create();
        $world = $this->defaultWorld();
        $category = $this->createPublicCategory($world, 'workflow-store');

        $this->actingAs($player)
            ->get(route('knowledge.encyclopedia.proposals.create', ['world' => $world]))
            ->assertOk()
            ->assertSeeText('Eintrag vorschlagen');

        $response = $this->actingAs($player)
            ->post(route('knowledge.encyclopedia.proposals.store', ['world' => $world]), [
                'encyclopedia_category_id' => $category->id,
                'title' => 'Kupfer Bastion',
                'slug' => '',
                'excerpt' => 'Kurztext für den Vorschlag.',
                'content' => '## Inhalt'.PHP_EOL.PHP_EOL.'Ein neuer Vorschlag.',
            ]);

        $entry = EncyclopediaEntry::query()
            ->where('encyclopedia_category_id', $category->id)
            ->where('slug', Str::slug('Kupfer Bastion'))
            ->firstOrFail();

        $response->assertRedirect(route('knowledge.encyclopedia.proposals.edit', [
            'world' => $world,
            'encyclopediaEntry' => $entry,
        ]));

        $this->assertDatabaseHas('encyclopedia_entries', [
            'id' => $entry->id,
            'status' => EncyclopediaEntry::STATUS_PENDING,
            'created_by' => $player->id,
            'updated_by' => $player->id,
            'reviewed_by' => null,
            'position' => 0,
        ]);
    }

    public function test_pending_entries_are_not_visible_on_public_index_or_detail(): void
    {
        $world = $this->defaultWorld();
        $category = $this->createPublicCategory($world, 'workflow-hidden');

        $entry = EncyclopediaEntry::query()->create([
            'encyclopedia_category_id' => $category->id,
            'title' => 'Nur intern sichtbar',
            'slug' => 'nur-intern-sichtbar',
            'excerpt' => 'Darf öffentlich nicht erscheinen.',
            'content' => 'Dieser Vorschlag ist pending.',
            'status' => EncyclopediaEntry::STATUS_PENDING,
            'position' => 0,
            'published_at' => null,
        ]);

        $this->get(route('knowledge.encyclopedia', ['world' => $world]))
            ->assertOk()
            ->assertDontSeeText($entry->title);

        $this->get(route('knowledge.encyclopedia.entry', [
            'world' => $world,
            'categorySlug' => $category->slug,
            'entrySlug' => $entry->slug,
        ]))
            ->assertNotFound();
    }

    public function test_gm_admin_and_world_cogm_can_review_proposals(): void
    {
        $world = $this->defaultWorld();
        $category = $this->createPublicCategory($world, 'workflow-review');
        $author = User::factory()->create();
        $gm = User::factory()->gm()->create();
        $admin = User::factory()->admin()->create();
        $coGm = User::factory()->create();
        $owner = User::factory()->gm()->create();
        $outsider = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'world_id' => $world->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        CampaignInvitation::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $coGm->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_CO_GM,
            'accepted_at' => now(),
            'responded_at' => now(),
            'created_at' => now(),
        ]);

        $entryForGm = $this->createPendingEntry($category, $author, 'gm-approve');
        $entryForAdmin = $this->createPendingEntry($category, $author, 'admin-reject');
        $entryForCoGm = $this->createPendingEntry($category, $author, 'co-gm-approve');
        $entryForOutsider = $this->createPendingEntry($category, $author, 'outsider-denied');

        $this->actingAs($gm)
            ->patch(route('knowledge.encyclopedia.moderation.approve', [
                'world' => $world,
                'encyclopediaEntry' => $entryForGm,
            ]))
            ->assertRedirect(route('knowledge.encyclopedia.moderation.index', ['world' => $world]));

        $this->assertDatabaseHas('encyclopedia_entries', [
            'id' => $entryForGm->id,
            'status' => EncyclopediaEntry::STATUS_PUBLISHED,
            'reviewed_by' => $gm->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('knowledge.encyclopedia.moderation.reject', [
                'world' => $world,
                'encyclopediaEntry' => $entryForAdmin,
            ]))
            ->assertRedirect(route('knowledge.encyclopedia.moderation.index', ['world' => $world]));

        $this->assertDatabaseHas('encyclopedia_entries', [
            'id' => $entryForAdmin->id,
            'status' => EncyclopediaEntry::STATUS_REJECTED,
            'reviewed_by' => $admin->id,
        ]);

        $this->actingAs($coGm)
            ->patch(route('knowledge.encyclopedia.moderation.approve', [
                'world' => $world,
                'encyclopediaEntry' => $entryForCoGm,
            ]))
            ->assertRedirect(route('knowledge.encyclopedia.moderation.index', ['world' => $world]));

        $this->assertDatabaseHas('encyclopedia_entries', [
            'id' => $entryForCoGm->id,
            'status' => EncyclopediaEntry::STATUS_PUBLISHED,
            'reviewed_by' => $coGm->id,
        ]);

        $this->actingAs($outsider)
            ->patch(route('knowledge.encyclopedia.moderation.reject', [
                'world' => $world,
                'encyclopediaEntry' => $entryForOutsider,
            ]))
            ->assertForbidden();

        $otherWorld = World::factory()->create([
            'slug' => 'workflow-andere-welt',
            'is_active' => true,
        ]);
        $otherCategory = $this->createPublicCategory($otherWorld, 'workflow-review-other');
        $otherEntry = $this->createPendingEntry($otherCategory, $author, 'other-world');

        $this->actingAs($coGm)
            ->patch(route('knowledge.encyclopedia.moderation.approve', [
                'world' => $otherWorld,
                'encyclopediaEntry' => $otherEntry,
            ]))
            ->assertForbidden();
    }

    public function test_updating_proposal_writes_revision_snapshot_and_resubmits_pending(): void
    {
        $world = $this->defaultWorld();
        $category = $this->createPublicCategory($world, 'workflow-revision');
        $player = User::factory()->create();
        $gm = User::factory()->gm()->create();

        $entry = EncyclopediaEntry::query()->create([
            'encyclopedia_category_id' => $category->id,
            'title' => 'Alter Titel',
            'slug' => 'alter-titel',
            'excerpt' => 'Alter Auszug',
            'content' => 'Alter Inhalt',
            'status' => EncyclopediaEntry::STATUS_REJECTED,
            'position' => 5,
            'published_at' => null,
            'created_by' => $player->id,
            'updated_by' => $player->id,
            'reviewed_by' => $gm->id,
            'reviewed_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($player)
            ->put(route('knowledge.encyclopedia.proposals.update', [
                'world' => $world,
                'encyclopediaEntry' => $entry,
            ]), [
                'encyclopedia_category_id' => $category->id,
                'title' => 'Neuer Titel',
                'slug' => 'neuer-titel',
                'excerpt' => 'Neuer Auszug',
                'content' => 'Neuer Inhalt',
            ]);

        $response->assertRedirect(route('knowledge.encyclopedia.proposals.edit', [
            'world' => $world,
            'encyclopediaEntry' => $entry,
        ]));

        $entry->refresh();

        $this->assertSame(EncyclopediaEntry::STATUS_PENDING, $entry->status);
        $this->assertSame($player->id, (int) $entry->updated_by);
        $this->assertNull($entry->reviewed_by);
        $this->assertNull($entry->reviewed_at);
        $this->assertSame(0, (int) $entry->position);

        $this->assertDatabaseHas('encyclopedia_entry_revisions', [
            'encyclopedia_entry_id' => $entry->id,
            'editor_id' => $player->id,
            'title_before' => 'Alter Titel',
            'excerpt_before' => 'Alter Auszug',
            'content_before' => 'Alter Inhalt',
            'status_before' => EncyclopediaEntry::STATUS_REJECTED,
        ]);
    }

    private function defaultWorld(): World
    {
        return World::query()
            ->where('slug', (string) config('worlds.default_slug'))
            ->firstOrFail();
    }

    private function createPublicCategory(World $world, string $slug): EncyclopediaCategory
    {
        return EncyclopediaCategory::query()->create([
            'world_id' => $world->id,
            'name' => 'Kategorie '.Str::headline($slug),
            'slug' => $slug,
            'summary' => 'Zusammenfassung '.$slug,
            'position' => 10,
            'is_public' => true,
        ]);
    }

    private function createPendingEntry(EncyclopediaCategory $category, User $author, string $slug): EncyclopediaEntry
    {
        return EncyclopediaEntry::query()->create([
            'encyclopedia_category_id' => $category->id,
            'title' => 'Eintrag '.Str::headline($slug),
            'slug' => 'eintrag-'.$slug,
            'excerpt' => 'Ausstehender Vorschlag '.$slug,
            'content' => 'Inhalt '.$slug,
            'status' => EncyclopediaEntry::STATUS_PENDING,
            'position' => 0,
            'published_at' => null,
            'created_by' => $author->id,
            'updated_by' => $author->id,
        ]);
    }
}
