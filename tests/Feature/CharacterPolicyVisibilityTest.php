<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\Character;
use App\Models\PlayerNote;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterPolicyVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_player_can_view_other_players_character_when_character_posted_in_same_campaign(): void
    {
        $world = World::factory()->create();
        $owner = User::factory()->gm()->create();
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        [$campaign, $scene] = $this->createCampaignWithScene($world, $owner, false);

        $this->addMembership($campaign, $author, CampaignMembershipRole::PLAYER);
        $this->addMembership($campaign, $viewer, CampaignMembershipRole::PLAYER);

        $character = Character::factory()->create([
            'user_id' => $author->id,
            'world_id' => $world->id,
        ]);
        $this->createCharacterPost($scene, $author, $character);

        $this->actingAs($viewer)
            ->get(route('characters.show', $character))
            ->assertOk()
            ->assertSeeText($character->name);
    }

    public function test_campaign_player_cannot_view_character_from_other_campaign(): void
    {
        $world = World::factory()->create();
        $ownerA = User::factory()->gm()->create();
        $ownerB = User::factory()->gm()->create();
        $viewer = User::factory()->create();
        $author = User::factory()->create();

        [$campaignA] = $this->createCampaignWithScene($world, $ownerA, false);
        [$campaignB, $sceneB] = $this->createCampaignWithScene($world, $ownerB, false);

        $this->addMembership($campaignA, $viewer, CampaignMembershipRole::PLAYER);
        $this->addMembership($campaignB, $author, CampaignMembershipRole::PLAYER);

        $character = Character::factory()->create([
            'user_id' => $author->id,
            'world_id' => $world->id,
        ]);
        $this->createCharacterPost($sceneB, $author, $character);

        $this->actingAs($viewer)
            ->get(route('characters.show', $character))
            ->assertForbidden();
    }

    public function test_campaign_player_cannot_view_character_from_other_world(): void
    {
        $worldA = World::factory()->create();
        $worldB = World::factory()->create();
        $ownerA = User::factory()->gm()->create();
        $ownerB = User::factory()->gm()->create();
        $viewer = User::factory()->create();
        $author = User::factory()->create();

        [$campaignA] = $this->createCampaignWithScene($worldA, $ownerA, false);
        [$campaignB, $sceneB] = $this->createCampaignWithScene($worldB, $ownerB, false);

        $this->addMembership($campaignA, $viewer, CampaignMembershipRole::PLAYER);
        $this->addMembership($campaignB, $author, CampaignMembershipRole::PLAYER);

        $character = Character::factory()->create([
            'user_id' => $author->id,
            'world_id' => $worldB->id,
        ]);
        $this->createCharacterPost($sceneB, $author, $character);

        $this->actingAs($viewer)
            ->get(route('characters.show', $character))
            ->assertForbidden();
    }

    public function test_non_member_cannot_view_character_from_private_campaign(): void
    {
        $world = World::factory()->create();
        $owner = User::factory()->gm()->create();
        $author = User::factory()->create();
        $outsider = User::factory()->create();
        [$campaign, $scene] = $this->createCampaignWithScene($world, $owner, false);

        $this->addMembership($campaign, $author, CampaignMembershipRole::PLAYER);

        $character = Character::factory()->create([
            'user_id' => $author->id,
            'world_id' => $world->id,
        ]);
        $this->createCharacterPost($scene, $author, $character);

        $this->actingAs($outsider)
            ->get(route('characters.show', $character))
            ->assertForbidden();
    }

    public function test_campaign_owner_and_campaign_gm_can_still_view_relevant_character(): void
    {
        $world = World::factory()->create();
        $owner = User::factory()->gm()->create();
        $gm = User::factory()->create();
        $author = User::factory()->create();
        [$campaign, $scene] = $this->createCampaignWithScene($world, $owner, false);

        $this->addMembership($campaign, $gm, CampaignMembershipRole::GM);
        $this->addMembership($campaign, $author, CampaignMembershipRole::PLAYER);

        $character = Character::factory()->create([
            'user_id' => $author->id,
            'world_id' => $world->id,
        ]);
        $this->createCharacterPost($scene, $author, $character);

        $this->actingAs($owner)
            ->get(route('characters.show', $character))
            ->assertOk();

        $this->actingAs($gm)
            ->get(route('characters.show', $character))
            ->assertOk();
    }

    public function test_owner_can_still_view_own_character_without_campaign_post(): void
    {
        $owner = User::factory()->create();
        $character = Character::factory()->create([
            'user_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('characters.show', $character))
            ->assertOk();
    }

    public function test_participant_view_does_not_expand_update_or_delete_permissions(): void
    {
        $world = World::factory()->create();
        $owner = User::factory()->gm()->create();
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        [$campaign, $scene] = $this->createCampaignWithScene($world, $owner, false);

        $this->addMembership($campaign, $author, CampaignMembershipRole::PLAYER);
        $this->addMembership($campaign, $viewer, CampaignMembershipRole::PLAYER);

        $character = Character::factory()->create([
            'user_id' => $author->id,
            'world_id' => $world->id,
        ]);
        $this->createCharacterPost($scene, $author, $character);

        $this->assertFalse($viewer->can('update', $character));
        $this->assertFalse($viewer->can('delete', $character));

        $this->actingAs($viewer)
            ->get(route('characters.edit', $character))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->delete(route('characters.destroy', $character))
            ->assertForbidden();
    }

    public function test_character_show_hides_gm_secret_and_gm_note_for_participant_viewers(): void
    {
        $world = World::factory()->create();
        $owner = User::factory()->gm()->create();
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        [$campaign, $scene] = $this->createCampaignWithScene($world, $owner, false);

        $this->addMembership($campaign, $author, CampaignMembershipRole::PLAYER);
        $this->addMembership($campaign, $viewer, CampaignMembershipRole::PLAYER);

        $character = Character::factory()->create([
            'user_id' => $author->id,
            'world_id' => $world->id,
            'concept' => 'Archivsucher',
            'gm_secret' => 'VERBORGENES_GM_SECRET',
            'gm_note' => 'VERBORGENE_GM_NOTE',
        ]);
        $this->createCharacterPost($scene, $author, $character);

        $this->actingAs($viewer)
            ->get(route('characters.show', $character))
            ->assertOk()
            ->assertDontSeeText('VERBORGENES_GM_SECRET')
            ->assertDontSeeText('VERBORGENE_GM_NOTE')
            ->assertDontSeeText('Geheimnis (GM)')
            ->assertDontSeeText('GM-Notiz')
            ->assertSeeText('Konzept:');
    }

    public function test_player_notes_remain_private_for_character_viewers(): void
    {
        $world = World::factory()->create();
        $owner = User::factory()->gm()->create();
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        [$campaign, $scene] = $this->createCampaignWithScene($world, $owner, false);

        $this->addMembership($campaign, $author, CampaignMembershipRole::PLAYER);
        $this->addMembership($campaign, $viewer, CampaignMembershipRole::PLAYER);

        $character = Character::factory()->create([
            'user_id' => $author->id,
            'world_id' => $world->id,
        ]);
        $this->createCharacterPost($scene, $author, $character);

        $note = PlayerNote::factory()->create([
            'user_id' => $author->id,
            'campaign_id' => $campaign->id,
            'scene_id' => $scene->id,
            'character_id' => $character->id,
            'title' => 'GEHEIME_NOTE_TITLE',
            'body' => 'GEHEIME_NOTE_BODY',
        ]);

        $this->actingAs($viewer)
            ->get(route('characters.show', $character))
            ->assertOk()
            ->assertDontSeeText('GEHEIME_NOTE_TITLE')
            ->assertDontSeeText('GEHEIME_NOTE_BODY');

        $this->actingAs($viewer)
            ->get(route('campaigns.player-notes.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'playerNote' => $note,
            ]))
            ->assertForbidden();
    }

    public function test_public_campaign_visibility_alone_does_not_grant_character_view(): void
    {
        $world = World::factory()->create();
        $owner = User::factory()->gm()->create();
        $author = User::factory()->create();
        $outsider = User::factory()->create();
        [$campaign, $scene] = $this->createCampaignWithScene($world, $owner, true);

        $this->addMembership($campaign, $author, CampaignMembershipRole::PLAYER);

        $character = Character::factory()->create([
            'user_id' => $author->id,
            'world_id' => $world->id,
        ]);
        $this->createCharacterPost($scene, $author, $character, 'Sichtbarer oeffentlicher Szenenpost.');

        $this->actingAs($outsider)
            ->get(route('campaigns.scenes.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]))
            ->assertOk()
            ->assertSeeText('Sichtbarer oeffentlicher Szenenpost.');

        $this->actingAs($outsider)
            ->get(route('characters.show', $character))
            ->assertForbidden();
    }

    /**
     * @return array{0: Campaign, 1: Scene}
     */
    private function createCampaignWithScene(World $world, User $owner, bool $isPublic): array
    {
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'world_id' => $world->id,
            'is_public' => $isPublic,
            'status' => 'active',
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        return [$campaign, $scene];
    }

    private function addMembership(Campaign $campaign, User $user, CampaignMembershipRole $role): void
    {
        CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'assigned_by' => $campaign->owner_id,
        ]);
    }

    private function createCharacterPost(Scene $scene, User $author, Character $character, ?string $content = null): Post
    {
        return Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'character_id' => $character->id,
            'post_type' => 'ic',
            'content_format' => 'plain',
            'content' => $content ?? 'Relevanter Charakterpost in dieser Kampagne.',
            'moderation_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $scene->created_by,
        ]);
    }
}
