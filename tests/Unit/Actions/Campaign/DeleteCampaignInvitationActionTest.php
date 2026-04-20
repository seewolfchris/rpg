<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Campaign;

use App\Actions\Campaign\DeleteCampaignInvitationAction;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Scene;
use App\Models\SceneBookmark;
use App\Models\SceneSubscription;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class DeleteCampaignInvitationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_accepted_invitation_and_cleans_related_access_data(): void
    {
        $owner = User::factory()->gm()->create();
        $invitedUser = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignOwner = User::factory()->gm()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);
        $sceneOne = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $sceneTwo = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $foreignCampaign = Campaign::factory()->create([
            'owner_id' => $foreignOwner->id,
            'status' => 'active',
            'is_public' => false,
        ]);
        $foreignScene = Scene::factory()->create([
            'campaign_id' => $foreignCampaign->id,
            'created_by' => $foreignOwner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $invitation = CampaignInvitation::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $invitedUser->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'accepted_at' => now(),
            'responded_at' => now(),
            'created_at' => now(),
        ]);

        SceneSubscription::query()->create([
            'scene_id' => $sceneOne->id,
            'user_id' => $invitedUser->id,
            'is_muted' => false,
            'last_read_post_id' => null,
            'last_read_at' => null,
        ]);
        SceneSubscription::query()->create([
            'scene_id' => $sceneTwo->id,
            'user_id' => $invitedUser->id,
            'is_muted' => true,
            'last_read_post_id' => null,
            'last_read_at' => null,
        ]);
        SceneSubscription::query()->create([
            'scene_id' => $foreignScene->id,
            'user_id' => $invitedUser->id,
            'is_muted' => false,
            'last_read_post_id' => null,
            'last_read_at' => null,
        ]);
        SceneSubscription::query()->create([
            'scene_id' => $sceneOne->id,
            'user_id' => $otherUser->id,
            'is_muted' => false,
            'last_read_post_id' => null,
            'last_read_at' => null,
        ]);

        SceneBookmark::query()->create([
            'scene_id' => $sceneOne->id,
            'user_id' => $invitedUser->id,
            'post_id' => null,
            'label' => 'A',
        ]);
        SceneBookmark::query()->create([
            'scene_id' => $sceneTwo->id,
            'user_id' => $invitedUser->id,
            'post_id' => null,
            'label' => 'B',
        ]);
        SceneBookmark::query()->create([
            'scene_id' => $foreignScene->id,
            'user_id' => $invitedUser->id,
            'post_id' => null,
            'label' => 'C',
        ]);
        SceneBookmark::query()->create([
            'scene_id' => $sceneOne->id,
            'user_id' => $otherUser->id,
            'post_id' => null,
            'label' => 'D',
        ]);

        app(DeleteCampaignInvitationAction::class)->execute($invitation);

        $this->assertDatabaseMissing('campaign_invitations', [
            'id' => $invitation->id,
        ]);
        $this->assertDatabaseMissing('scene_subscriptions', [
            'scene_id' => $sceneOne->id,
            'user_id' => $invitedUser->id,
        ]);
        $this->assertDatabaseMissing('scene_subscriptions', [
            'scene_id' => $sceneTwo->id,
            'user_id' => $invitedUser->id,
        ]);
        $this->assertDatabaseMissing('scene_bookmarks', [
            'scene_id' => $sceneOne->id,
            'user_id' => $invitedUser->id,
        ]);
        $this->assertDatabaseMissing('scene_bookmarks', [
            'scene_id' => $sceneTwo->id,
            'user_id' => $invitedUser->id,
        ]);

        $this->assertDatabaseHas('scene_subscriptions', [
            'scene_id' => $foreignScene->id,
            'user_id' => $invitedUser->id,
        ]);
        $this->assertDatabaseHas('scene_subscriptions', [
            'scene_id' => $sceneOne->id,
            'user_id' => $otherUser->id,
        ]);
        $this->assertDatabaseHas('scene_bookmarks', [
            'scene_id' => $foreignScene->id,
            'user_id' => $invitedUser->id,
        ]);
        $this->assertDatabaseHas('scene_bookmarks', [
            'scene_id' => $sceneOne->id,
            'user_id' => $otherUser->id,
        ]);
    }

    public function test_it_deletes_pending_invitation_without_cleaning_scene_access_rows(): void
    {
        $owner = User::factory()->gm()->create();
        $invitedUser = User::factory()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $invitation = CampaignInvitation::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $invitedUser->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_PENDING,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'accepted_at' => null,
            'responded_at' => null,
            'created_at' => now(),
        ]);

        SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $invitedUser->id,
            'is_muted' => false,
            'last_read_post_id' => null,
            'last_read_at' => null,
        ]);
        SceneBookmark::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $invitedUser->id,
            'post_id' => null,
            'label' => 'Zwischenstand',
        ]);

        app(DeleteCampaignInvitationAction::class)->execute($invitation);

        $this->assertDatabaseMissing('campaign_invitations', [
            'id' => $invitation->id,
        ]);
        $this->assertDatabaseHas('scene_subscriptions', [
            'scene_id' => $scene->id,
            'user_id' => $invitedUser->id,
        ]);
        $this->assertDatabaseHas('scene_bookmarks', [
            'scene_id' => $scene->id,
            'user_id' => $invitedUser->id,
        ]);
    }

    public function test_it_throws_when_invitation_context_is_mismatched(): void
    {
        $owner = User::factory()->gm()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);
        $foreignCampaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);
        $invitedUser = User::factory()->create();

        $invitation = CampaignInvitation::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $invitedUser->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_PENDING,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'accepted_at' => null,
            'responded_at' => null,
            'created_at' => now(),
        ]);

        $invitation->setAttribute('campaign_id', (int) $foreignCampaign->id);

        $this->expectException(ModelNotFoundException::class);

        try {
            app(DeleteCampaignInvitationAction::class)->execute($invitation);
        } finally {
            $this->assertDatabaseHas('campaign_invitations', [
                'id' => $invitation->id,
                'campaign_id' => $campaign->id,
            ]);
        }
    }

    public function test_it_rolls_back_cleanup_when_transaction_fails_after_mutation(): void
    {
        $owner = User::factory()->gm()->create();
        $invitedUser = User::factory()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $invitation = CampaignInvitation::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $invitedUser->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'accepted_at' => now(),
            'responded_at' => now(),
            'created_at' => now(),
        ]);

        SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $invitedUser->id,
            'is_muted' => false,
            'last_read_post_id' => null,
            'last_read_at' => null,
        ]);
        SceneBookmark::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $invitedUser->id,
            'post_id' => null,
            'label' => 'Rollback',
        ]);

        $realDb = app(DatabaseManager::class);
        $mockedDb = Mockery::mock(DatabaseManager::class);
        $mockedDb->shouldReceive('transaction')
            ->once()
            ->withArgs(static fn (mixed $callback, mixed $attempts): bool => is_callable($callback) && $attempts === 3)
            ->andReturnUsing(function (callable $callback) use ($realDb): mixed {
                $connection = $realDb->connection();
                $connection->beginTransaction();

                try {
                    $callback();

                    throw new RuntimeException('Forced invitation delete failure');
                } catch (Throwable $throwable) {
                    $connection->rollBack();

                    throw $throwable;
                }
            });

        $action = new DeleteCampaignInvitationAction($mockedDb);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forced invitation delete failure');

        try {
            $action->execute($invitation);
        } finally {
            $this->assertDatabaseHas('campaign_invitations', [
                'id' => $invitation->id,
            ]);
            $this->assertDatabaseHas('scene_subscriptions', [
                'scene_id' => $scene->id,
                'user_id' => $invitedUser->id,
            ]);
            $this->assertDatabaseHas('scene_bookmarks', [
                'scene_id' => $scene->id,
                'user_id' => $invitedUser->id,
            ]);
        }
    }
}
