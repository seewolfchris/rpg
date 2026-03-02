<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostPinningTest extends TestCase
{
    use RefreshDatabase;

    public function test_gm_can_pin_and_unpin_post(): void
    {
        $gm = User::factory()->gm()->create();
        $player = User::factory()->create();

        [$campaign, $scene] = $this->seedCampaignAndScene($gm, $player);

        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
        ]);

        $this->actingAs($gm)
            ->patch(route('posts.pin', $post))
            ->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'is_pinned' => true,
            'pinned_by' => $gm->id,
        ]);

        $this->actingAs($gm)
            ->patch(route('posts.unpin', $post))
            ->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'is_pinned' => false,
            'pinned_by' => null,
        ]);
    }

    public function test_player_cannot_pin_post_without_moderation_rights(): void
    {
        $gm = User::factory()->gm()->create();
        $player = User::factory()->create();

        [$campaign, $scene] = $this->seedCampaignAndScene($gm, $player);

        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
        ]);

        $this->actingAs($player)
            ->patch(route('posts.pin', $post))
            ->assertForbidden();

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'is_pinned' => false,
        ]);
    }

    /**
     * @return array{0: Campaign, 1: Scene}
     */
    private function seedCampaignAndScene(User $gm, User $player): array
    {
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

        SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'is_muted' => false,
            'last_read_post_id' => null,
            'last_read_at' => null,
        ]);

        return [$campaign, $scene];
    }
}
