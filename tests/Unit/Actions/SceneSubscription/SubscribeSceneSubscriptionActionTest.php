<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\SceneSubscription;

use App\Actions\SceneSubscription\SubscribeSceneSubscriptionAction;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscribeSceneSubscriptionActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_or_updates_subscription_with_latest_post_cursor(): void
    {
        $user = User::factory()->create();
        [$campaign, $scene] = $this->seedCampaignAndScene();

        Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => User::factory()->create()->id,
            'post_type' => 'ic',
            'content_format' => 'plain',
            'content' => 'Alter Beitrag',
            'moderation_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $campaign->owner_id,
        ]);
        $latestPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => User::factory()->create()->id,
            'post_type' => 'ic',
            'content_format' => 'plain',
            'content' => 'Neuester Beitrag',
            'moderation_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $campaign->owner_id,
        ]);

        $result = app(SubscribeSceneSubscriptionAction::class)->execute($user, $scene);

        $this->assertSame((int) $scene->id, (int) $result->subscription->scene_id);
        $this->assertSame((int) $user->id, (int) $result->subscription->user_id);
        $this->assertFalse($result->subscription->is_muted);
        $this->assertSame((int) $latestPost->id, (int) $result->subscription->last_read_post_id);
        $this->assertNotNull($result->subscription->last_read_at);
        $this->assertSame('Szene abonniert.', $result->statusMessage());
    }

    /**
     * @return array{0: Campaign, 1: Scene}
     */
    private function seedCampaignAndScene(): array
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

        return [$campaign, $scene];
    }
}
