<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScenePostReadabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);
    }

    public function test_ic_character_post_shows_prominent_character_header_with_link_and_avatar_fallback_for_allowed_viewer(): void
    {
        [$campaign, $scene] = $this->seedCampaignScene();
        $player = User::factory()->create(['name' => 'Spieler Armin']);
        $character = Character::factory()->create([
            'user_id' => $player->id,
            'world_id' => $campaign->world_id,
            'name' => 'Sir Avar',
            'avatar_path' => null,
        ]);

        Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'character_id' => $character->id,
            'post_type' => 'ic',
            'content_format' => 'plain',
            'content' => 'Ich halte die Bruecke bis zum letzten Atemzug.',
            'moderation_status' => 'approved',
        ]);

        $response = $this->actingAs($player)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $characterUrl = route('characters.show', ['character' => $character]);

        $response->assertOk()
            ->assertSeeText('Sir Avar')
            ->assertSeeText('gespielt von')
            ->assertSeeText('Spieler Armin')
            ->assertSee('href="'.$characterUrl.'"', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertSee('character-placeholder.svg', false);
    }

    public function test_ic_character_post_links_character_name_for_campaign_participant_viewer(): void
    {
        [$campaign, $scene, $gm] = $this->seedCampaignSceneWithOwner();
        $author = User::factory()->create(['name' => 'Heldenautor']);
        $viewer = User::factory()->create(['name' => 'Mitspieler']);

        CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $author->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => $gm->id,
        ]);
        CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $viewer->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => $gm->id,
        ]);

        $character = Character::factory()->create([
            'user_id' => $author->id,
            'world_id' => $campaign->world_id,
            'name' => 'Talan vom Nordgrat',
            'avatar_path' => null,
        ]);

        Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'character_id' => $character->id,
            'post_type' => 'ic',
            'content_format' => 'plain',
            'content' => 'Ich ziehe die Klinge und halte die Linie.',
            'moderation_status' => 'approved',
        ]);

        $characterUrl = route('characters.show', ['character' => $character]);
        $response = $this->actingAs($viewer)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk()
            ->assertSeeText('Talan vom Nordgrat')
            ->assertSee('href="'.$characterUrl.'"', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false);
    }

    public function test_ic_character_name_stays_plain_text_for_viewer_without_character_permission(): void
    {
        [$campaign, $scene] = $this->seedCampaignScene();
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $character = Character::factory()->create([
            'user_id' => $owner->id,
            'world_id' => $campaign->world_id,
            'name' => 'Mira Nebelpfad',
        ]);

        Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $owner->id,
            'character_id' => $character->id,
            'post_type' => 'ic',
            'content_format' => 'plain',
            'content' => 'Die Spuren verlieren sich im Schieferstaub.',
            'moderation_status' => 'approved',
        ]);

        $response = $this->actingAs($viewer)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk()
            ->assertSeeText('Mira Nebelpfad')
            ->assertDontSee('href="'.route('characters.show', ['character' => $character]).'"', false);
    }

    public function test_gm_narration_shows_spielleitung_and_has_no_character_link(): void
    {
        [$campaign, $scene, $gm] = $this->seedCampaignSceneWithOwner();
        $character = Character::factory()->create([
            'user_id' => $gm->id,
            'world_id' => $campaign->world_id,
        ]);

        Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
            'character_id' => null,
            'post_type' => 'ic',
            'content_format' => 'plain',
            'content' => 'Der Nebel zieht als schweigende Erzählerstimme vorbei.',
            'meta' => [
                'author_role' => 'gm',
            ],
            'moderation_status' => 'approved',
        ]);

        $response = $this->actingAs($gm)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk()
            ->assertSeeText('Spielleitung')
            ->assertSeeText('Erzählerstimme')
            ->assertDontSee('href="'.route('characters.show', ['character' => $character]).'"', false);
    }

    public function test_ooc_post_is_clearly_marked_and_not_rendered_as_gm_narration(): void
    {
        [$campaign, $scene] = $this->seedCampaignScene();
        $player = User::factory()->create();

        Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'character_id' => null,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'OOC: Ich bin 10 Minuten spaeter da.',
            'moderation_status' => 'approved',
            'meta' => [],
        ]);

        $response = $this->actingAs($player)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk()
            ->assertSeeText('OOC')
            ->assertDontSeeText('Erzählerstimme');
    }

    public function test_deleted_post_keeps_tombstone_without_character_link_or_avatar_markup(): void
    {
        [$campaign, $scene] = $this->seedCampaignScene();
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $character = Character::factory()->create([
            'user_id' => $owner->id,
            'world_id' => $campaign->world_id,
            'name' => 'Kara vom Moor',
        ]);

        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $owner->id,
            'character_id' => $character->id,
            'post_type' => 'ic',
            'content_format' => 'plain',
            'content' => 'Dieser Text darf nur im Tombstone verschwinden.',
            'moderation_status' => 'approved',
        ]);
        $post->delete();

        $response = $this->actingAs($viewer)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk()
            ->assertSeeText('Beitrag gelöscht.')
            ->assertDontSeeText('Kara vom Moor')
            ->assertDontSee('href="'.route('characters.show', ['character' => $character]).'"', false)
            ->assertDontSee('thread-post-author-avatar', false);
    }

    public function test_gm_narration_with_immersive_image_stays_visible_when_not_deleted(): void
    {
        [$campaign, $scene, $gm] = $this->seedCampaignSceneWithOwner();

        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
            'character_id' => null,
            'post_type' => 'ic',
            'content_format' => 'plain',
            'content' => 'Die Spielleitung setzt den naechsten Blickpunkt.',
            'meta' => [
                'author_role' => 'gm',
            ],
            'moderation_status' => 'approved',
        ]);

        $post->addMedia(UploadedFile::fake()->image('mist.jpg', 1400, 900))
            ->toMediaCollection(Post::IMMERSIVE_IMAGES_COLLECTION);
        $mediaUrl = (string) optional($post->getMedia(Post::IMMERSIVE_IMAGES_COLLECTION)->first())->getUrl();

        $response = $this->actingAs($gm)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk()
            ->assertSeeText('Spielleitung')
            ->assertSee($mediaUrl, false);
    }

    public function test_scene_show_contains_only_one_new_post_form(): void
    {
        [$campaign, $scene, $gm] = $this->seedCampaignSceneWithOwner();

        $response = $this->actingAs($gm)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk();

        $html = (string) $response->getContent();
        $this->assertSame(1, substr_count($html, 'id="new-post-form"'));
        $this->assertSame(1, substr_count($html, 'data-offline-post-form'));
    }

    /**
     * @return array{0: Campaign, 1: Scene}
     */
    private function seedCampaignScene(): array
    {
        [$campaign, $scene] = $this->seedCampaignSceneWithOwner();

        return [$campaign, $scene];
    }

    /**
     * @return array{0: Campaign, 1: Scene, 2: User}
     */
    private function seedCampaignSceneWithOwner(): array
    {
        $gm = User::factory()->gm()->create();
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

        return [$campaign, $scene, $gm];
    }
}
