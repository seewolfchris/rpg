<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class NotificationSubscriptionCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_index_shows_subscription_management_block(): void
    {
        $user = User::factory()->create();
        [$campaign, $scene] = $this->seedCampaignAndScene();

        SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'is_muted' => false,
        ]);

        $response = $this->actingAs($user)->get(route('notifications.index'));

        $response->assertOk();
        $response->assertSee('Szenen-Abos');
        $response->assertSee($scene->title);
        $response->assertSee('Aktiv benachrichtigt');
    }

    public function test_user_can_mute_and_unfollow_from_notification_center(): void
    {
        $user = User::factory()->create();
        [$campaign, $scene] = $this->seedCampaignAndScene();

        SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'is_muted' => false,
        ]);

        $this->actingAs($user)
            ->patch(route('campaigns.scenes.subscription.mute', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->assertRedirect();

        $this->assertDatabaseHas('scene_subscriptions', [
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'is_muted' => true,
        ]);

        $this->actingAs($user)
            ->delete(route('campaigns.scenes.unsubscribe', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->assertRedirect();

        $this->assertDatabaseMissing('scene_subscriptions', [
            'scene_id' => $scene->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_notification_subscriptions_are_paginated(): void
    {
        $user = User::factory()->create();
        $gm = User::factory()->gm()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        for ($index = 1; $index <= 25; $index += 1) {
            $scene = Scene::factory()->create([
                'campaign_id' => $campaign->id,
                'created_by' => $gm->id,
                'status' => 'open',
                'allow_ooc' => true,
                'title' => 'Abo-Szene '.$index,
            ]);

            SceneSubscription::query()->create([
                'scene_id' => $scene->id,
                'user_id' => $user->id,
                'is_muted' => false,
            ]);
        }

        $response = $this->actingAs($user)->get(route('notifications.index'));

        $response->assertOk();
        $response->assertViewHas('subscriptions', function (mixed $subscriptions): bool {
            return $subscriptions instanceof LengthAwarePaginator
                && $subscriptions->count() === 20
                && $subscriptions->total() === 25;
        });
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
