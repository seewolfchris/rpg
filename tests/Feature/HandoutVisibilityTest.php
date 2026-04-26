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

class HandoutVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_player_can_view_revealed_handout_and_file(): void
    {
        [$campaign, $owner, $gm, $player] = $this->seedPrivateCampaignContext();

        $handout = $this->createHandoutWithFile($campaign, $owner, true, 'Freigegebene Karte');

        $this->actingAs($player)
            ->get(route('campaigns.handouts.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'handout' => $handout,
            ]))
            ->assertOk()
            ->assertSee('Freigegebene Karte');

        $this->actingAs($player)
            ->get(route('campaigns.handouts.file', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'handout' => $handout,
            ]))
            ->assertOk();

        $this->actingAs($gm)
            ->get(route('campaigns.handouts.file', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'handout' => $handout,
            ]))
            ->assertOk();
    }

    public function test_player_cannot_view_unrevealed_handout_or_file_but_gm_can(): void
    {
        [$campaign, $owner, $gm, $player] = $this->seedPrivateCampaignContext();

        $handout = $this->createHandoutWithFile($campaign, $owner, false, 'Verborgene Notiz');

        $this->actingAs($player)
            ->get(route('campaigns.handouts.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'handout' => $handout,
            ]))
            ->assertForbidden();

        $this->actingAs($player)
            ->get(route('campaigns.handouts.file', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'handout' => $handout,
            ]))
            ->assertForbidden();

        $this->actingAs($gm)
            ->get(route('campaigns.handouts.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'handout' => $handout,
            ]))
            ->assertOk();

        $this->actingAs($gm)
            ->get(route('campaigns.handouts.file', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'handout' => $handout,
            ]))
            ->assertOk();
    }

    public function test_non_member_cannot_view_handouts_in_private_campaign(): void
    {
        [$campaign, $owner] = $this->seedPrivateCampaignContext();
        $outsider = User::factory()->create();
        $handout = $this->createHandoutWithFile($campaign, $owner, true, 'Privater Beweis');

        $this->actingAs($outsider)
            ->get(route('campaigns.handouts.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'handout' => $handout,
            ]))
            ->assertForbidden();
    }

    public function test_cross_campaign_handout_routes_are_rejected(): void
    {
        [$campaignA, $owner] = $this->seedPrivateCampaignContext();

        $campaignB = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'world_id' => $campaignA->world_id,
            'is_public' => false,
            'status' => 'active',
        ]);

        CampaignMembership::factory()->create([
            'campaign_id' => $campaignB->id,
            'user_id' => $owner->id,
            'role' => CampaignMembershipRole::GM->value,
            'assigned_by' => $owner->id,
        ]);

        $handoutB = $this->createHandoutWithFile($campaignB, $owner, true, 'Anderes Campaign-Handout');

        $this->actingAs($owner)
            ->get(route('campaigns.handouts.show', [
                'world' => $campaignA->world,
                'campaign' => $campaignA,
                'handout' => $handoutB,
            ]))
            ->assertNotFound();

        $this->actingAs($owner)
            ->get(route('campaigns.handouts.edit', [
                'world' => $campaignA->world,
                'campaign' => $campaignA,
                'handout' => $handoutB,
            ]))
            ->assertNotFound();

        $this->actingAs($owner)
            ->patch(route('campaigns.handouts.update', [
                'world' => $campaignA->world,
                'campaign' => $campaignA,
                'handout' => $handoutB,
            ]), [
                'title' => 'Manipuliert',
                'description' => 'x',
            ])
            ->assertNotFound();

        $this->actingAs($owner)
            ->get(route('campaigns.handouts.file', [
                'world' => $campaignA->world,
                'campaign' => $campaignA,
                'handout' => $handoutB,
            ]))
            ->assertNotFound();
    }

    public function test_cross_world_handout_routes_are_rejected(): void
    {
        [$campaign, $owner] = $this->seedPrivateCampaignContext();
        $handout = $this->createHandoutWithFile($campaign, $owner, true, 'Weltgrenzen');

        $foreignWorld = World::factory()->create([
            'slug' => 'fremde-handout-welt',
            'is_active' => true,
            'position' => -999,
        ]);

        $this->actingAs($owner)
            ->get(route('campaigns.handouts.show', [
                'world' => $foreignWorld,
                'campaign' => $campaign,
                'handout' => $handout,
            ]))
            ->assertNotFound();

        $this->actingAs($owner)
            ->get(route('campaigns.handouts.file', [
                'world' => $foreignWorld,
                'campaign' => $campaign,
                'handout' => $handout,
            ]))
            ->assertNotFound();
    }

    public function test_file_route_returns_404_when_handout_has_no_primary_media(): void
    {
        [$campaign, $owner] = $this->seedPrivateCampaignContext();

        $handout = Handout::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'revealed_at' => now(),
            'title' => 'Datei fehlt',
        ]);

        $this->actingAs($owner)
            ->get(route('campaigns.handouts.file', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'handout' => $handout,
            ]))
            ->assertNotFound();
    }

    /**
     * @return array{0: Campaign, 1: User, 2: User, 3: User}
     */
    private function seedPrivateCampaignContext(): array
    {
        $owner = User::factory()->gm()->create();
        $gm = User::factory()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'is_public' => false,
            'status' => 'active',
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

        return [$campaign, $owner, $gm, $player];
    }

    private function createHandoutWithFile(Campaign $campaign, User $creator, bool $revealed, string $title): Handout
    {
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $creator->id,
            'status' => 'open',
        ]);

        $handout = Handout::factory()->create([
            'campaign_id' => $campaign->id,
            'scene_id' => $scene->id,
            'created_by' => $creator->id,
            'title' => $title,
            'revealed_at' => $revealed ? now() : null,
        ]);

        $handout
            ->addMedia(UploadedFile::fake()->image('handout-'.$handout->id.'.jpg', 1200, 700))
            ->toMediaCollection(Handout::HANDOUT_FILE_COLLECTION);

        return $handout->fresh(['media']) ?? $handout;
    }
}
