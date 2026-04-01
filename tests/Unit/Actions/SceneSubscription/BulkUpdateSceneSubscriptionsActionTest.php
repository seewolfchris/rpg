<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\SceneSubscription;

use App\Actions\SceneSubscription\BulkUpdateSceneSubscriptionsAction;
use App\Models\Campaign;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class BulkUpdateSceneSubscriptionsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_mutes_only_filtered_active_subscriptions(): void
    {
        $user = User::factory()->create();
        $gm = User::factory()->gm()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'title' => 'Aschepfad',
            'status' => 'active',
            'is_public' => true,
        ]);

        $matchingScene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'title' => 'Doran-Halle',
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $otherScene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'title' => 'Nordtor',
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $alreadyMutedScene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'title' => 'Doran-Archiv',
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        SceneSubscription::query()->create([
            'scene_id' => $matchingScene->id,
            'user_id' => $user->id,
            'is_muted' => false,
        ]);
        SceneSubscription::query()->create([
            'scene_id' => $otherScene->id,
            'user_id' => $user->id,
            'is_muted' => false,
        ]);
        SceneSubscription::query()->create([
            'scene_id' => $alreadyMutedScene->id,
            'user_id' => $user->id,
            'is_muted' => true,
        ]);

        $result = app(BulkUpdateSceneSubscriptionsAction::class)->execute(
            user: $user,
            world: $campaign->world,
            action: 'mute_filtered',
            status: 'active',
            search: 'Doran',
        );

        $this->assertSame(1, $result->affected);
        $this->assertSame('Gefilterte Abos stummgeschaltet.', $result->message);
        $this->assertSame('Gefilterte Abos stummgeschaltet. Betroffene Abos: 1.', $result->flashMessage());

        $this->assertDatabaseHas('scene_subscriptions', [
            'scene_id' => $matchingScene->id,
            'user_id' => $user->id,
            'is_muted' => true,
        ]);
        $this->assertDatabaseHas('scene_subscriptions', [
            'scene_id' => $otherScene->id,
            'user_id' => $user->id,
            'is_muted' => false,
        ]);
    }

    public function test_it_unfollows_only_muted_subscriptions_that_are_visible_to_the_user(): void
    {
        $user = User::factory()->create();
        $gm = User::factory()->gm()->create();

        $visibleCampaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'title' => 'Sichtbare Kampagne',
            'status' => 'active',
            'is_public' => true,
        ]);
        $hiddenCampaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'title' => 'Verborgene Kampagne',
            'status' => 'active',
            'is_public' => false,
            'world_id' => $visibleCampaign->world_id,
        ]);

        $visibleMutedScene = Scene::factory()->create([
            'campaign_id' => $visibleCampaign->id,
            'created_by' => $gm->id,
            'title' => 'Sichtbar-Stumm',
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $visibleActiveScene = Scene::factory()->create([
            'campaign_id' => $visibleCampaign->id,
            'created_by' => $gm->id,
            'title' => 'Sichtbar-Aktiv',
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $hiddenMutedScene = Scene::factory()->create([
            'campaign_id' => $hiddenCampaign->id,
            'created_by' => $gm->id,
            'title' => 'Versteckt-Stumm',
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        SceneSubscription::query()->create([
            'scene_id' => $visibleMutedScene->id,
            'user_id' => $user->id,
            'is_muted' => true,
        ]);
        SceneSubscription::query()->create([
            'scene_id' => $visibleActiveScene->id,
            'user_id' => $user->id,
            'is_muted' => false,
        ]);
        SceneSubscription::query()->create([
            'scene_id' => $hiddenMutedScene->id,
            'user_id' => $user->id,
            'is_muted' => true,
        ]);

        $result = app(BulkUpdateSceneSubscriptionsAction::class)->execute(
            user: $user,
            world: $visibleCampaign->world,
            action: 'unfollow_all_muted',
            status: 'all',
            search: '',
        );

        $this->assertSame(1, $result->affected);
        $this->assertSame('Alle stummen Abos entfernt.', $result->message);

        $this->assertDatabaseMissing('scene_subscriptions', [
            'scene_id' => $visibleMutedScene->id,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('scene_subscriptions', [
            'scene_id' => $visibleActiveScene->id,
            'user_id' => $user->id,
            'is_muted' => false,
        ]);
        $this->assertDatabaseHas('scene_subscriptions', [
            'scene_id' => $hiddenMutedScene->id,
            'user_id' => $user->id,
            'is_muted' => true,
        ]);
    }

    public function test_it_throws_for_unsupported_bulk_action(): void
    {
        $user = User::factory()->create();
        $gm = User::factory()->gm()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported scene subscription bulk action: unsupported_action');

        app(BulkUpdateSceneSubscriptionsAction::class)->execute(
            user: $user,
            world: $campaign->world,
            action: 'unsupported_action',
            status: 'all',
            search: '',
        );
    }
}
