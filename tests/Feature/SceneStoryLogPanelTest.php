<?php

namespace Tests\Feature;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\Scene;
use App\Models\StoryLogEntry;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SceneStoryLogPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_sceneChroniclePanel_displays_navigation_for_player(): void
    {
        [$campaign, $scene, $owner, , $player] = $this->seedSceneContext();
        $entry = $this->createStoryLogEntry(
            campaign: $campaign,
            creator: $owner,
            title: 'Kapitel Nordtor',
            revealed: true,
            scene: $scene,
        );

        $response = $this->actingAs($player)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk();
        $response->assertSee('Chronik');
        $response->assertSee(route('campaigns.story-log.index', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]), false);
        $response->assertDontSee(route('campaigns.story-log.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'storyLogEntry' => $entry,
        ]), false);
    }

    public function test_sceneChroniclePanel_hides_create_cta_for_player(): void
    {
        [$campaign, $scene, , , $player] = $this->seedSceneContext();

        $response = $this->actingAs($player)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk();
        $response->assertDontSee(route('campaigns.story-log.create', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]), false);
    }

    public function test_sceneChroniclePanel_shows_create_cta_for_gm(): void
    {
        [$campaign, $scene, , $gm] = $this->seedSceneContext();

        $response = $this->actingAs($gm)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk();
        $response->assertSee(route('campaigns.story-log.create', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]), false);
    }

    public function test_sceneChroniclePanel_counts_relevant_entries_for_player_and_gm(): void
    {
        [$campaign, $scene, $owner, $gm, $player] = $this->seedSceneContext();

        $otherScene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
        ]);

        StoryLogEntry::factory()->create([
            'campaign_id' => $campaign->id,
            'scene_id' => null,
            'created_by' => $owner->id,
            'title' => 'Kampagnenweit revealed',
            'revealed_at' => now(),
        ]);
        StoryLogEntry::factory()->create([
            'campaign_id' => $campaign->id,
            'scene_id' => $scene->id,
            'created_by' => $owner->id,
            'title' => 'Szene revealed',
            'revealed_at' => now(),
        ]);
        StoryLogEntry::factory()->create([
            'campaign_id' => $campaign->id,
            'scene_id' => $scene->id,
            'created_by' => $owner->id,
            'title' => 'Szene unrevealed',
            'revealed_at' => null,
        ]);
        StoryLogEntry::factory()->create([
            'campaign_id' => $campaign->id,
            'scene_id' => $otherScene->id,
            'created_by' => $owner->id,
            'title' => 'Andere Szene revealed',
            'revealed_at' => now(),
        ]);

        $otherCampaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'world_id' => $campaign->world_id,
            'status' => 'active',
            'is_public' => false,
        ]);
        StoryLogEntry::factory()->create([
            'campaign_id' => $otherCampaign->id,
            'scene_id' => null,
            'created_by' => $owner->id,
            'title' => 'Andere Kampagne revealed',
            'revealed_at' => now(),
        ]);

        $foreignWorld = World::factory()->create([
            'slug' => 'fremde-chronik-panel-welt',
            'is_active' => true,
            'position' => -960,
        ]);
        $foreignCampaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'world_id' => $foreignWorld->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        StoryLogEntry::factory()->create([
            'campaign_id' => $foreignCampaign->id,
            'scene_id' => null,
            'created_by' => $owner->id,
            'title' => 'Andere Welt revealed',
            'revealed_at' => now(),
        ]);

        $playerResponse = $this->actingAs($player)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));
        $playerResponse->assertOk();
        $playerResponse->assertSee('2 relevante Einträge');

        $gmResponse = $this->actingAs($gm)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));
        $gmResponse->assertOk();
        $gmResponse->assertSee('3 relevante Einträge');
    }

    public function test_sceneChroniclePanel_does_not_render_full_chronicle_body_in_scene_thread(): void
    {
        [$campaign, $scene, $owner, , $player] = $this->seedSceneContext();
        $uniqueBody = 'CHRONICLE-BODY-SHOULD-NOT-RENDER-IN-THREAD';

        StoryLogEntry::factory()->create([
            'campaign_id' => $campaign->id,
            'scene_id' => $scene->id,
            'created_by' => $owner->id,
            'title' => 'Kapitel mit Langtext',
            'body' => $uniqueBody,
            'revealed_at' => now(),
        ]);

        $response = $this->actingAs($player)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk();
        $response->assertDontSee($uniqueBody);
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
        ]);

        return [$campaign, $scene, $owner, $gm, $player];
    }

    private function createStoryLogEntry(
        Campaign $campaign,
        User $creator,
        string $title,
        bool $revealed,
        ?Scene $scene,
    ): StoryLogEntry {
        return StoryLogEntry::factory()->create([
            'campaign_id' => $campaign->id,
            'scene_id' => $scene?->id,
            'created_by' => $creator->id,
            'title' => $title,
            'body' => 'Kurzer Chroniktext',
            'revealed_at' => $revealed ? now() : null,
        ]);
    }
}
