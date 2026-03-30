<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Post;

use App\Actions\Post\SetPostPinStateAction;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetPostPinStateActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sets_pin_state_with_actor(): void
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
        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'is_pinned' => false,
            'pinned_at' => null,
            'pinned_by' => null,
        ]);

        $action = new SetPostPinStateAction;
        $action->execute($post, true, (int) $gm->id);

        $post->refresh();

        $this->assertTrue((bool) $post->is_pinned);
        $this->assertNotNull($post->pinned_at);
        $this->assertSame((int) $gm->id, (int) ($post->pinned_by ?? 0));
    }

    public function test_it_clears_pin_state(): void
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
        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'is_pinned' => true,
            'pinned_at' => now(),
            'pinned_by' => $gm->id,
        ]);

        $action = new SetPostPinStateAction;
        $action->execute($post, false);

        $post->refresh();

        $this->assertFalse((bool) $post->is_pinned);
        $this->assertNull($post->pinned_at);
        $this->assertNull($post->pinned_by);
    }
}
