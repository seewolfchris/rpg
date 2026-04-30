<?php

namespace Tests\Feature;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\Scene;
use App\Models\StoryLogEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoryLogManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_gm_can_create_story_log_entry(): void
    {
        [$campaign, $scene, , $gm] = $this->seedCampaignContext();

        $response = $this->actingAs($gm)->post(route('campaigns.story-log.store', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]), [
            'title' => 'Kapitel 3: Der Schwur',
            'body' => "Die Gruppe erreichte das Nordtor.\nDer Schwur wurde geleistet.",
            'scene_id' => $scene->id,
            'sort_order' => 10,
        ]);

        $storyLogEntry = StoryLogEntry::query()->where('campaign_id', $campaign->id)->firstOrFail();

        $response->assertRedirect(route('campaigns.story-log.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'storyLogEntry' => $storyLogEntry,
        ]));

        $this->assertDatabaseHas('story_log_entries', [
            'id' => $storyLogEntry->id,
            'campaign_id' => $campaign->id,
            'scene_id' => $scene->id,
            'created_by' => $gm->id,
            'title' => 'Kapitel 3: Der Schwur',
            'sort_order' => 10,
        ]);
    }

    public function test_player_and_trusted_player_cannot_create_story_log_entry(): void
    {
        [$campaign, $scene, $player, , $trustedPlayer] = $this->seedCampaignContext();

        foreach ([$player, $trustedPlayer] as $actor) {
            $response = $this->actingAs($actor)->post(route('campaigns.story-log.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
            ]), [
                'title' => 'Verbotener Eintrag '.$actor->id,
                'scene_id' => $scene->id,
            ]);

            $response->assertForbidden();
        }

        $this->assertDatabaseCount('story_log_entries', 0);
    }

    public function test_player_and_trusted_player_cannot_access_story_log_management_routes_directly(): void
    {
        [$campaign, $scene, $player, $gm, $trustedPlayer] = $this->seedCampaignContext();
        $storyLogEntry = $this->createStoryLogEntry($campaign, $gm, $scene, false);

        foreach ([$player, $trustedPlayer] as $actor) {
            $this->actingAs($actor)->get(route('campaigns.story-log.create', [
                'world' => $campaign->world,
                'campaign' => $campaign,
            ]))->assertForbidden();

            $this->actingAs($actor)->post(route('campaigns.story-log.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
            ]), [
                'title' => 'Verbotener Eintrag '.$actor->id,
                'scene_id' => $scene->id,
            ])->assertForbidden();

            $this->actingAs($actor)->get(route('campaigns.story-log.edit', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'storyLogEntry' => $storyLogEntry,
            ]))->assertForbidden();

            $this->actingAs($actor)->patch(route('campaigns.story-log.update', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'storyLogEntry' => $storyLogEntry,
            ]), [
                'title' => 'Manipuliert',
                'body' => 'x',
                'scene_id' => $scene->id,
            ])->assertForbidden();

            $this->actingAs($actor)->delete(route('campaigns.story-log.destroy', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'storyLogEntry' => $storyLogEntry,
            ]))->assertForbidden();

            $this->actingAs($actor)->patch(route('campaigns.story-log.reveal', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'storyLogEntry' => $storyLogEntry,
            ]))->assertForbidden();

            $this->actingAs($actor)->patch(route('campaigns.story-log.unreveal', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'storyLogEntry' => $storyLogEntry,
            ]))->assertForbidden();
        }
    }

    public function test_gm_can_update_story_log_entry(): void
    {
        [$campaign, $scene, , $gm] = $this->seedCampaignContext();
        $storyLogEntry = $this->createStoryLogEntry($campaign, $gm, $scene);

        $response = $this->actingAs($gm)->patch(route('campaigns.story-log.update', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'storyLogEntry' => $storyLogEntry,
        ]), [
            'title' => 'Kapitel 3: Der Schwur (aktualisiert)',
            'body' => 'Neue Zusammenfassung.',
            'scene_id' => $scene->id,
            'sort_order' => 15,
        ]);

        $response->assertRedirect(route('campaigns.story-log.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'storyLogEntry' => $storyLogEntry,
        ]));

        $this->assertDatabaseHas('story_log_entries', [
            'id' => $storyLogEntry->id,
            'title' => 'Kapitel 3: Der Schwur (aktualisiert)',
            'sort_order' => 15,
            'updated_by' => $gm->id,
        ]);
    }

    public function test_player_cannot_update_story_log_entry(): void
    {
        [$campaign, $scene, $player, $gm] = $this->seedCampaignContext();
        $storyLogEntry = $this->createStoryLogEntry($campaign, $gm, $scene);

        $this->actingAs($player)->patch(route('campaigns.story-log.update', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'storyLogEntry' => $storyLogEntry,
        ]), [
            'title' => 'Manipuliert',
            'body' => 'x',
            'scene_id' => $scene->id,
        ])->assertForbidden();
    }

    public function test_gm_can_delete_story_log_entry(): void
    {
        [$campaign, $scene, , $gm] = $this->seedCampaignContext();
        $storyLogEntry = $this->createStoryLogEntry($campaign, $gm, $scene, true, 'Löschbarer Eintrag');

        $this->actingAs($gm)->delete(route('campaigns.story-log.destroy', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'storyLogEntry' => $storyLogEntry,
        ]))->assertRedirect(route('campaigns.story-log.index', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]));

        $this->assertDatabaseMissing('story_log_entries', [
            'id' => $storyLogEntry->id,
        ]);
    }

    public function test_player_cannot_delete_story_log_entry(): void
    {
        [$campaign, $scene, $player, $gm] = $this->seedCampaignContext();
        $storyLogEntry = $this->createStoryLogEntry($campaign, $gm, $scene);

        $this->actingAs($player)->delete(route('campaigns.story-log.destroy', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'storyLogEntry' => $storyLogEntry,
        ]))->assertForbidden();
    }

    public function test_gm_can_reveal_and_unreveal_story_log_entry(): void
    {
        [$campaign, $scene, , $gm] = $this->seedCampaignContext();
        $storyLogEntry = $this->createStoryLogEntry($campaign, $gm, $scene, false);

        $this->actingAs($gm)->patch(route('campaigns.story-log.reveal', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'storyLogEntry' => $storyLogEntry,
        ]))->assertRedirect();

        $this->assertNotNull($storyLogEntry->fresh()->revealed_at);

        $this->actingAs($gm)->patch(route('campaigns.story-log.unreveal', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'storyLogEntry' => $storyLogEntry,
        ]))->assertRedirect();

        $this->assertNull($storyLogEntry->fresh()->revealed_at);
    }

    public function test_player_cannot_reveal_or_unreveal_story_log_entry(): void
    {
        [$campaign, $scene, $player, $gm] = $this->seedCampaignContext();
        $storyLogEntry = $this->createStoryLogEntry($campaign, $gm, $scene, false);

        $this->actingAs($player)->patch(route('campaigns.story-log.reveal', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'storyLogEntry' => $storyLogEntry,
        ]))->assertForbidden();

        $this->actingAs($player)->patch(route('campaigns.story-log.unreveal', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'storyLogEntry' => $storyLogEntry,
        ]))->assertForbidden();
    }

    public function test_store_rejects_scene_id_from_other_campaign(): void
    {
        [$campaign, , , $gm] = $this->seedCampaignContext();

        $otherCampaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'world_id' => $campaign->world_id,
            'is_public' => false,
            'status' => 'active',
        ]);
        $otherScene = Scene::factory()->create([
            'campaign_id' => $otherCampaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
        ]);

        $response = $this->actingAs($gm)
            ->from(route('campaigns.story-log.create', [
                'world' => $campaign->world,
                'campaign' => $campaign,
            ]))
            ->post(route('campaigns.story-log.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
            ]), [
                'title' => 'Cross-Campaign Scene',
                'scene_id' => $otherScene->id,
            ]);

        $response->assertRedirect(route('campaigns.story-log.create', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]));
        $response->assertSessionHasErrors('scene_id');
    }

    public function test_update_rejects_scene_id_from_other_campaign(): void
    {
        [$campaign, $scene, , $gm] = $this->seedCampaignContext();
        $storyLogEntry = $this->createStoryLogEntry($campaign, $gm, $scene);

        $otherCampaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'world_id' => $campaign->world_id,
            'is_public' => false,
            'status' => 'active',
        ]);
        $otherScene = Scene::factory()->create([
            'campaign_id' => $otherCampaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
        ]);

        $response = $this->actingAs($gm)
            ->from(route('campaigns.story-log.edit', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'storyLogEntry' => $storyLogEntry,
            ]))
            ->patch(route('campaigns.story-log.update', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'storyLogEntry' => $storyLogEntry,
            ]), [
                'title' => 'Aktualisiert',
                'body' => 'x',
                'scene_id' => $otherScene->id,
            ]);

        $response->assertRedirect(route('campaigns.story-log.edit', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'storyLogEntry' => $storyLogEntry,
        ]));
        $response->assertSessionHasErrors('scene_id');
    }

    public function test_cross_campaign_and_cross_world_story_log_routes_are_rejected(): void
    {
        [$campaignA, $sceneA, , $gm] = $this->seedCampaignContext();
        $storyLogEntryA = $this->createStoryLogEntry($campaignA, $gm, $sceneA, true, 'A');

        $campaignB = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'world_id' => $campaignA->world_id,
            'is_public' => false,
            'status' => 'active',
        ]);
        CampaignMembership::factory()->create([
            'campaign_id' => $campaignB->id,
            'user_id' => $gm->id,
            'role' => CampaignMembershipRole::GM->value,
            'assigned_by' => $gm->id,
        ]);

        $storyLogEntryB = StoryLogEntry::factory()->create([
            'campaign_id' => $campaignB->id,
            'scene_id' => null,
            'created_by' => $gm->id,
            'title' => 'B',
            'revealed_at' => now(),
        ]);

        $this->actingAs($gm)->get(route('campaigns.story-log.show', [
            'world' => $campaignA->world,
            'campaign' => $campaignA,
            'storyLogEntry' => $storyLogEntryB,
        ]))->assertNotFound();

        $this->actingAs($gm)->get(route('campaigns.story-log.edit', [
            'world' => $campaignA->world,
            'campaign' => $campaignA,
            'storyLogEntry' => $storyLogEntryB,
        ]))->assertNotFound();

        $this->actingAs($gm)->patch(route('campaigns.story-log.update', [
            'world' => $campaignA->world,
            'campaign' => $campaignA,
            'storyLogEntry' => $storyLogEntryB,
        ]), [
            'title' => 'Manipuliert',
            'body' => 'x',
        ])->assertNotFound();

        $this->actingAs($gm)->delete(route('campaigns.story-log.destroy', [
            'world' => $campaignA->world,
            'campaign' => $campaignA,
            'storyLogEntry' => $storyLogEntryB,
        ]))->assertNotFound();

        $foreignWorld = \App\Models\World::factory()->create([
            'slug' => 'fremde-chronik-welt',
            'is_active' => true,
            'position' => -980,
        ]);

        $this->actingAs($gm)->get(route('campaigns.story-log.show', [
            'world' => $foreignWorld,
            'campaign' => $campaignA,
            'storyLogEntry' => $storyLogEntryA,
        ]))->assertNotFound();
    }

    public function test_player_sees_no_management_actions_on_story_log_index_and_show(): void
    {
        [$campaign, $scene, $player, $gm] = $this->seedCampaignContext();
        $storyLogEntry = $this->createStoryLogEntry($campaign, $gm, $scene, true, 'Spieleransicht');

        $indexResponse = $this->actingAs($player)->get(route('campaigns.story-log.index', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]));

        $indexResponse->assertOk();
        $indexResponse->assertDontSee(route('campaigns.story-log.create', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]), false);
        $indexResponse->assertDontSee(route('campaigns.story-log.edit', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'storyLogEntry' => $storyLogEntry,
        ]), false);

        $showResponse = $this->actingAs($player)->get(route('campaigns.story-log.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'storyLogEntry' => $storyLogEntry,
        ]));

        $showResponse->assertOk();
        $showResponse->assertDontSee(route('campaigns.story-log.edit', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'storyLogEntry' => $storyLogEntry,
        ]), false);
        $showResponse->assertDontSee(route('campaigns.story-log.reveal', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'storyLogEntry' => $storyLogEntry,
        ]), false);
        $showResponse->assertDontSee(route('campaigns.story-log.unreveal', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'storyLogEntry' => $storyLogEntry,
        ]), false);
        $showResponse->assertDontSee('Chronik-Eintrag wirklich löschen?');
    }

    /**
     * @return array{0: Campaign, 1: Scene, 2: User, 3: User, 4: User}
     */
    private function seedCampaignContext(): array
    {
        $owner = User::factory()->gm()->create();
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
        ]);

        return [$campaign, $scene, $player, $gm, $trustedPlayer];
    }

    private function createStoryLogEntry(
        Campaign $campaign,
        User $creator,
        ?Scene $scene = null,
        bool $revealed = false,
        ?string $title = null,
    ): StoryLogEntry {
        return StoryLogEntry::factory()->create([
            'campaign_id' => $campaign->id,
            'scene_id' => $scene?->id,
            'created_by' => $creator->id,
            'updated_by' => null,
            'title' => $title ?? 'Chronik '.uniqid('', true),
            'body' => 'Kurzer Eintragstext',
            'revealed_at' => $revealed ? now() : null,
        ]);
    }
}
