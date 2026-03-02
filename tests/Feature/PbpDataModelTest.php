<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PbpDataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_scene_and_post_relationships_are_persisted(): void
    {
        $gm = User::factory()->gm()->create();
        $player = User::factory()->create();

        $character = Character::factory()->create([
            'user_id' => $player->id,
        ]);

        $campaign = Campaign::query()->create([
            'owner_id' => $gm->id,
            'title' => 'Schatten ueber Morhaven',
            'slug' => 'schatten-ueber-morhaven',
            'summary' => 'Die Stadt versinkt in Blutmond-Nebeln.',
            'status' => 'active',
            'is_public' => true,
        ]);

        $scene = Scene::query()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'title' => 'Das Tor der Totenwaechter',
            'slug' => 'tor-der-totenwaechter',
            'status' => 'open',
            'position' => 1,
            'allow_ooc' => true,
        ]);

        $post = Post::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'character_id' => $character->id,
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'content' => '**Aldric** zieht seine Klinge und tritt durch das Tor.',
            'moderation_status' => 'pending',
        ]);

        $this->assertTrue($campaign->owner->is($gm));
        $this->assertTrue($scene->campaign->is($campaign));
        $this->assertTrue($post->scene->is($scene));
        $this->assertTrue($post->user->is($player));
        $this->assertTrue($post->character->is($character));
        $this->assertTrue($campaign->scenes->contains($scene));
        $this->assertTrue($scene->posts->contains($post));
    }
}
