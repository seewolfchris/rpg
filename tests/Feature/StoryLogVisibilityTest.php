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

class StoryLogVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_can_view_revealed_story_log_entry(): void
    {
        [$campaign, $owner, $gm, $player] = $this->seedPrivateCampaignContext();
        $entry = $this->createStoryLogEntry($campaign, $owner, true, 'Freigegebenes Kapitel');

        $this->actingAs($player)
            ->get(route('campaigns.story-log.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'storyLogEntry' => $entry,
            ]))
            ->assertOk()
            ->assertSee('Freigegebenes Kapitel');
    }

    public function test_player_cannot_view_unrevealed_story_log_entry_but_gm_can(): void
    {
        [$campaign, $owner, $gm, $player] = $this->seedPrivateCampaignContext();
        $entry = $this->createStoryLogEntry($campaign, $owner, false, 'Verborgene Chronik');

        $this->actingAs($player)
            ->get(route('campaigns.story-log.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'storyLogEntry' => $entry,
            ]))
            ->assertForbidden();

        $this->actingAs($gm)
            ->get(route('campaigns.story-log.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'storyLogEntry' => $entry,
            ]))
            ->assertOk()
            ->assertSee('Verborgene Chronik');
    }

    public function test_non_member_cannot_view_private_campaign_story_log(): void
    {
        [$campaign, $owner] = $this->seedPrivateCampaignContext();
        $outsider = User::factory()->create();
        $entry = $this->createStoryLogEntry($campaign, $owner, true, 'Privates Kapitel');

        $this->actingAs($outsider)
            ->get(route('campaigns.story-log.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'storyLogEntry' => $entry,
            ]))
            ->assertForbidden();
    }

    public function test_cross_campaign_story_log_routes_are_rejected(): void
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

        $entryB = StoryLogEntry::factory()->create([
            'campaign_id' => $campaignB->id,
            'scene_id' => null,
            'created_by' => $owner->id,
            'title' => 'Fremde Kampagne',
            'revealed_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('campaigns.story-log.show', [
                'world' => $campaignA->world,
                'campaign' => $campaignA,
                'storyLogEntry' => $entryB,
            ]))
            ->assertNotFound();
    }

    public function test_cross_world_story_log_routes_are_rejected(): void
    {
        [$campaign, $owner] = $this->seedPrivateCampaignContext();
        $entry = $this->createStoryLogEntry($campaign, $owner, true, 'Weltgrenzen');

        $foreignWorld = World::factory()->create([
            'slug' => 'fremde-chronik-sichtbarkeit-welt',
            'is_active' => true,
            'position' => -970,
        ]);

        $this->actingAs($owner)
            ->get(route('campaigns.story-log.show', [
                'world' => $foreignWorld,
                'campaign' => $campaign,
                'storyLogEntry' => $entry,
            ]))
            ->assertNotFound();
    }

    public function test_player_index_shows_only_revealed_story_log_entries(): void
    {
        [$campaign, $owner, , $player] = $this->seedPrivateCampaignContext();
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
        ]);

        StoryLogEntry::factory()->create([
            'campaign_id' => $campaign->id,
            'scene_id' => null,
            'created_by' => $owner->id,
            'title' => 'Kapitel Offen',
            'revealed_at' => now(),
        ]);
        StoryLogEntry::factory()->create([
            'campaign_id' => $campaign->id,
            'scene_id' => $scene->id,
            'created_by' => $owner->id,
            'title' => 'Kapitel Verborgen',
            'revealed_at' => null,
        ]);

        $response = $this->actingAs($player)->get(route('campaigns.story-log.index', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]));

        $response->assertOk();
        $response->assertSee('Kapitel Offen');
        $response->assertDontSee('Kapitel Verborgen');
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

    private function createStoryLogEntry(Campaign $campaign, User $creator, bool $revealed, string $title): StoryLogEntry
    {
        return StoryLogEntry::factory()->create([
            'campaign_id' => $campaign->id,
            'scene_id' => null,
            'created_by' => $creator->id,
            'title' => $title,
            'body' => 'Zusammenfassung',
            'revealed_at' => $revealed ? now() : null,
        ]);
    }
}
