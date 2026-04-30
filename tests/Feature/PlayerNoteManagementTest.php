<?php

namespace Tests\Feature;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\Character;
use App\Models\PlayerNote;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerNoteManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_user_can_create_update_and_delete_own_player_note(): void
    {
        [$campaign, $scene, $player] = $this->seedCampaignContext();
        $character = Character::factory()->create([
            'user_id' => $player->id,
            'world_id' => $campaign->world_id,
        ]);

        $createResponse = $this->actingAs($player)->post(route('campaigns.player-notes.store', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]), [
            'title' => 'Eigene Notiz',
            'body' => "Erster Stand.\nOffene Frage.",
            'scene_id' => $scene->id,
            'character_id' => $character->id,
            'sort_order' => 3,
        ]);

        $playerNote = PlayerNote::query()
            ->where('campaign_id', $campaign->id)
            ->where('user_id', $player->id)
            ->firstOrFail();

        $createResponse->assertRedirect(route('campaigns.player-notes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'playerNote' => $playerNote,
        ]));

        $this->assertDatabaseHas('player_notes', [
            'id' => $playerNote->id,
            'campaign_id' => $campaign->id,
            'user_id' => $player->id,
            'scene_id' => $scene->id,
            'character_id' => $character->id,
            'title' => 'Eigene Notiz',
            'sort_order' => 3,
        ]);

        $updateResponse = $this->actingAs($player)->patch(route('campaigns.player-notes.update', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'playerNote' => $playerNote,
        ]), [
            'title' => 'Eigene Notiz (aktualisiert)',
            'body' => 'Neuer Stand.',
            'scene_id' => $scene->id,
            'character_id' => $character->id,
            'sort_order' => 7,
        ]);

        $updateResponse->assertRedirect(route('campaigns.player-notes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'playerNote' => $playerNote,
        ]));
        $this->assertDatabaseHas('player_notes', [
            'id' => $playerNote->id,
            'title' => 'Eigene Notiz (aktualisiert)',
            'sort_order' => 7,
        ]);

        $this->actingAs($player)->delete(route('campaigns.player-notes.destroy', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'playerNote' => $playerNote,
        ]))->assertRedirect(route('campaigns.player-notes.index', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]));

        $this->assertDatabaseMissing('player_notes', ['id' => $playerNote->id]);
    }

    public function test_gm_owner_admin_and_other_players_cannot_access_foreign_player_notes(): void
    {
        [$campaign, $scene, $player, $owner, $gm, $trustedPlayer] = $this->seedCampaignContext();
        $admin = User::factory()->admin()->create();
        CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $admin->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => $owner->id,
        ]);

        $note = $this->createPlayerNote($campaign, $player, $scene, 'Nur fuer Besitzer');

        foreach ([$owner, $gm, $trustedPlayer, $admin] as $actor) {
            $this->actingAs($actor)->get(route('campaigns.player-notes.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'playerNote' => $note,
            ]))->assertForbidden();

            $this->actingAs($actor)->get(route('campaigns.player-notes.edit', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'playerNote' => $note,
            ]))->assertForbidden();

            $this->actingAs($actor)->patch(route('campaigns.player-notes.update', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'playerNote' => $note,
            ]), [
                'title' => 'Manipulation',
                'body' => 'x',
                'scene_id' => null,
                'character_id' => null,
            ])->assertForbidden();

            $this->actingAs($actor)->delete(route('campaigns.player-notes.destroy', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'playerNote' => $note,
            ]))->assertForbidden();
        }
    }

    public function test_non_member_cannot_create_player_note_in_private_campaign(): void
    {
        [$campaign] = $this->seedCampaignContext();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->post(route('campaigns.player-notes.store', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]), [
            'title' => 'Kein Zugriff',
            'body' => 'Sollte nicht erstellt werden.',
        ])->assertForbidden();

        $this->assertDatabaseCount('player_notes', 0);
    }

    public function test_store_and_update_reject_scene_id_from_other_campaign(): void
    {
        [$campaign, $scene, $player] = $this->seedCampaignContext();
        $note = $this->createPlayerNote($campaign, $player, $scene, 'Basis');

        $otherCampaign = Campaign::factory()->create([
            'owner_id' => $player->id,
            'world_id' => $campaign->world_id,
            'is_public' => false,
            'status' => 'active',
        ]);
        $otherScene = Scene::factory()->create([
            'campaign_id' => $otherCampaign->id,
            'created_by' => $player->id,
            'status' => 'open',
        ]);

        $storeResponse = $this->actingAs($player)
            ->from(route('campaigns.player-notes.create', [
                'world' => $campaign->world,
                'campaign' => $campaign,
            ]))
            ->post(route('campaigns.player-notes.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
            ]), [
                'title' => 'Cross Scene',
                'scene_id' => $otherScene->id,
            ]);

        $storeResponse->assertRedirect(route('campaigns.player-notes.create', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]));
        $storeResponse->assertSessionHasErrors('scene_id');

        $updateResponse = $this->actingAs($player)
            ->from(route('campaigns.player-notes.edit', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'playerNote' => $note,
            ]))
            ->patch(route('campaigns.player-notes.update', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'playerNote' => $note,
            ]), [
                'title' => 'Cross Scene',
                'body' => 'x',
                'scene_id' => $otherScene->id,
            ]);

        $updateResponse->assertRedirect(route('campaigns.player-notes.edit', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'playerNote' => $note,
        ]));
        $updateResponse->assertSessionHasErrors('scene_id');
    }

    public function test_store_and_update_reject_character_id_from_other_user_or_wrong_world(): void
    {
        [$campaign, $scene, $player] = $this->seedCampaignContext();
        $note = $this->createPlayerNote($campaign, $player, $scene, 'Basis');
        $otherUser = User::factory()->create();

        $foreignCharacter = Character::factory()->create([
            'user_id' => $otherUser->id,
            'world_id' => $campaign->world_id,
        ]);

        $wrongWorld = World::factory()->create([
            'slug' => 'player-note-fremde-char-welt',
            'is_active' => true,
            'position' => -931,
        ]);
        $wrongWorldCharacter = Character::factory()->create([
            'user_id' => $player->id,
            'world_id' => $wrongWorld->id,
        ]);

        foreach ([$foreignCharacter->id, $wrongWorldCharacter->id] as $invalidCharacterId) {
            $storeResponse = $this->actingAs($player)
                ->from(route('campaigns.player-notes.create', [
                    'world' => $campaign->world,
                    'campaign' => $campaign,
                ]))
                ->post(route('campaigns.player-notes.store', [
                    'world' => $campaign->world,
                    'campaign' => $campaign,
                ]), [
                    'title' => 'Invalid Character',
                    'character_id' => $invalidCharacterId,
                ]);

            $storeResponse->assertRedirect(route('campaigns.player-notes.create', [
                'world' => $campaign->world,
                'campaign' => $campaign,
            ]));
            $storeResponse->assertSessionHasErrors('character_id');

            $updateResponse = $this->actingAs($player)
                ->from(route('campaigns.player-notes.edit', [
                    'world' => $campaign->world,
                    'campaign' => $campaign,
                    'playerNote' => $note,
                ]))
                ->patch(route('campaigns.player-notes.update', [
                    'world' => $campaign->world,
                    'campaign' => $campaign,
                    'playerNote' => $note,
                ]), [
                    'title' => 'Invalid Character',
                    'body' => 'x',
                    'character_id' => $invalidCharacterId,
                ]);

            $updateResponse->assertRedirect(route('campaigns.player-notes.edit', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'playerNote' => $note,
            ]));
            $updateResponse->assertSessionHasErrors('character_id');
        }
    }

    public function test_cross_campaign_and_cross_world_player_note_routes_are_rejected(): void
    {
        [$campaignA, $sceneA, $player] = $this->seedCampaignContext();
        $noteA = $this->createPlayerNote($campaignA, $player, $sceneA, 'A');

        $campaignB = Campaign::factory()->create([
            'owner_id' => $player->id,
            'world_id' => $campaignA->world_id,
            'is_public' => false,
            'status' => 'active',
        ]);
        CampaignMembership::factory()->create([
            'campaign_id' => $campaignB->id,
            'user_id' => $player->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => $player->id,
        ]);

        $noteB = PlayerNote::factory()->create([
            'campaign_id' => $campaignB->id,
            'user_id' => $player->id,
            'scene_id' => null,
            'character_id' => null,
            'title' => 'B',
        ]);

        $this->actingAs($player)->get(route('campaigns.player-notes.show', [
            'world' => $campaignA->world,
            'campaign' => $campaignA,
            'playerNote' => $noteB,
        ]))->assertNotFound();

        $this->actingAs($player)->get(route('campaigns.player-notes.edit', [
            'world' => $campaignA->world,
            'campaign' => $campaignA,
            'playerNote' => $noteB,
        ]))->assertNotFound();

        $this->actingAs($player)->patch(route('campaigns.player-notes.update', [
            'world' => $campaignA->world,
            'campaign' => $campaignA,
            'playerNote' => $noteB,
        ]), [
            'title' => 'x',
            'body' => 'x',
        ])->assertNotFound();

        $foreignWorld = World::factory()->create([
            'slug' => 'player-note-cross-world',
            'is_active' => true,
            'position' => -930,
        ]);

        $this->actingAs($player)->get(route('campaigns.player-notes.show', [
            'world' => $foreignWorld,
            'campaign' => $campaignA,
            'playerNote' => $noteA,
        ]))->assertNotFound();
    }

    public function test_index_shows_only_own_notes(): void
    {
        [$campaign, $scene, $player] = $this->seedCampaignContext();
        $otherUser = User::factory()->create();
        CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $otherUser->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => $player->id,
        ]);

        $own = $this->createPlayerNote($campaign, $player, $scene, 'Eigene Notiz');
        $foreign = $this->createPlayerNote($campaign, $otherUser, $scene, 'Fremde Notiz');

        $response = $this->actingAs($player)->get(route('campaigns.player-notes.index', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]));

        $response->assertOk();
        $response->assertSee($own->title);
        $response->assertDontSee($foreign->title);
    }

    /**
     * @return array{0: Campaign, 1: Scene, 2: User, 3: User, 4: User, 5: User}
     */
    private function seedCampaignContext(): array
    {
        $owner = User::factory()->gm()->create();
        $gm = User::factory()->create();
        $player = User::factory()->create();
        $trustedPlayer = User::factory()->create();

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

        return [$campaign, $scene, $player, $owner, $gm, $trustedPlayer];
    }

    private function createPlayerNote(Campaign $campaign, User $user, ?Scene $scene, string $title): PlayerNote
    {
        return PlayerNote::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'scene_id' => $scene?->id,
            'character_id' => null,
            'title' => $title,
            'body' => 'Privater Inhalt',
        ]);
    }
}
