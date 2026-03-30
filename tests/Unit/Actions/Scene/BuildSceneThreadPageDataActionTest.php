<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Scene;

use App\Actions\Scene\BuildSceneThreadPageDataAction;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildSceneThreadPageDataActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_thread_page_data_with_unread_count_and_co_gm_moderation_rights(): void
    {
        $owner = User::factory()->gm()->create();
        $coGm = User::factory()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
        ]);

        CampaignInvitation::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $coGm->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_CO_GM,
            'accepted_at' => now(),
            'responded_at' => now(),
        ]);

        $readPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $owner->id,
            'moderation_status' => 'approved',
        ]);
        $latestPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $owner->id,
            'moderation_status' => 'approved',
        ]);

        $subscription = SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $coGm->id,
            'is_muted' => false,
            'last_read_post_id' => $readPost->id,
            'last_read_at' => now(),
        ]);

        $result = app(BuildSceneThreadPageDataAction::class)->execute($scene, $campaign, $coGm);

        $this->assertSame(2, $result->posts->total());
        $this->assertSame((int) $latestPost->id, $result->latestPostId);
        $this->assertSame(1, $result->unreadPostsCount);
        $this->assertTrue($result->canModerateScene);
        $this->assertSame($subscription->id, $result->subscription?->id);
    }

    public function test_it_returns_zero_unread_without_subscription_and_without_moderation_rights(): void
    {
        $owner = User::factory()->gm()->create();
        $player = User::factory()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
        ]);
        $latestPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $owner->id,
            'moderation_status' => 'approved',
        ]);

        $result = app(BuildSceneThreadPageDataAction::class)->execute($scene, $campaign, $player);

        $this->assertSame((int) $latestPost->id, $result->latestPostId);
        $this->assertSame(0, $result->unreadPostsCount);
        $this->assertNull($result->subscription);
        $this->assertFalse($result->canModerateScene);
    }
}
