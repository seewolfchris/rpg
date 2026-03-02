<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;
use App\Notifications\SceneNewPostNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_moderation_change_notifies_post_author(): void
    {
        [$gm, $player, $campaign, $scene, $character] = $this->seedSceneContext();

        $post = Post::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'character_id' => $character->id,
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'content' => 'Ein Opfertritt in der kalten Halle.',
            'moderation_status' => 'pending',
        ]);

        $this->actingAs($gm)->patch(route('posts.moderate', $post), [
            'moderation_status' => 'approved',
        ])->assertRedirect();

        $notification = $player->fresh()->unreadNotifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame('post_moderation', $notification->data['kind'] ?? null);
        $this->assertSame('approved', $notification->data['new_status'] ?? null);
        $this->assertSame($post->id, $notification->data['post_id'] ?? null);
    }

    public function test_new_scene_post_notifies_only_active_scene_followers(): void
    {
        $gm = User::factory()->gm()->create();
        $author = User::factory()->create();
        $otherPlayer = User::factory()->create();
        $mutedPlayer = User::factory()->create();

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

        SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
            'is_muted' => false,
        ]);
        SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $otherPlayer->id,
            'is_muted' => false,
        ]);
        SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $mutedPlayer->id,
            'is_muted' => true,
        ]);

        $this->actingAs($author)->post(route('campaigns.scenes.posts.store', [$campaign, $scene]), [
            'post_type' => 'ooc',
            'content_format' => 'markdown',
            'content' => 'Neuer Beitrag aus den Schatten.',
        ])->assertRedirect();

        $gmNotification = $gm->fresh()->unreadNotifications()->first();
        $otherNotification = $otherPlayer->fresh()->unreadNotifications()->first();
        $mutedNotificationCount = $mutedPlayer->fresh()->unreadNotifications()->count();
        $authorUnreadCount = $author->fresh()->unreadNotifications()->count();

        $this->assertNotNull($gmNotification);
        $this->assertNotNull($otherNotification);
        $this->assertSame(0, $authorUnreadCount);
        $this->assertSame(0, $mutedNotificationCount);
        $this->assertSame('scene_new_post', $gmNotification->data['kind'] ?? null);
        $this->assertSame('scene_new_post', $otherNotification->data['kind'] ?? null);
    }

    public function test_notification_center_can_mark_single_and_all_as_read(): void
    {
        [$gm, $player, $campaign, $scene, $character] = $this->seedSceneContext();

        $post = Post::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
            'character_id' => null,
            'post_type' => 'ooc',
            'content_format' => 'markdown',
            'content' => 'Ein Vorbote der Nacht.',
            'moderation_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $gm->id,
        ]);

        $player->notify(new SceneNewPostNotification($post, $gm));
        $player->notify(new SceneNewPostNotification($post, $gm));

        $index = $this->actingAs($player)->get(route('notifications.index'));
        $index->assertOk();
        $index->assertSee('Benachrichtigungen');

        $first = $player->fresh()->unreadNotifications()->firstOrFail();
        $expectedRedirect = route('campaigns.scenes.show', [$campaign, $scene]).'#post-'.$post->id;

        $this->actingAs($player)->post(route('notifications.read', $first->id))
            ->assertRedirect($expectedRedirect);

        $firstReadAt = $player->fresh()->notifications()->whereKey($first->id)->value('read_at');
        $this->assertNotNull($firstReadAt);

        $this->actingAs($player)->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $player->fresh()->unreadNotifications()->count());
    }

    /**
     * @return array{0: User, 1: User, 2: Campaign, 3: Scene, 4: Character}
     */
    private function seedSceneContext(): array
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
            'allow_ooc' => true,
        ]);

        $character = Character::factory()->create([
            'user_id' => $player->id,
        ]);

        return [$gm, $player, $campaign, $scene, $character];
    }
}
