<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Character;
use App\Models\PointEvent;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamificationPointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_post_grants_points_to_author(): void
    {
        [$gm, $player, $campaign, $scene, $character] = $this->seedSceneContext();

        $this->actingAs($player)->post(route('campaigns.scenes.posts.store', [$campaign, $scene]), [
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'character_id' => $character->id,
            'content' => 'Ein erster Schritt in die verfluchte Halle.',
        ])->assertRedirect();

        $post = Post::query()->latest('id')->firstOrFail();

        $player->refresh();
        $this->assertSame(0, $player->points);

        $this->actingAs($gm)->patch(route('posts.moderate', $post), [
            'moderation_status' => 'approved',
        ])->assertRedirect();

        $player->refresh();
        $this->assertSame(10, $player->points);

        $this->assertDatabaseHas('point_events', [
            'user_id' => $player->id,
            'source_type' => 'post',
            'source_id' => $post->id,
            'event_key' => 'approved',
            'points' => 10,
        ]);
    }

    public function test_repeated_approval_does_not_duplicate_points(): void
    {
        [$gm, $player, $campaign, $scene, $character] = $this->seedSceneContext();

        $post = Post::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'character_id' => $character->id,
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'content' => 'Ein Testbeitrag im Nebel.',
            'moderation_status' => 'pending',
        ]);

        $this->actingAs($gm)->patch(route('posts.moderate', $post), [
            'moderation_status' => 'approved',
        ])->assertRedirect();

        $this->actingAs($gm)->patch(route('posts.moderate', $post), [
            'moderation_status' => 'approved',
        ])->assertRedirect();

        $player->refresh();
        $this->assertSame(10, $player->points);

        $count = PointEvent::query()
            ->where('user_id', $player->id)
            ->where('source_type', 'post')
            ->where('source_id', $post->id)
            ->where('event_key', 'approved')
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_rejecting_approved_post_revokes_points(): void
    {
        [$gm, $player, $campaign, $scene, $character] = $this->seedSceneContext();

        $post = Post::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'character_id' => $character->id,
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'content' => 'Ein bereits freigegebener Beitrag.',
            'moderation_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $gm->id,
        ]);

        // Punktevergabe via Moderations-Endpoint ausloesen.
        $this->actingAs($gm)->patch(route('posts.moderate', $post), [
            'moderation_status' => 'approved',
        ])->assertRedirect();

        $this->actingAs($gm)->patch(route('posts.moderate', $post), [
            'moderation_status' => 'rejected',
        ])->assertRedirect();

        $player->refresh();
        $this->assertSame(0, $player->points);

        $this->assertDatabaseMissing('point_events', [
            'user_id' => $player->id,
            'source_type' => 'post',
            'source_id' => $post->id,
            'event_key' => 'approved',
        ]);
    }

    public function test_deleting_approved_post_revokes_points(): void
    {
        [$gm, $player, $campaign, $scene] = $this->seedSceneContext();

        $this->actingAs($gm)->post(route('campaigns.scenes.posts.store', [$campaign, $scene]), [
            'post_type' => 'ooc',
            'content_format' => 'markdown',
            'content' => 'GM postet im Namen der Ordnung.',
        ])->assertRedirect();

        $post = Post::query()->latest('id')->firstOrFail();
        $gm->refresh();
        $this->assertSame(10, $gm->points);

        $this->actingAs($gm)->delete(route('posts.destroy', $post))->assertRedirect();

        $gm->refresh();
        $this->assertSame(0, $gm->points);
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
