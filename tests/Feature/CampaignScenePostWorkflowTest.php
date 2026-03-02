<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Character;
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
}
