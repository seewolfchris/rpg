<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Scene;

use App\Actions\Scene\BuildSceneShowDataAction;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneBookmark;
use App\Models\SceneSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildSceneShowDataActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_scene_show_data_with_jump_urls_and_read_tracking(): void
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

        $playerCharacter = Character::factory()->create([
            'user_id' => $player->id,
            'world_id' => $campaign->world_id,
        ]);

        $readPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
            'moderation_status' => 'approved',
            'is_pinned' => false,
        ]);
        $bookmarkPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
            'moderation_status' => 'approved',
            'is_pinned' => false,
        ]);
        $latestPinnedPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
            'moderation_status' => 'approved',
            'is_pinned' => true,
            'pinned_at' => now(),
            'pinned_by' => $gm->id,
        ]);

        $subscription = SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'is_muted' => false,
            'last_read_post_id' => $readPost->id,
            'last_read_at' => now()->subMinute(),
        ]);

        SceneBookmark::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'post_id' => $bookmarkPost->id,
            'label' => 'Mein Marker',
        ]);

        $result = app(BuildSceneShowDataAction::class)->execute(
            world: $campaign->world,
            campaign: $campaign,
            scene: $scene,
            user: $player,
        );

        $this->assertSame((int) $subscription->id, (int) ($result->subscription?->id ?? 0));
        $this->assertSame((int) $latestPinnedPost->id, $result->latestPostId);
        $this->assertSame(0, $result->unreadPostsCount);
        $this->assertSame(2, $result->newPostsSinceLastRead);
        $this->assertFalse($result->hasUnreadPosts);
        $this->assertFalse($result->canModerateScene);
        $this->assertCount(0, $result->probeCharacters);

        $characterIds = $result->characters
            ->map(static fn (Character $character): int => (int) $character->id)
            ->all();
        $this->assertContains((int) $playerCharacter->id, $characterIds);

        $this->assertStringContainsString('#post-'.(string) $readPost->id, (string) $result->jumpToLastReadUrl);
        $this->assertStringContainsString('#post-'.(string) $latestPinnedPost->id, (string) $result->jumpToLatestPostUrl);
        $this->assertStringContainsString('#post-'.(string) $bookmarkPost->id, (string) $result->bookmarkJumpUrl);
        $this->assertStringContainsString('#post-'.(string) $latestPinnedPost->id, (string) ($result->pinnedPostJumpUrls[(int) $latestPinnedPost->id] ?? ''));
    }

    public function test_it_exposes_probe_characters_for_moderator(): void
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

        $gmCharacter = Character::factory()->create([
            'user_id' => $gm->id,
            'world_id' => $campaign->world_id,
        ]);

        Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
            'moderation_status' => 'approved',
        ]);

        $result = app(BuildSceneShowDataAction::class)->execute(
            world: $campaign->world,
            campaign: $campaign,
            scene: $scene,
            user: $gm,
        );

        $this->assertTrue($result->canModerateScene);

        $probeIds = $result->probeCharacters
            ->map(static fn (Character $character): int => (int) $character->id)
            ->all();
        $this->assertContains((int) $gmCharacter->id, $probeIds);
    }
}
