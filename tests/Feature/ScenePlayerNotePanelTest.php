<?php

namespace Tests\Feature;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\PlayerNote;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScenePlayerNotePanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_scene_show_displays_player_note_panel_with_navigation_links(): void
    {
        [$campaign, $scene, $player] = $this->seedSceneContext();

        $response = $this->actingAs($player)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk();
        $response->assertSee('Meine Notizen');
        $response->assertSee(route('campaigns.player-notes.index', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]), false);
        $response->assertSee(route('campaigns.player-notes.create', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]), false);
    }

    public function test_scene_show_counts_only_relevant_own_notes(): void
    {
        [$campaign, $scene, $player, $owner] = $this->seedSceneContext();
        $otherScene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
        ]);
        $otherUser = User::factory()->create();
        CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $otherUser->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => $owner->id,
        ]);

        PlayerNote::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $player->id,
            'scene_id' => null,
            'title' => 'Kampagnenweit',
        ]);
        PlayerNote::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $player->id,
            'scene_id' => $scene->id,
            'title' => 'Aktuelle Szene',
        ]);
        PlayerNote::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $player->id,
            'scene_id' => $otherScene->id,
            'title' => 'Andere Szene',
        ]);
        PlayerNote::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $otherUser->id,
            'scene_id' => $scene->id,
            'title' => 'Fremde Notiz',
        ]);

        $otherCampaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'world_id' => $campaign->world_id,
            'status' => 'active',
            'is_public' => false,
        ]);
        PlayerNote::factory()->create([
            'campaign_id' => $otherCampaign->id,
            'user_id' => $player->id,
            'scene_id' => null,
            'title' => 'Andere Kampagne',
        ]);

        $foreignWorld = World::factory()->create([
            'slug' => 'player-note-panel-foreign-world',
            'is_active' => true,
            'position' => -928,
        ]);
        $foreignCampaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'world_id' => $foreignWorld->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        PlayerNote::factory()->create([
            'campaign_id' => $foreignCampaign->id,
            'user_id' => $player->id,
            'scene_id' => null,
            'title' => 'Andere Welt',
        ]);

        $response = $this->actingAs($player)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk();
        $response->assertSee('2 eigene Notizen');
    }

    public function test_scene_show_does_not_render_note_bodies_or_foreign_titles_in_thread(): void
    {
        [$campaign, $scene, $player] = $this->seedSceneContext();
        $otherUser = User::factory()->create();
        CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $otherUser->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => $campaign->owner_id,
        ]);

        $foreignTitle = 'FREMDE-NOTIZ-SOLLTE-NICHT-IM-THREAD-SEIN';
        $uniqueBody = 'PRIVATE-NOTE-BODY-SHOULD-NOT-RENDER-IN-THREAD';

        PlayerNote::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $player->id,
            'scene_id' => $scene->id,
            'title' => 'Eigene Notiz',
            'body' => $uniqueBody,
        ]);
        PlayerNote::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $otherUser->id,
            'scene_id' => $scene->id,
            'title' => $foreignTitle,
            'body' => 'x',
        ]);

        $response = $this->actingAs($player)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk();
        $response->assertDontSee($uniqueBody);
        $response->assertDontSee($foreignTitle);
    }

    /**
     * @return array{0: Campaign, 1: Scene, 2: User, 3: User}
     */
    private function seedSceneContext(): array
    {
        $owner = User::factory()->gm()->create();
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
            'user_id' => $player->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => $owner->id,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
        ]);

        return [$campaign, $scene, $player, $owner];
    }
}
