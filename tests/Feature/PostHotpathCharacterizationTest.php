<?php

namespace Tests\Feature;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostHotpathCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_store_in_moderated_campaign_stays_pending_without_approval_fields(): void
    {
        [$world, $campaign, $scene, $owner, $manager, $player, $character] = $this->seedModeratedPrivateSceneContext();

        $response = $this->actingAs($player)->post(route('campaigns.scenes.posts.store', [
            'world' => $world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]), [
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'character_id' => $character->id,
            'content' => 'Der Trupp wartet auf Freigabe im dichten Nebel.',
        ]);

        $post = Post::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', $player->id)
            ->latest('id')
            ->firstOrFail();

        $response->assertRedirect(route('campaigns.scenes.show', [
            'world' => $world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]).'#post-'.$post->id);

        $this->assertSame('pending', $post->moderation_status);
        $this->assertNull($post->approved_by);
        $this->assertNull($post->approved_at);
    }

    public function test_manager_store_as_gm_narration_is_approved_and_sets_author_role(): void
    {
        [$world, $campaign, $scene, $owner, $manager] = $this->seedModeratedPrivateSceneContext();

        $response = $this->actingAs($manager)->post(route('campaigns.scenes.posts.store', [
            'world' => $world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]), [
            'post_type' => 'ic',
            'post_mode' => 'gm',
            'content_format' => 'plain',
            'content' => 'Die Spielleitung laesst den Sturm ueber die Mauer rollen.',
        ]);

        $post = Post::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', $manager->id)
            ->latest('id')
            ->firstOrFail();

        $response->assertRedirect(route('campaigns.scenes.show', [
            'world' => $world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]).'#post-'.$post->id);

        $this->assertSame('approved', $post->moderation_status);
        $this->assertSame((int) $manager->id, (int) $post->approved_by);
        $this->assertNotNull($post->approved_at);
        $this->assertNull($post->character_id);
        $this->assertSame('gm', data_get($post->meta, 'author_role'));
    }

    public function test_author_update_cannot_force_approved_status_when_moderation_is_required(): void
    {
        [$world, $campaign, $scene, $owner, $manager, $player, $character] = $this->seedModeratedPrivateSceneContext();

        $post = Post::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'character_id' => $character->id,
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'content' => 'Ausgangsversion vor der Ueberarbeitung.',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $response = $this->actingAs($player)->patch(route('posts.update', [
            'world' => $world,
            'post' => $post,
        ]), [
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'character_id' => $character->id,
            'content' => 'Neue Version, aber weiterhin moderationspflichtig.',
            'moderation_status' => 'approved',
        ]);

        $response->assertRedirect(route('campaigns.scenes.show', [
            'world' => $world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]).'#post-'.$post->id);

        $post->refresh();

        $this->assertSame('pending', $post->moderation_status);
        $this->assertNull($post->approved_by);
        $this->assertNull($post->approved_at);
        $this->assertDatabaseHas('post_revisions', [
            'post_id' => $post->id,
            'version' => 1,
            'editor_id' => $player->id,
            'content' => 'Ausgangsversion vor der Ueberarbeitung.',
        ]);
    }

    public function test_moderator_status_only_update_can_reject_post_and_does_not_create_revision(): void
    {
        [$world, $campaign, $scene, $owner, $manager, $player, $character] = $this->seedModeratedPrivateSceneContext();

        $post = Post::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'character_id' => $character->id,
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'content' => 'Bereits freigegebener Beitrag.',
            'moderation_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $owner->id,
        ]);

        $response = $this->actingAs($manager)->patch(route('posts.update', [
            'world' => $world,
            'post' => $post,
        ]), [
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'character_id' => $character->id,
            'content' => 'Bereits freigegebener Beitrag.',
            'moderation_status' => 'rejected',
            'moderation_note' => 'Zurueck in die Ueberarbeitung.',
        ]);

        $response->assertRedirect(route('campaigns.scenes.show', [
            'world' => $world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]).'#post-'.$post->id);

        $post->refresh();

        $this->assertSame('rejected', $post->moderation_status);
        $this->assertNull($post->approved_by);
        $this->assertNull($post->approved_at);
        $this->assertSame(0, (int) DB::table('post_revisions')->where('post_id', $post->id)->count());
    }

    public function test_moderation_non_hx_redirects_back_and_keeps_contract_status_message(): void
    {
        [$world, $campaign, $scene, $owner, $manager, $player, $character] = $this->seedModeratedPrivateSceneContext();

        $post = Post::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'character_id' => $character->id,
            'post_type' => 'ic',
            'content_format' => 'plain',
            'content' => 'Wartet auf Moderation.',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $sceneUrl = route('campaigns.scenes.show', [
            'world' => $world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]);

        $response = $this->actingAs($manager)
            ->from($sceneUrl)
            ->patch(route('posts.moderate', [
                'world' => $world,
                'post' => $post,
            ]), [
                'moderation_status' => 'approved',
                'moderation_note' => 'Freigegeben.',
            ]);

        $response->assertRedirect($sceneUrl);
        $response->assertSessionHas('status', 'Moderationsstatus aktualisiert.');

        $post->refresh();
        $this->assertSame('approved', $post->moderation_status);
        $this->assertSame((int) $manager->id, (int) $post->approved_by);
        $this->assertNotNull($post->approved_at);
    }

    public function test_moderation_with_foreign_world_slug_returns_404_without_state_change(): void
    {
        [$world, $campaign, $scene, $owner, $manager, $player, $character] = $this->seedModeratedPrivateSceneContext();

        $post = Post::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'character_id' => $character->id,
            'post_type' => 'ic',
            'content_format' => 'plain',
            'content' => 'Soll bei Weltgrenzen unveraendert bleiben.',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $foreignWorld = World::factory()->create([
            'slug' => 'post-hotpath-foreign-world',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->patch(route('posts.moderate', [
                'world' => $foreignWorld,
                'post' => $post,
            ]), [
                'moderation_status' => 'approved',
                'moderation_note' => 'Darf nicht greifen.',
            ])
            ->assertNotFound();

        $post->refresh();
        $this->assertSame('pending', $post->moderation_status);
        $this->assertNull($post->approved_by);
        $this->assertNull($post->approved_at);
    }

    /**
     * @return array{0: World, 1: Campaign, 2: Scene, 3: User, 4: User, 5: User, 6: Character}
     */
    private function seedModeratedPrivateSceneContext(): array
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $player = User::factory()->create();

        $world = World::factory()->create([
            'slug' => 'post-hotpath-world',
            'is_active' => true,
        ]);

        $campaign = Campaign::factory()->create([
            'world_id' => $world->id,
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => false,
            'requires_post_moderation' => true,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $manager->id,
            'role' => CampaignMembershipRole::GM->value,
            'assigned_by' => $owner->id,
        ]);

        CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $player->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => $owner->id,
        ]);

        $character = Character::factory()->create([
            'user_id' => $player->id,
            'world_id' => $world->id,
        ]);

        return [$world, $campaign, $scene, $owner, $manager, $player, $character];
    }
}
