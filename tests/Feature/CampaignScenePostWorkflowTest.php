<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Character;
use App\Models\DiceRoll;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CampaignScenePostWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_cannot_create_campaign(): void
    {
        $player = User::factory()->create([
            'role' => UserRole::PLAYER->value,
        ]);

        $response = $this->actingAs($player)->post(route('campaigns.store'), [
            'title' => 'Verbotene Flamme',
            'slug' => 'verbotene-flamme',
            'summary' => 'Spieler versucht eine Kampagne anzulegen.',
            'status' => 'draft',
            'is_public' => false,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('campaigns', ['slug' => 'verbotene-flamme']);
    }

    public function test_gm_can_create_campaign_and_scene(): void
    {
        $gm = User::factory()->gm()->create();

        $campaignResponse = $this->actingAs($gm)->post(route('campaigns.store'), [
            'title' => 'Die Fahlmond-Chronik',
            'slug' => 'die-fahlmond-chronik',
            'summary' => 'Ein uralter Schwur droht zu brechen.',
            'status' => 'active',
            'is_public' => true,
        ]);

        $campaign = Campaign::query()->where('slug', 'die-fahlmond-chronik')->firstOrFail();

        $campaignResponse->assertRedirect(route('campaigns.show', $campaign));
        $this->assertSame($gm->id, $campaign->owner_id);

        $sceneResponse = $this->actingAs($gm)->post(route('campaigns.scenes.store', $campaign), [
            'title' => 'Ankunft am Bluttor',
            'slug' => 'ankunft-am-bluttor',
            'summary' => 'Der Nebel oeffnet den ersten Pfad.',
            'status' => 'open',
            'position' => 1,
            'allow_ooc' => true,
        ]);

        $sceneResponse->assertRedirect();
        $this->assertDatabaseHas('scenes', [
            'campaign_id' => $campaign->id,
            'slug' => 'ankunft-am-bluttor',
            'created_by' => $gm->id,
        ]);
    }

    public function test_player_post_is_pending_and_gm_can_approve_it(): void
    {
        $gm = User::factory()->gm()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $character = Character::factory()->create([
            'user_id' => $player->id,
        ]);

        $postResponse = $this->actingAs($player)->post(route('campaigns.scenes.posts.store', [$campaign, $scene]), [
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'character_id' => $character->id,
            'content' => str_repeat('Die Klinge singt im Nebel. ', 2),
        ]);

        $postResponse->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'character_id' => $character->id,
            'moderation_status' => 'pending',
            'approved_by' => null,
        ]);

        $postId = (int) DB::table('posts')
            ->where('scene_id', $scene->id)
            ->where('user_id', $player->id)
            ->latest('id')
            ->value('id');

        $approveResponse = $this->actingAs($gm)->patch(route('posts.moderate', $postId), [
            'moderation_status' => 'approved',
        ]);

        $approveResponse->assertRedirect();
        $this->assertDatabaseHas('posts', [
            'id' => $postId,
            'moderation_status' => 'approved',
            'approved_by' => $gm->id,
        ]);
    }

    public function test_gm_can_create_post_with_integrated_probe_result(): void
    {
        $gm = User::factory()->gm()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $campaign->invitations()->create([
            'user_id' => $player->id,
            'invited_by' => $gm->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'accepted_at' => now(),
            'responded_at' => now(),
            'created_at' => now(),
        ]);

        $gmCharacter = Character::factory()->create(['user_id' => $gm->id]);
        $playerCharacter = Character::factory()->create([
            'user_id' => $player->id,
            'species' => 'mensch',
            'mu' => 44,
            'le_max' => 45,
            'le_current' => 45,
            'ae_max' => 30,
            'ae_current' => 30,
        ]);

        $response = $this->actingAs($gm)->post(route('campaigns.scenes.posts.store', [$campaign, $scene]), [
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'character_id' => $gmCharacter->id,
            'content' => str_repeat('Der Spielleiter setzt die Szene unter Druck. ', 2),
            'probe_enabled' => '1',
            'probe_character_id' => $playerCharacter->id,
            'probe_roll_mode' => DiceRoll::MODE_NORMAL,
            'probe_modifier' => -4,
            'probe_attribute_key' => 'mu',
            'probe_outcome' => DiceRoll::OUTCOME_FAILURE,
            'probe_le_delta' => -10,
            'probe_ae_delta' => -3,
            'probe_explanation' => 'Klettern am zerborstenen Ascheturm bei Sturm',
        ]);

        $post = DB::table('posts')
            ->where('scene_id', $scene->id)
            ->where('user_id', $gm->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($post);
        $response->assertRedirect(route('campaigns.scenes.show', [$campaign, $scene]).'#post-'.$post->id);

        $this->assertDatabaseHas('dice_rolls', [
            'scene_id' => $scene->id,
            'post_id' => $post->id,
            'user_id' => $gm->id,
            'character_id' => $playerCharacter->id,
            'roll_mode' => DiceRoll::MODE_NORMAL,
            'modifier' => -4,
            'label' => 'Klettern am zerborstenen Ascheturm bei Sturm',
            'probe_attribute_key' => 'mu',
            'probe_target_value' => 44,
            'probe_is_success' => 0,
            'applied_le_delta' => -10,
            'applied_ae_delta' => -3,
            'resulting_le_current' => 35,
            'resulting_ae_current' => 27,
        ]);

        $roll = DB::table('dice_rolls')
            ->where('post_id', $post->id)
            ->first();

        $this->assertNotNull($roll);
        $this->assertGreaterThanOrEqual(1, (int) $roll->kept_roll);
        $this->assertLessThanOrEqual(100, (int) $roll->kept_roll);
        $this->assertSame(35, (int) $playerCharacter->fresh()->le_current);
        $this->assertSame(27, (int) $playerCharacter->fresh()->ae_current);

        $sceneResponse = $this->actingAs($player)->get(route('campaigns.scenes.show', [$campaign, $scene]));
        $sceneResponse->assertOk()
            ->assertSeeText('GM-Probe')
            ->assertSeeText('Klettern am zerborstenen Ascheturm bei Sturm')
            ->assertSeeText($playerCharacter->name)
            ->assertSeeText('Probe auf: Mut')
            ->assertSeeText('44 %')
            ->assertSeeText('Ergebnis: Nicht bestanden')
            ->assertSeeText('LE: -10')
            ->assertSeeText('AE: -3');
    }

    public function test_multiple_gm_probes_apply_pool_changes_incrementally(): void
    {
        $gm = User::factory()->gm()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $campaign->invitations()->create([
            'user_id' => $player->id,
            'invited_by' => $gm->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'accepted_at' => now(),
            'responded_at' => now(),
            'created_at' => now(),
        ]);

        $gmCharacter = Character::factory()->create(['user_id' => $gm->id]);
        $targetCharacter = Character::factory()->create([
            'user_id' => $player->id,
            'species' => 'mensch',
            'mu' => 45,
            'le_max' => 45,
            'le_current' => 45,
            'ae_max' => 30,
            'ae_current' => 30,
        ]);

        $this->actingAs($gm)->post(route('campaigns.scenes.posts.store', [$campaign, $scene]), [
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'character_id' => $gmCharacter->id,
            'content' => str_repeat('Erste Probe folgt. ', 2),
            'probe_enabled' => '1',
            'probe_character_id' => $targetCharacter->id,
            'probe_roll_mode' => DiceRoll::MODE_NORMAL,
            'probe_modifier' => 0,
            'probe_attribute_key' => 'mu',
            'probe_outcome' => DiceRoll::OUTCOME_SUCCESS,
            'probe_le_delta' => -10,
            'probe_ae_delta' => -3,
            'probe_explanation' => 'Erster Einschlag',
        ])->assertRedirect();

        $this->actingAs($gm)->post(route('campaigns.scenes.posts.store', [$campaign, $scene]), [
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'character_id' => $gmCharacter->id,
            'content' => str_repeat('Zweite Probe folgt. ', 2),
            'probe_enabled' => '1',
            'probe_character_id' => $targetCharacter->id,
            'probe_roll_mode' => DiceRoll::MODE_NORMAL,
            'probe_modifier' => 0,
            'probe_attribute_key' => 'mu',
            'probe_outcome' => DiceRoll::OUTCOME_FAILURE,
            'probe_le_delta' => -8,
            'probe_ae_delta' => -4,
            'probe_explanation' => 'Zweiter Einschlag',
        ])->assertRedirect();

        $targetCharacter->refresh();

        $this->assertSame(27, (int) $targetCharacter->le_current);
        $this->assertSame(23, (int) $targetCharacter->ae_current);
        $this->assertDatabaseCount('dice_rolls', 2);
    }

    public function test_gm_can_add_inventory_item_to_character_from_post(): void
    {
        $gm = User::factory()->gm()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $campaign->invitations()->create([
            'user_id' => $player->id,
            'invited_by' => $gm->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'accepted_at' => now(),
            'responded_at' => now(),
            'created_at' => now(),
        ]);

        $gmCharacter = Character::factory()->create(['user_id' => $gm->id]);
        $playerCharacter = Character::factory()->create([
            'user_id' => $player->id,
            'inventory' => ['Fackel'],
        ]);

        $response = $this->actingAs($gm)->post(route('campaigns.scenes.posts.store', [$campaign, $scene]), [
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'character_id' => $gmCharacter->id,
            'content' => str_repeat('Ein Fund wird im Lager notiert. ', 2),
            'inventory_award_enabled' => '1',
            'inventory_award_character_id' => $playerCharacter->id,
            'inventory_award_item' => 'Seil 10m lang',
        ]);

        $post = Post::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', $gm->id)
            ->latest('id')
            ->firstOrFail();

        $response->assertRedirect(route('campaigns.scenes.show', [$campaign, $scene]).'#post-'.$post->id);

        $playerCharacter->refresh();
        $this->assertSame(['Fackel', 'Seil 10m lang'], $playerCharacter->inventory);

        $meta = is_array($post->meta) ? $post->meta : [];
        $award = is_array($meta['inventory_award'] ?? null) ? $meta['inventory_award'] : [];

        $this->assertSame($playerCharacter->id, (int) ($award['character_id'] ?? 0));
        $this->assertSame($playerCharacter->name, (string) ($award['character_name'] ?? ''));
        $this->assertSame('Seil 10m lang', (string) ($award['item'] ?? ''));

        $sceneResponse = $this->actingAs($player)->get(route('campaigns.scenes.show', [$campaign, $scene]));
        $sceneResponse->assertOk()
            ->assertSeeText('Inventar aktualisiert')
            ->assertSeeText($playerCharacter->name)
            ->assertSeeText('Seil 10m lang');
    }

    public function test_player_cannot_attach_probe_data_to_post(): void
    {
        $gm = User::factory()->gm()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $playerCharacter = Character::factory()->create(['user_id' => $player->id]);

        $response = $this->actingAs($player)
            ->from(route('campaigns.scenes.show', [$campaign, $scene]))
            ->post(route('campaigns.scenes.posts.store', [$campaign, $scene]), [
                'post_type' => 'ic',
                'content_format' => 'markdown',
                'character_id' => $playerCharacter->id,
                'content' => str_repeat('Ich renne ueber das brennende Pflaster. ', 2),
                'probe_enabled' => '1',
                'probe_character_id' => $playerCharacter->id,
                'probe_roll_mode' => DiceRoll::MODE_NORMAL,
                'probe_modifier' => 2,
                'probe_attribute_key' => 'in',
                'probe_outcome' => DiceRoll::OUTCOME_SUCCESS,
                'probe_explanation' => 'Unerlaubte Probe durch Spieler',
                'inventory_award_enabled' => '1',
                'inventory_award_character_id' => $playerCharacter->id,
                'inventory_award_item' => 'Verbotener Eintrag',
            ]);

        $response->assertRedirect(route('campaigns.scenes.show', [$campaign, $scene]));
        $response->assertSessionHasErrors('probe_enabled');
        $response->assertSessionHasErrors('inventory_award_enabled');
        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('dice_rolls', 0);
    }

    public function test_gm_probe_rejects_target_character_outside_campaign_participants(): void
    {
        $gm = User::factory()->gm()->create();
        $player = User::factory()->create();
        $outsider = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $campaign->invitations()->create([
            'user_id' => $player->id,
            'invited_by' => $gm->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'accepted_at' => now(),
            'responded_at' => now(),
            'created_at' => now(),
        ]);

        $gmCharacter = Character::factory()->create(['user_id' => $gm->id]);
        $outsiderCharacter = Character::factory()->create(['user_id' => $outsider->id]);

        $response = $this->actingAs($gm)
            ->from(route('campaigns.scenes.show', [$campaign, $scene]))
            ->post(route('campaigns.scenes.posts.store', [$campaign, $scene]), [
                'post_type' => 'ic',
                'content_format' => 'markdown',
                'character_id' => $gmCharacter->id,
                'content' => str_repeat('Die Probe soll einen externen Helden treffen. ', 2),
                'probe_enabled' => '1',
                'probe_character_id' => $outsiderCharacter->id,
                'probe_roll_mode' => DiceRoll::MODE_NORMAL,
                'probe_modifier' => 0,
                'probe_attribute_key' => 'ge',
                'probe_outcome' => DiceRoll::OUTCOME_FAILURE,
                'probe_le_delta' => -5,
                'probe_ae_delta' => 0,
                'probe_explanation' => 'Unzulaessiges Ziel ausserhalb der Kampagne',
            ]);

        $response->assertRedirect(route('campaigns.scenes.show', [$campaign, $scene]));
        $response->assertSessionHasErrors('probe_character_id');
        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('dice_rolls', 0);
    }

    public function test_scene_show_separates_ic_and_ooc_sections(): void
    {
        $gm = User::factory()->gm()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $character = Character::factory()->create(['user_id' => $player->id]);

        $this->actingAs($player)->post(route('campaigns.scenes.posts.store', [$campaign, $scene]), [
            'post_type' => 'ic',
            'content_format' => 'plain',
            'character_id' => $character->id,
            'content' => 'IC-Text am roten Tor mit Blutmondschein.',
        ]);

        $this->actingAs($player)->post(route('campaigns.scenes.posts.store', [$campaign, $scene]), [
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'OOC-Abstimmung fuer die naechste Runde.',
        ]);

        $response = $this->actingAs($player)->get(route('campaigns.scenes.show', [$campaign, $scene]));

        $response->assertOk()
            ->assertSeeText('Abenteuerfluss (IC)')
            ->assertSeeText('OOC-Kanal')
            ->assertSeeText('IC-Text am roten Tor mit Blutmondschein.')
            ->assertSeeText('OOC-Abstimmung fuer die naechste Runde.');
    }

    public function test_gm_can_use_scene_inventory_quick_action_to_add_and_remove_items(): void
    {
        $gm = User::factory()->gm()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $campaign->invitations()->create([
            'user_id' => $player->id,
            'invited_by' => $gm->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'accepted_at' => now(),
            'responded_at' => now(),
            'created_at' => now(),
        ]);

        $targetCharacter = Character::factory()->create([
            'user_id' => $player->id,
            'inventory' => ['Fackel', 'Seil 10m lang'],
        ]);

        $addResponse = $this->actingAs($gm)->post(route('campaigns.scenes.inventory-quick-action', [$campaign, $scene]), [
            'inventory_action_character_id' => $targetCharacter->id,
            'inventory_action_type' => 'add',
            'inventory_action_item' => 'Heiltrank',
            'inventory_action_note' => 'Gefunden in der Nebelkammer',
        ]);

        $addResponse->assertRedirect(route('campaigns.scenes.show', [$campaign, $scene]).'#inventory-quick-action');
        $this->assertSame(['Fackel', 'Seil 10m lang', 'Heiltrank'], $targetCharacter->fresh()->inventory);

        $removeResponse = $this->actingAs($gm)->post(route('campaigns.scenes.inventory-quick-action', [$campaign, $scene]), [
            'inventory_action_character_id' => $targetCharacter->id,
            'inventory_action_type' => 'remove',
            'inventory_action_item' => 'seil 10m lang',
        ]);

        $removeResponse->assertRedirect(route('campaigns.scenes.show', [$campaign, $scene]).'#inventory-quick-action');
        $this->assertSame(['Fackel', 'Heiltrank'], $targetCharacter->fresh()->inventory);
    }

    public function test_inventory_quick_action_rejects_unknown_item_removal(): void
    {
        $gm = User::factory()->gm()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $campaign->invitations()->create([
            'user_id' => $player->id,
            'invited_by' => $gm->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'accepted_at' => now(),
            'responded_at' => now(),
            'created_at' => now(),
        ]);

        $targetCharacter = Character::factory()->create([
            'user_id' => $player->id,
            'inventory' => ['Fackel'],
        ]);

        $response = $this->actingAs($gm)
            ->from(route('campaigns.scenes.show', [$campaign, $scene]))
            ->post(route('campaigns.scenes.inventory-quick-action', [$campaign, $scene]), [
                'inventory_action_character_id' => $targetCharacter->id,
                'inventory_action_type' => 'remove',
                'inventory_action_item' => 'Unbekannter Gegenstand',
            ]);

        $response->assertRedirect(route('campaigns.scenes.show', [$campaign, $scene]).'#inventory-quick-action');
        $response->assertSessionHasErrors('inventory_action_item');
        $this->assertSame(['Fackel'], $targetCharacter->fresh()->inventory);
    }

    public function test_player_cannot_use_scene_inventory_quick_action(): void
    {
        $gm = User::factory()->gm()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $campaign->invitations()->create([
            'user_id' => $player->id,
            'invited_by' => $gm->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'accepted_at' => now(),
            'responded_at' => now(),
            'created_at' => now(),
        ]);

        $targetCharacter = Character::factory()->create([
            'user_id' => $player->id,
            'inventory' => ['Fackel'],
        ]);

        $response = $this->actingAs($player)
            ->from(route('campaigns.scenes.show', [$campaign, $scene]))
            ->post(route('campaigns.scenes.inventory-quick-action', [$campaign, $scene]), [
                'inventory_action_character_id' => $targetCharacter->id,
                'inventory_action_type' => 'add',
                'inventory_action_item' => 'Heiltrank',
            ]);

        $response->assertRedirect(route('campaigns.scenes.show', [$campaign, $scene]));
        $response->assertSessionHasErrors('inventory_action_character_id');
        $this->assertSame(['Fackel'], $targetCharacter->fresh()->inventory);
    }
}
