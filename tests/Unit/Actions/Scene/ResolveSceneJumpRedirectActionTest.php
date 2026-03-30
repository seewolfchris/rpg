<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Scene;

use App\Actions\Scene\ResolveSceneJumpRedirectAction;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveSceneJumpRedirectActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_redirect_url_for_latest_jump(): void
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
        ]);

        Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
            'moderation_status' => 'approved',
        ]);
        $latestPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
            'moderation_status' => 'approved',
        ]);

        $url = app(ResolveSceneJumpRedirectAction::class)->execute(
            world: $campaign->world,
            campaign: $campaign,
            scene: $scene,
            user: $gm,
            jump: 'latest',
        );

        $this->assertNotNull($url);
        $this->assertStringContainsString('#post-'.(string) $latestPost->id, (string) $url);
    }

    public function test_it_returns_null_for_first_unread_without_subscription(): void
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
        ]);

        Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
            'moderation_status' => 'approved',
        ]);

        SceneSubscription::query()->where('scene_id', $scene->id)->where('user_id', $player->id)->delete();

        $url = app(ResolveSceneJumpRedirectAction::class)->execute(
            world: $campaign->world,
            campaign: $campaign,
            scene: $scene,
            user: $player,
            jump: 'first_unread',
        );

        $this->assertNull($url);
    }
}
