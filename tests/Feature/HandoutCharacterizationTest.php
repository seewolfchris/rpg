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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HandoutCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_campaign_manager_can_reveal_unreveal_and_delete_handout_with_existing_redirect_contract(): void
    {
        [$campaign, $scene, , $gm] = $this->seedPrivateCampaignSceneContext();

        $handout = $this->createHandoutWithFile(
            campaign: $campaign,
            creator: $gm,
            scene: $scene,
            revealed: false,
            title: 'Pilot-Management-Handout',
        );

        $showRoute = route('campaigns.handouts.show', $this->routeParams($campaign, $handout));
        $indexRoute = route('campaigns.handouts.index', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]);

        $this->actingAs($gm)
            ->patch(route('campaigns.handouts.reveal', $this->routeParams($campaign, $handout)))
            ->assertRedirect($showRoute);

        $this->assertNotNull($handout->fresh()?->revealed_at);

        $this->actingAs($gm)
            ->patch(route('campaigns.handouts.unreveal', $this->routeParams($campaign, $handout)))
            ->assertRedirect($showRoute);

        $this->assertNull($handout->fresh()?->revealed_at);

        $this->actingAs($gm)
            ->delete(route('campaigns.handouts.destroy', $this->routeParams($campaign, $handout)))
            ->assertRedirect($indexRoute);

        $this->assertDatabaseMissing('handouts', [
            'id' => $handout->id,
        ]);
    }

    public function test_player_trusted_player_and_outsider_cannot_run_handout_mutations(): void
    {
        [$campaign, $scene, , $gm, $player, $trustedPlayer] = $this->seedPrivateCampaignSceneContext();
        $outsider = User::factory()->create();

        $handout = $this->createHandoutWithFile(
            campaign: $campaign,
            creator: $gm,
            scene: $scene,
            revealed: false,
            title: 'Nur Manager Mutation',
        );

        foreach ([$player, $trustedPlayer, $outsider] as $actor) {
            $this->actingAs($actor)
                ->patch(route('campaigns.handouts.reveal', $this->routeParams($campaign, $handout)))
                ->assertForbidden();

            $this->actingAs($actor)
                ->patch(route('campaigns.handouts.unreveal', $this->routeParams($campaign, $handout)))
                ->assertForbidden();

            $this->actingAs($actor)
                ->delete(route('campaigns.handouts.destroy', $this->routeParams($campaign, $handout)))
                ->assertForbidden();
        }

        $this->assertDatabaseHas('handouts', [
            'id' => $handout->id,
        ]);
    }

    public function test_handout_mutations_reject_cross_campaign_context_with_404(): void
    {
        [$campaignA, , $owner] = $this->seedPrivateCampaignSceneContext();

        $campaignB = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'world_id' => $campaignA->world_id,
            'status' => 'active',
            'is_public' => false,
        ]);
        $sceneB = Scene::factory()->create([
            'campaign_id' => $campaignB->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $handoutB = $this->createHandoutWithFile(
            campaign: $campaignB,
            creator: $owner,
            scene: $sceneB,
            revealed: true,
            title: 'Fremdes Kampagnen-Handout',
        );

        $params = [
            'world' => $campaignA->world,
            'campaign' => $campaignA,
            'handout' => $handoutB,
        ];

        $this->actingAs($owner)
            ->patch(route('campaigns.handouts.reveal', $params))
            ->assertNotFound();

        $this->actingAs($owner)
            ->patch(route('campaigns.handouts.unreveal', $params))
            ->assertNotFound();

        $this->actingAs($owner)
            ->delete(route('campaigns.handouts.destroy', $params))
            ->assertNotFound();
    }

    public function test_handout_mutations_reject_cross_world_context_with_404(): void
    {
        [$campaign, $scene, $owner] = $this->seedPrivateCampaignSceneContext();

        $handout = $this->createHandoutWithFile(
            campaign: $campaign,
            creator: $owner,
            scene: $scene,
            revealed: true,
            title: 'Weltkontext-Grenze',
        );

        $foreignWorld = World::factory()->create([
            'slug' => 'fremde-handout-mutation-welt',
            'is_active' => true,
            'position' => -903,
        ]);

        $params = [
            'world' => $foreignWorld,
            'campaign' => $campaign,
            'handout' => $handout,
        ];

        $this->actingAs($owner)
            ->patch(route('campaigns.handouts.reveal', $params))
            ->assertNotFound();

        $this->actingAs($owner)
            ->patch(route('campaigns.handouts.unreveal', $params))
            ->assertNotFound();

        $this->actingAs($owner)
            ->delete(route('campaigns.handouts.destroy', $params))
            ->assertNotFound();
    }

    public function test_player_index_visibility_respects_revealed_state_for_scene_and_campaign_handouts(): void
    {
        [$campaign, $scene, $owner, , $player] = $this->seedPrivateCampaignSceneContext();

        $this->createHandoutWithFile(
            campaign: $campaign,
            creator: $owner,
            scene: $scene,
            revealed: true,
            title: 'Revealed Scene Handout',
        );
        $this->createHandoutWithFile(
            campaign: $campaign,
            creator: $owner,
            scene: null,
            revealed: true,
            title: 'Revealed Campaign Handout',
        );
        $this->createHandoutWithFile(
            campaign: $campaign,
            creator: $owner,
            scene: $scene,
            revealed: false,
            title: 'Hidden Scene Handout',
        );
        $this->createHandoutWithFile(
            campaign: $campaign,
            creator: $owner,
            scene: null,
            revealed: false,
            title: 'Hidden Campaign Handout',
        );

        $this->actingAs($player)
            ->get(route('campaigns.handouts.index', [
                'world' => $campaign->world,
                'campaign' => $campaign,
            ]))
            ->assertOk()
            ->assertSee('Revealed Scene Handout')
            ->assertSee('Revealed Campaign Handout')
            ->assertDontSee('Hidden Scene Handout')
            ->assertDontSee('Hidden Campaign Handout');
    }

    public function test_reveal_state_controls_player_file_access_while_manager_access_stays_available(): void
    {
        [$campaign, $scene, , $gm, $player] = $this->seedPrivateCampaignSceneContext();

        $handout = $this->createHandoutWithFile(
            campaign: $campaign,
            creator: $gm,
            scene: $scene,
            revealed: false,
            title: 'Dateizugriff Handout',
        );

        $params = $this->routeParams($campaign, $handout);

        $this->actingAs($player)
            ->get(route('campaigns.handouts.file', $params))
            ->assertForbidden();

        $this->actingAs($gm)
            ->get(route('campaigns.handouts.file', $params))
            ->assertOk();

        $this->actingAs($gm)
            ->patch(route('campaigns.handouts.reveal', $params))
            ->assertRedirect(route('campaigns.handouts.show', $params));

        $this->actingAs($player)
            ->get(route('campaigns.handouts.file', $params))
            ->assertOk();

        $this->actingAs($gm)
            ->patch(route('campaigns.handouts.unreveal', $params))
            ->assertRedirect(route('campaigns.handouts.show', $params));

        $this->actingAs($player)
            ->get(route('campaigns.handouts.file', $params))
            ->assertForbidden();
    }

    /**
     * @return array{0: Campaign, 1: Scene, 2: User, 3: User, 4: User, 5: User}
     */
    private function seedPrivateCampaignSceneContext(): array
    {
        $owner = User::factory()->create();
        $gm = User::factory()->create();
        $player = User::factory()->create();
        $trustedPlayer = User::factory()->create();

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
        CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $trustedPlayer->id,
            'role' => CampaignMembershipRole::TRUSTED_PLAYER->value,
            'assigned_by' => $owner->id,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        return [$campaign, $scene, $owner, $gm, $player, $trustedPlayer];
    }

    private function createHandoutWithFile(
        Campaign $campaign,
        User $creator,
        ?Scene $scene,
        bool $revealed,
        string $title,
    ): Handout {
        $handout = Handout::factory()->create([
            'campaign_id' => $campaign->id,
            'scene_id' => $scene?->id,
            'created_by' => $creator->id,
            'updated_by' => null,
            'title' => $title,
            'revealed_at' => $revealed ? now() : null,
        ]);

        $handout
            ->addMedia(UploadedFile::fake()->image('handout-'.$handout->id.'.jpg', 1200, 700))
            ->toMediaCollection(Handout::HANDOUT_FILE_COLLECTION);

        return $handout->fresh(['media']) ?? $handout;
    }

    /**
     * @return array{world: World, campaign: Campaign, handout: Handout}
     */
    private function routeParams(Campaign $campaign, Handout $handout): array
    {
        return [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'handout' => $handout,
        ];
    }
}
