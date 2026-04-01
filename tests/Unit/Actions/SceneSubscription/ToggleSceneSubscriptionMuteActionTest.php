<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\SceneSubscription;

use App\Actions\SceneSubscription\ToggleSceneSubscriptionMuteAction;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToggleSceneSubscriptionMuteActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_subscription_with_latest_post_cursor_and_mutes_it(): void
    {
        $user = User::factory()->create();
        [$campaign, $scene] = $this->seedCampaignAndScene();

        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => User::factory()->create()->id,
            'post_type' => 'ic',
            'content_format' => 'plain',
            'content' => 'Neuester Beitrag',
            'moderation_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $campaign->owner_id,
        ]);

        $result = app(ToggleSceneSubscriptionMuteAction::class)->execute($user, $scene);

        $this->assertTrue($result->subscription->is_muted);
        $this->assertSame((int) $post->id, (int) $result->subscription->last_read_post_id);
        $this->assertNotNull($result->subscription->last_read_at);
        $this->assertSame('Szenen-Benachrichtigungen stummgeschaltet.', $result->statusMessage());
    }

    public function test_it_unmutes_existing_muted_subscription(): void
    {
        $user = User::factory()->create();
        [, $scene] = $this->seedCampaignAndScene();

        SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'is_muted' => true,
            'last_read_post_id' => null,
            'last_read_at' => now(),
        ]);

        $result = app(ToggleSceneSubscriptionMuteAction::class)->execute($user, $scene);

        $this->assertFalse($result->subscription->is_muted);
        $this->assertSame('Szenen-Benachrichtigungen aktiviert.', $result->statusMessage());
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
