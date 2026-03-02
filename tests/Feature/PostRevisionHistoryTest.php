<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostRevisionHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_post_content_creates_revision_snapshot(): void
    {
        [$gm, $player, $campaign, $scene, $character] = $this->seedSceneContext();

        $post = Post::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'character_id' => $character->id,
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'content' => 'Erster Entwurf in den Schatten.',
            'moderation_status' => 'pending',
        ]);

        $response = $this->actingAs($player)->patch(route('posts.update', $post), [
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'character_id' => $character->id,
            'content' => 'Ueberarbeiteter Beitrag mit mehr Tiefe.',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'content' => 'Ueberarbeiteter Beitrag mit mehr Tiefe.',
            'is_edited' => true,
        ]);

        $this->assertDatabaseHas('post_revisions', [
            'post_id' => $post->id,
            'version' => 1,
            'editor_id' => $player->id,
            'content' => 'Erster Entwurf in den Schatten.',
            'post_type' => 'ic',
        ]);
    }

    public function test_revision_versions_increment_on_multiple_edits(): void
    {
        [$gm, $player, $campaign, $scene, $character] = $this->seedSceneContext();

        $post = Post::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'character_id' => $character->id,
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'content' => 'Version A',
            'moderation_status' => 'pending',
        ]);

        $this->actingAs($player)->patch(route('posts.update', $post), [
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'character_id' => $character->id,
            'content' => 'Version B',
        ])->assertRedirect();

        $this->actingAs($player)->patch(route('posts.update', $post), [
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'character_id' => $character->id,
            'content' => 'Version C',
        ])->assertRedirect();

        $this->assertDatabaseHas('post_revisions', [
            'post_id' => $post->id,
            'version' => 1,
            'content' => 'Version A',
        ]);

        $this->assertDatabaseHas('post_revisions', [
            'post_id' => $post->id,
            'version' => 2,
            'content' => 'Version B',
        ]);
    }

    public function test_moderation_only_update_does_not_create_revision(): void
    {
        [$gm, $player, $campaign, $scene, $character] = $this->seedSceneContext();

        $post = Post::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'character_id' => $character->id,
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'content' => 'Unveraenderter Inhalt',
            'moderation_status' => 'pending',
        ]);

        $response = $this->actingAs($gm)->patch(route('posts.update', $post), [
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'character_id' => $character->id,
            'content' => 'Unveraenderter Inhalt',
            'moderation_status' => 'approved',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'moderation_status' => 'approved',
            'is_edited' => false,
        ]);

        $count = (int) DB::table('post_revisions')->where('post_id', $post->id)->count();
        $this->assertSame(0, $count);
    }

    /**
     * @return array{0: User, 1: User, 2: Campaign, 3: Scene, 4: Character}
     */
    private function seedSceneContext(): array
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

        return [$gm, $player, $campaign, $scene, $character];
    }
}
