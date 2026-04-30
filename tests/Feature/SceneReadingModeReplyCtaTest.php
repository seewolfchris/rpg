<?php

namespace Tests\Feature;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SceneReadingModeReplyCtaTest extends TestCase
{
    use RefreshDatabase;

    public function test_scene_show_displays_reading_mode_reply_cta_for_user_with_post_rights(): void
    {
        [$campaign, $scene, $owner] = $this->seedSceneContext();

        $response = $this->actingAs($owner)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk()
            ->assertSee('data-reading-mode-exit-to-write', false)
            ->assertSeeText('Romanmodus beenden & antworten');
    }

    public function test_scene_show_hides_reading_mode_reply_cta_for_user_without_post_rights(): void
    {
        [$campaign, $closedScene, , $player] = $this->seedSceneContext(sceneStatus: 'closed');

        $response = $this->actingAs($player)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $closedScene,
        ]));

        $response->assertOk()
            ->assertDontSee('data-reading-mode-exit-to-write', false)
            ->assertDontSeeText('Romanmodus beenden & antworten');
    }

    public function test_scene_show_contains_only_one_post_form_instance(): void
    {
        [$campaign, $scene, $owner] = $this->seedSceneContext();

        $response = $this->actingAs($owner)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk();

        $html = $response->getContent();
        $this->assertIsString($html);
        $this->assertSame(1, substr_count($html, 'id="new-post-form"'));
        $this->assertSame(1, substr_count($html, 'data-offline-post-form'));
    }

    /**
     * @return array{0: Campaign, 1: Scene, 2: User, 3: User}
     */
    private function seedSceneContext(string $sceneStatus = 'open'): array
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
            'status' => $sceneStatus,
        ]);

        return [$campaign, $scene, $owner, $player];
    }
}

