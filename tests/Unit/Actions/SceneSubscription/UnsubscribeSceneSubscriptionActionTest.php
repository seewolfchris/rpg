<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\SceneSubscription;

use App\Actions\SceneSubscription\UnsubscribeSceneSubscriptionAction;
use App\Models\Campaign;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnsubscribeSceneSubscriptionActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_existing_subscription(): void
    {
        $user = User::factory()->create();
        [, $scene] = $this->seedCampaignAndScene();

        $subscription = SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'is_muted' => false,
            'last_read_post_id' => null,
            'last_read_at' => null,
        ]);

        $result = app(UnsubscribeSceneSubscriptionAction::class)->execute($user, $scene);

        $this->assertSame(1, $result->deleted);
        $this->assertSame('Szenen-Abo entfernt.', $result->statusMessage());
        $this->assertDatabaseMissing('scene_subscriptions', [
            'id' => $subscription->id,
        ]);
    }

    public function test_it_returns_zero_deleted_when_no_subscription_exists(): void
    {
        $user = User::factory()->create();
        [, $scene] = $this->seedCampaignAndScene();

        $result = app(UnsubscribeSceneSubscriptionAction::class)->execute($user, $scene);

        $this->assertSame(0, $result->deleted);
        $this->assertSame('Szenen-Abo entfernt.', $result->statusMessage());
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
