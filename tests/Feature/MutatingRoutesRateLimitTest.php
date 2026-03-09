<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MutatingRoutesRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_write_routes_are_rate_limited(): void
    {
        [$gm, $player, $post] = $this->seedPostContext();

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->actingAs($player)->patch(route('posts.update', ['world' => $post->scene->campaign->world, 'post' => $post]), [
                'post_type' => 'ic',
                'content_format' => 'markdown',
                'character_id' => $post->character_id,
                'content' => 'Aktualisierung #'.$attempt.' in den Aschelanden.',
            ])->assertStatus(302);
        }

        $this->actingAs($player)->patch(route('posts.update', ['world' => $post->scene->campaign->world, 'post' => $post]), [
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'character_id' => $post->character_id,
            'content' => 'Diese Anfrage muss wegen writes-Limit blockiert werden.',
        ])->assertStatus(429);
    }

    public function test_moderation_routes_are_rate_limited(): void
    {
        [$gm, $player, $post] = $this->seedPostContext();

        for ($attempt = 0; $attempt < 15; $attempt++) {
            $this->actingAs($gm)->patch(route('posts.moderate', ['world' => $post->scene->campaign->world, 'post' => $post]), [
                'moderation_status' => $attempt % 2 === 0 ? 'approved' : 'rejected',
            ])->assertStatus(302);
        }

        $this->actingAs($gm)->patch(route('posts.moderate', ['world' => $post->scene->campaign->world, 'post' => $post]), [
            'moderation_status' => 'approved',
        ])->assertStatus(429);
    }

    public function test_notification_routes_are_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->actingAs($user)->post(route('notifications.read-all'))
                ->assertStatus(302);
        }

        $this->actingAs($user)->post(route('notifications.read-all'))
            ->assertStatus(429);
    }

    /**
     * @return array{0: User, 1: User, 2: Post}
     */
    private function seedPostContext(): array
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

        $post = Post::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'character_id' => $character->id,
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'content' => 'Der Wind traegt Funken durch die Halle.',
            'moderation_status' => 'pending',
        ]);

        return [$gm, $player, $post];
    }
}
