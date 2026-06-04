<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Scene;
use App\Models\SceneBookmark;
use App\Models\SceneSubscription;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalCenterUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_states_render_actionable_copy_for_personal_pages(): void
    {
        $world = $this->defaultWorld();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['world_slug' => $world->slug])
            ->get(route('characters.index', ['world' => $world->slug]))
            ->assertOk()
            ->assertSeeText('Noch keine Charaktere')
            ->assertSeeText('Charakter erstellen');

        $this->actingAs($user)
            ->withSession(['world_slug' => $world->slug])
            ->get(route('scene-subscriptions.index', ['world' => $world]))
            ->assertOk()
            ->assertSeeText('Keine Szenen-Abos')
            ->assertSeeText('Kampagnen öffnen')
            ->assertSeeText('Zu Mitteilungen');

        $this->actingAs($user)
            ->withSession(['world_slug' => $world->slug])
            ->get(route('bookmarks.index', ['world' => $world]))
            ->assertOk()
            ->assertSeeText('Keine Lesezeichen')
            ->assertSeeText('Speichere wichtige Szenenstellen als Lesezeichen');

        $this->actingAs($user)
            ->get(route('campaign-invitations.index'))
            ->assertOk()
            ->assertSeeText('Keine offenen Einladungen')
            ->assertSeeText('Welten ansehen');
    }

    public function test_filter_empty_state_is_distinct_from_missing_bookmark_data(): void
    {
        $world = $this->defaultWorld();
        $user = User::factory()->create();
        [, $scene] = $this->publicCampaignAndScene($world);

        SceneBookmark::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'label' => 'Wichtige Spur',
        ]);

        $this->actingAs($user)
            ->withSession(['world_slug' => $world->slug])
            ->get(route('bookmarks.index', ['world' => $world, 'q' => 'ohne-treffer']))
            ->assertOk()
            ->assertSeeText('Keine Lesezeichen im aktuellen Filter')
            ->assertSeeText('Filter zurücksetzen')
            ->assertDontSeeText('Speichere wichtige Szenenstellen als Lesezeichen');
    }

    public function test_notification_quick_access_counts_use_their_target_scope(): void
    {
        $activeWorld = $this->defaultWorld();
        $otherWorld = World::factory()->create(['name' => 'Nachtmeer Test', 'slug' => 'nachtmeer-test']);
        $user = User::factory()->create();
        $owner = User::factory()->create();

        [$activeCampaign, $activeScene] = $this->publicCampaignAndScene($activeWorld, $owner);
        [, $mutedScene] = $this->publicCampaignAndScene($activeWorld, $owner);
        [$otherCampaign, $otherScene] = $this->publicCampaignAndScene($otherWorld, $owner);

        SceneSubscription::query()->create([
            'scene_id' => $activeScene->id,
            'user_id' => $user->id,
            'is_muted' => false,
        ]);
        SceneSubscription::query()->create([
            'scene_id' => $mutedScene->id,
            'user_id' => $user->id,
            'is_muted' => true,
        ]);
        SceneSubscription::query()->create([
            'scene_id' => $otherScene->id,
            'user_id' => $user->id,
            'is_muted' => false,
        ]);

        SceneBookmark::query()->create([
            'scene_id' => $activeScene->id,
            'user_id' => $user->id,
            'label' => 'Aktive Welt',
        ]);
        SceneBookmark::query()->create([
            'scene_id' => $otherScene->id,
            'user_id' => $user->id,
            'label' => 'Andere Welt',
        ]);

        CampaignInvitation::query()->create([
            'campaign_id' => $otherCampaign->id,
            'user_id' => $user->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_PENDING,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['world_slug' => $activeWorld->slug])
            ->get(route('notifications.index'));

        $response->assertOk()
            ->assertSeeText('Persönlich · plattformweit')
            ->assertSeeText('Aktive Welt: '.$activeWorld->name)
            ->assertSeeText('1 offen')
            ->assertSeeText('1 aktiv')
            ->assertSeeText('1 sichtbar');
    }

    public function test_private_pending_invitation_hides_campaign_link_but_keeps_world_and_rules_links(): void
    {
        $world = $this->defaultWorld();
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $campaign = Campaign::factory()->create([
            'world_id' => $world->id,
            'owner_id' => $owner->id,
            'is_public' => false,
            'status' => 'active',
            'title' => 'Verborgene Kampagne',
        ]);

        CampaignInvitation::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $invitee->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_PENDING,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'created_at' => now(),
        ]);

        $this->actingAs($invitee)
            ->get(route('campaign-invitations.index'))
            ->assertOk()
            ->assertSeeText('Weltprofil')
            ->assertSeeText('Regeln/Wissen')
            ->assertDontSeeText('Kampagne ansehen');
    }

    public function test_public_pending_invitation_shows_campaign_context_link(): void
    {
        $world = $this->defaultWorld();
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $campaign = Campaign::factory()->create([
            'world_id' => $world->id,
            'owner_id' => $owner->id,
            'is_public' => true,
            'status' => 'active',
            'title' => 'Offene Kampagne',
        ]);

        CampaignInvitation::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $invitee->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_PENDING,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'created_at' => now(),
        ]);

        $this->actingAs($invitee)
            ->get(route('campaign-invitations.index'))
            ->assertOk()
            ->assertSeeText('Kampagne ansehen')
            ->assertSeeText('Weltprofil')
            ->assertSeeText('Regeln/Wissen');
    }

    /**
     * @return array{0: Campaign, 1: Scene}
     */
    private function publicCampaignAndScene(World $world, ?User $owner = null): array
    {
        $owner ??= User::factory()->create();

        $campaign = Campaign::factory()->create([
            'world_id' => $world->id,
            'owner_id' => $owner->id,
            'is_public' => true,
            'status' => 'active',
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        return [$campaign, $scene];
    }

    private function defaultWorld(): World
    {
        $world = World::query()
            ->where('slug', (string) config('worlds.default_slug'))
            ->first();

        if ($world instanceof World) {
            return $world;
        }

        return World::factory()->chronikenDerAsche()->create();
    }
}
