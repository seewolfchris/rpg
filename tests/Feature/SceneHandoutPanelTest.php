<?php

namespace Tests\Feature;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\Handout;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SceneHandoutPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_scene_show_displays_revealed_campaign_handout_for_player(): void
    {
        [$campaign, $scene, $owner, , $player] = $this->seedSceneContext();
        $campaignHandout = $this->createHandout(
            campaign: $campaign,
            creator: $owner,
            title: 'Kampagnenkarte',
            revealed: true,
            scene: null,
        );

        $response = $this->actingAs($player)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk();
        $response->assertSee('Szenen-Handouts');
        $response->assertSee('Kampagnenkarte');
        $response->assertSee(route('campaigns.handouts.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'handout' => $campaignHandout,
        ]), false);
    }

    public function test_scene_show_displays_revealed_scene_handout_for_player(): void
    {
        [$campaign, $scene, $owner, , $player] = $this->seedSceneContext();

        $this->createHandout(
            campaign: $campaign,
            creator: $owner,
            title: 'Szenenbrief',
            revealed: true,
            scene: $scene,
        );

        $response = $this->actingAs($player)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk();
        $response->assertSee('Szenenbrief');
        $response->assertSee('Szene');
    }

    public function test_scene_show_hides_unrevealed_handouts_for_player(): void
    {
        [$campaign, $scene, $owner, , $player] = $this->seedSceneContext();

        $this->createHandout(
            campaign: $campaign,
            creator: $owner,
            title: 'Verborgener Hinweis',
            revealed: false,
            scene: $scene,
        );

        $response = $this->actingAs($player)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk();
        $response->assertDontSee('Verborgener Hinweis');
    }

    public function test_scene_show_does_not_render_handout_management_cta_for_player(): void
    {
        [$campaign, $scene, , , $player] = $this->seedSceneContext();

        $response = $this->actingAs($player)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk();
        $response->assertDontSee(route('campaigns.handouts.create', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]), false);
    }

    public function test_scene_show_renders_handout_management_cta_for_gm(): void
    {
        [$campaign, $scene, , $gm] = $this->seedSceneContext();

        $response = $this->actingAs($gm)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk();
        $response->assertSee(route('campaigns.handouts.create', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]), false);
    }

    public function test_scene_show_displays_unrevealed_handout_with_hidden_marker_for_gm(): void
    {
        [$campaign, $scene, $owner, $gm] = $this->seedSceneContext();

        $this->createHandout(
            campaign: $campaign,
            creator: $owner,
            title: 'Nur fuer Leitung',
            revealed: false,
            scene: $scene,
        );

        $response = $this->actingAs($gm)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk();
        $response->assertSee('Nur fuer Leitung');
        $response->assertSee('Verborgen für Spieler');
    }

    public function test_scene_show_does_not_include_handouts_from_other_campaign(): void
    {
        [$campaignA, $sceneA, $owner, , $player] = $this->seedSceneContext();

        $campaignB = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'world_id' => $campaignA->world_id,
            'is_public' => false,
            'status' => 'active',
        ]);
        CampaignMembership::factory()->create([
            'campaign_id' => $campaignB->id,
            'user_id' => $player->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => $owner->id,
        ]);

        $this->createHandout(
            campaign: $campaignA,
            creator: $owner,
            title: 'Handout Kampagne A',
            revealed: true,
            scene: $sceneA,
        );
        $this->createHandout(
            campaign: $campaignB,
            creator: $owner,
            title: 'Leak aus Kampagne B',
            revealed: true,
            scene: null,
        );

        $response = $this->actingAs($player)->get(route('campaigns.scenes.show', [
            'world' => $campaignA->world,
            'campaign' => $campaignA,
            'scene' => $sceneA,
        ]));

        $response->assertOk();
        $response->assertSee('Handout Kampagne A');
        $response->assertDontSee('Leak aus Kampagne B');
    }

    public function test_scene_show_does_not_include_handouts_from_other_world(): void
    {
        [$campaign, $scene, $owner, , $player] = $this->seedSceneContext();

        $foreignWorld = World::factory()->create([
            'slug' => 'fremde-handout-panel-welt',
            'is_active' => true,
            'position' => -990,
        ]);
        $foreignCampaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'world_id' => $foreignWorld->id,
            'is_public' => true,
            'status' => 'active',
        ]);

        $this->createHandout(
            campaign: $campaign,
            creator: $owner,
            title: 'Heimischer Eintrag',
            revealed: true,
            scene: $scene,
        );
        $this->createHandout(
            campaign: $foreignCampaign,
            creator: $owner,
            title: 'Fremde Welt Leak',
            revealed: true,
            scene: null,
        );

        $response = $this->actingAs($player)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk();
        $response->assertSee('Heimischer Eintrag');
        $response->assertDontSee('Fremde Welt Leak');
    }

    public function test_non_member_cannot_access_private_scene_and_handout_panel(): void
    {
        [$campaign, $scene, $owner] = $this->seedSceneContext();
        $outsider = User::factory()->create();

        $this->createHandout(
            campaign: $campaign,
            creator: $owner,
            title: 'Privates Dokument',
            revealed: true,
            scene: $scene,
        );

        $this->actingAs($outsider)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]))->assertForbidden();
    }

    public function test_scene_show_uses_authorized_handout_routes_without_public_storage_urls(): void
    {
        [$campaign, $scene, $owner, , $player] = $this->seedSceneContext();

        $handout = $this->createHandout(
            campaign: $campaign,
            creator: $owner,
            title: 'Route-Check',
            revealed: true,
            scene: $scene,
        );

        $response = $this->actingAs($player)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk();
        $response->assertSee(route('campaigns.handouts.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'handout' => $handout,
        ]), false);
        $response->assertDontSee(route('campaigns.handouts.file', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'handout' => $handout,
        ]), false);
        $response->assertDontSee('/storage/', false);
    }

    /**
     * @return array{0: Campaign, 1: Scene, 2: User, 3: User, 4: User}
     */
    private function seedSceneContext(): array
    {
        $owner = User::factory()->gm()->create();
        $gm = User::factory()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);

        CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $owner->id,
            'role' => CampaignMembershipRole::GM->value,
            'assigned_by' => $owner->id,
        ]);
        CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $gm->id,
            'role' => CampaignMembershipRole::GM->value,
            'assigned_by' => $owner->id,
        ]);
        CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $player->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => $owner->id,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'header_image_path' => null,
        ]);

        return [$campaign, $scene, $owner, $gm, $player];
    }

    private function createHandout(
        Campaign $campaign,
        User $creator,
        string $title,
        bool $revealed,
        ?Scene $scene,
    ): Handout {
        return Handout::factory()->create([
            'campaign_id' => $campaign->id,
            'scene_id' => $scene?->id,
            'created_by' => $creator->id,
            'title' => $title,
            'revealed_at' => $revealed ? now() : null,
        ]);
    }
}
