<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\SceneSubscription;

use App\Actions\SceneSubscription\MarkSceneSubscriptionUnreadAction;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkSceneSubscriptionUnreadActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_not_subscribed_result_when_no_subscription_exists(): void
    {
        $user = User::factory()->create();
        [, $scene] = $this->seedCampaignAndScene();

        $result = app(MarkSceneSubscriptionUnreadAction::class)->execute($user, $scene);

        $this->assertNull($result->subscription);
        $this->assertSame('Szene ist nicht abonniert.', $result->statusMessage());
    }

    public function test_it_marks_existing_subscription_as_unread(): void
    {
        $user = User::factory()->create();
        [$campaign, $scene] = $this->seedCampaignAndScene();
        $latestPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => User::factory()->create()->id,
            'post_type' => 'ic',
            'content_format' => 'plain',
            'content' => 'Beitrag für unread test',
            'moderation_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $campaign->owner_id,
        ]);

        $subscription = SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'is_muted' => false,
            'last_read_post_id' => $latestPost->id,
            'last_read_at' => now(),
        ]);

        $result = app(MarkSceneSubscriptionUnreadAction::class)->execute($user, $scene);

        $this->assertNotNull($result->subscription);
        $this->assertSame((int) $subscription->id, (int) $result->subscription?->id);
        $this->assertNull($result->subscription?->last_read_post_id);
        $this->assertNull($result->subscription?->last_read_at);
        $this->assertSame('Szene als ungelesen markiert.', $result->statusMessage());
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
