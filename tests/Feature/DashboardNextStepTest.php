<?php

namespace Tests\Feature;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\CampaignMembership;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardNextStepTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_compact_next_step_card_for_active_world(): void
    {
        $user = User::factory()->create();
        $world = World::query()->where('slug', 'klassische-fantasy')->firstOrFail();

        $response = $this->actingAs($user)
            ->withSession(['world_slug' => $world->slug])
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSeeText('Kampagne')
            ->assertSeeText('Kampagnen öffnen')
            ->assertSeeText('Erste Schritte ins Spiel')
            ->assertDontSeeText('Was ist RPG?')
            ->assertDontSeeText('Du brauchst kein Vorwissen.');

        $html = $response->getContent();
        $this->assertIsString($html);

        $this->assertStringContainsString('href="'.route('campaigns.index', ['world' => $world]).'"', $html);
        $this->assertStringContainsString('href="'.route('knowledge.getting-started', ['world' => $world]).'"', $html);

        $xpath = $this->toXPath($html);
        $nextStepCards = $xpath->query("//section[@aria-labelledby='dashboard-next-step-title']");

        $this->assertSame(1, $nextStepCards->length);
    }

    public function test_dashboard_next_step_prioritizes_pending_invitation_in_active_world(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->gm()->create();
        $world = World::query()->where('slug', 'klassische-fantasy')->firstOrFail();
        $campaign = Campaign::factory()->create([
            'world_id' => (int) $world->id,
            'owner_id' => (int) $owner->id,
            'is_public' => false,
            'status' => 'active',
            'title' => 'Einladungsrunde',
        ]);

        CampaignInvitation::query()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $user->id,
            'invited_by' => (int) $owner->id,
            'status' => CampaignInvitation::STATUS_PENDING,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['world_slug' => $world->slug])
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSeeText('Offene Einladung')
            ->assertSeeText('Einladung ansehen')
            ->assertSeeText('Kampagne: Einladungsrunde');
    }

    public function test_dashboard_next_step_character_check_is_scoped_to_active_world(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->gm()->create();
        $activeWorld = World::query()->where('slug', 'klassische-fantasy')->firstOrFail();
        $otherWorld = World::query()->where('slug', 'chroniken-der-asche')->firstOrFail();

        Campaign::factory()->create([
            'world_id' => (int) $activeWorld->id,
            'owner_id' => (int) $owner->id,
            'is_public' => true,
            'status' => 'active',
        ]);
        Character::factory()->create([
            'user_id' => (int) $user->id,
            'world_id' => (int) $otherWorld->id,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['world_slug' => $activeWorld->slug])
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSeeText('Charakter erstellen')
            ->assertSee('href="'.route('characters.create', ['world' => $activeWorld->slug]).'"', false);
    }

    public function test_dashboard_next_step_uses_only_visible_campaigns_in_active_world(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->gm()->create();
        $world = World::query()->where('slug', 'klassische-fantasy')->firstOrFail();
        $visibleCampaign = Campaign::factory()->create([
            'world_id' => (int) $world->id,
            'owner_id' => (int) $owner->id,
            'is_public' => true,
            'status' => 'active',
            'title' => 'Sichtbare Runde',
            'created_at' => now()->subDay(),
        ]);
        $hiddenCampaign = Campaign::factory()->create([
            'world_id' => (int) $world->id,
            'owner_id' => (int) $owner->id,
            'is_public' => false,
            'status' => 'active',
            'title' => 'Verdeckte Runde',
            'created_at' => now(),
        ]);
        Character::factory()->create([
            'user_id' => (int) $user->id,
            'world_id' => (int) $world->id,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['world_slug' => $world->slug])
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSeeText('Kampagne öffnen')
            ->assertDontSeeText('Verdeckte Runde');

        $html = $response->getContent();
        $this->assertIsString($html);
        $this->assertStringContainsString(
            'href="'.route('campaigns.show', ['world' => $world, 'campaign' => $visibleCampaign]).'"',
            $html
        );
        $this->assertStringNotContainsString(
            'href="'.route('campaigns.show', ['world' => $world, 'campaign' => $hiddenCampaign]).'"',
            $html
        );
    }

    public function test_dashboard_next_step_shows_moderation_before_unread_scenes_for_allowed_gm(): void
    {
        $owner = User::factory()->gm()->create();
        $player = User::factory()->create();
        $world = World::query()->where('slug', 'chroniken-der-asche')->firstOrFail();
        $campaign = Campaign::factory()->create([
            'world_id' => (int) $world->id,
            'owner_id' => (int) $owner->id,
            'is_public' => true,
            'status' => 'active',
        ]);
        CampaignMembership::factory()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $owner->id,
            'role' => CampaignMembershipRole::GM->value,
        ]);
        Character::factory()->create([
            'user_id' => (int) $owner->id,
            'world_id' => (int) $world->id,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => (int) $campaign->id,
            'created_by' => (int) $owner->id,
            'status' => 'open',
        ]);
        $post = Post::factory()->create([
            'scene_id' => (int) $scene->id,
            'user_id' => (int) $player->id,
            'moderation_status' => 'pending',
        ]);
        SceneSubscription::query()->create([
            'scene_id' => (int) $scene->id,
            'user_id' => (int) $owner->id,
            'last_read_post_id' => null,
            'is_muted' => false,
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['world_slug' => $world->slug])
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSeeText('Moderation öffnen')
            ->assertSeeText('Ausstehend: 1')
            ->assertDontSeeText('Ungelesene Szenen öffnen');
    }

    public function test_dashboard_next_step_does_not_show_moderation_for_unallowed_player(): void
    {
        $owner = User::factory()->gm()->create();
        $player = User::factory()->create();
        $world = World::query()->where('slug', 'chroniken-der-asche')->firstOrFail();
        $campaign = Campaign::factory()->create([
            'world_id' => (int) $world->id,
            'owner_id' => (int) $owner->id,
            'is_public' => true,
            'status' => 'active',
        ]);
        Character::factory()->create([
            'user_id' => (int) $player->id,
            'world_id' => (int) $world->id,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => (int) $campaign->id,
            'created_by' => (int) $owner->id,
            'status' => 'open',
        ]);
        $post = Post::factory()->create([
            'scene_id' => (int) $scene->id,
            'user_id' => (int) $owner->id,
            'moderation_status' => 'pending',
        ]);
        SceneSubscription::query()->create([
            'scene_id' => (int) $scene->id,
            'user_id' => (int) $player->id,
            'last_read_post_id' => null,
            'is_muted' => false,
        ]);

        $response = $this->actingAs($player)
            ->withSession(['world_slug' => $world->slug])
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSeeText('Ungelesene Szenen öffnen')
            ->assertDontSeeText('Moderation öffnen');
    }

    public function test_dashboard_next_step_keeps_existing_dashboard_sections(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertSeeText('Sichere Zuflucht')
            ->assertSeeText('Aktive Welt')
            ->assertSeeText('Tutorial im Spiel')
            ->assertSeeText('Top-Chronisten');
    }

    private function toXPath(string $html): \DOMXPath
    {
        $document = new \DOMDocument();
        @$document->loadHTML($html);

        return new \DOMXPath($document);
    }
}
