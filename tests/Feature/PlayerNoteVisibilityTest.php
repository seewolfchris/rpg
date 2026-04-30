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

class PlayerNoteVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_own_player_note(): void
    {
        [$campaign, $player] = $this->seedPrivateCampaignContext();
        $note = $this->createPlayerNote($campaign, $player, 'Eigene Notiz');

        $this->actingAs($player)
            ->get(route('campaigns.player-notes.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'playerNote' => $note,
            ]))
            ->assertOk()
            ->assertSee('Eigene Notiz');
    }

    public function test_foreign_player_note_is_not_visible_for_owner_gm_and_admin(): void
    {
        [$campaign, $player, $owner, $gm] = $this->seedPrivateCampaignContext();
        $admin = User::factory()->admin()->create();
        CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $admin->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => $owner->id,
        ]);

        $note = $this->createPlayerNote($campaign, $player, 'Fremde Notiz');

        foreach ([$owner, $gm, $admin] as $actor) {
            $this->actingAs($actor)
                ->get(route('campaigns.player-notes.show', [
                    'world' => $campaign->world,
                    'campaign' => $campaign,
                    'playerNote' => $note,
                ]))
                ->assertForbidden();
        }
    }

    public function test_non_member_cannot_access_private_campaign_player_notes(): void
    {
        [$campaign, $player] = $this->seedPrivateCampaignContext();
        $outsider = User::factory()->create();
        $note = $this->createPlayerNote($campaign, $player, 'Privat');

        $this->actingAs($outsider)
            ->get(route('campaigns.player-notes.index', [
                'world' => $campaign->world,
                'campaign' => $campaign,
            ]))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->get(route('campaigns.player-notes.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'playerNote' => $note,
            ]))
            ->assertForbidden();
    }

    public function test_cross_campaign_and_cross_world_player_note_access_is_rejected(): void
    {
        [$campaignA, $player] = $this->seedPrivateCampaignContext();
        $noteA = $this->createPlayerNote($campaignA, $player, 'A');

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
        $noteB = $this->createPlayerNote($campaignB, $player, 'B');

        $this->actingAs($player)
            ->get(route('campaigns.player-notes.show', [
                'world' => $campaignA->world,
                'campaign' => $campaignA,
                'playerNote' => $noteB,
            ]))
            ->assertNotFound();

        $foreignWorld = World::factory()->create([
            'slug' => 'player-note-visibility-foreign-world',
            'is_active' => true,
            'position' => -929,
        ]);

        $this->actingAs($player)
            ->get(route('campaigns.player-notes.show', [
                'world' => $foreignWorld,
                'campaign' => $campaignA,
                'playerNote' => $noteA,
            ]))
            ->assertNotFound();
    }

    public function test_index_lists_only_own_notes(): void
    {
        [$campaign, $player] = $this->seedPrivateCampaignContext();
        $otherUser = User::factory()->create();
        CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $otherUser->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => $player->id,
        ]);

        $own = $this->createPlayerNote($campaign, $player, 'Eigen');
        $foreign = $this->createPlayerNote($campaign, $otherUser, 'Fremd');

        $response = $this->actingAs($player)->get(route('campaigns.player-notes.index', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]));

        $response->assertOk();
        $response->assertSee($own->title);
        $response->assertDontSee($foreign->title);
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

        return [$campaign, $player, $owner, $gm];
    }

    private function createPlayerNote(Campaign $campaign, User $user, string $title): PlayerNote
    {
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $campaign->owner_id,
            'status' => 'open',
        ]);

        return PlayerNote::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'scene_id' => $scene->id,
            'title' => $title,
            'body' => 'Privater Inhalt',
        ]);
    }
}
