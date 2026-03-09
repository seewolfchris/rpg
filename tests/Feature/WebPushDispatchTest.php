<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\PushSubscription;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;
use App\Notifications\SceneNewPostWebPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WebPushDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_scene_new_post_webpush_is_sent_only_to_browser_enabled_and_subscribed_users(): void
    {
        Notification::fake();

        $gm = User::factory()->gm()->create();
        $author = User::factory()->create();
        $receiver = User::factory()->create([
            'notification_preferences' => [
                'post_moderation' => ['database' => true, 'mail' => false, 'browser' => false],
                'scene_new_post' => ['database' => true, 'mail' => false, 'browser' => true],
                'campaign_invitation' => ['database' => true, 'mail' => false, 'browser' => false],
            ],
        ]);
        $muted = User::factory()->create([
            'notification_preferences' => [
                'post_moderation' => ['database' => true, 'mail' => false, 'browser' => false],
                'scene_new_post' => ['database' => true, 'mail' => false, 'browser' => true],
                'campaign_invitation' => ['database' => true, 'mail' => false, 'browser' => false],
            ],
        ]);

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
            'user_id' => $receiver->id,
            'is_muted' => false,
        ]);
        SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $muted->id,
            'is_muted' => true,
        ]);

        PushSubscription::query()->create([
            'subscribable_type' => $receiver->getMorphClass(),
            'subscribable_id' => $receiver->id,
            'user_id' => $receiver->id,
            'world_id' => $campaign->world_id,
            'endpoint' => 'https://example.push.local/subscription/receiver',
            'public_key' => 'public-key-1',
            'auth_token' => 'auth-token-1',
            'content_encoding' => 'aes128gcm',
        ]);
        PushSubscription::query()->create([
            'subscribable_type' => $muted->getMorphClass(),
            'subscribable_id' => $muted->id,
            'user_id' => $muted->id,
            'world_id' => $campaign->world_id,
            'endpoint' => 'https://example.push.local/subscription/muted',
            'public_key' => 'public-key-2',
            'auth_token' => 'auth-token-2',
            'content_encoding' => 'aes128gcm',
        ]);

        $this->actingAs($author)->post(route('campaigns.scenes.posts.store', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]), [
            'post_type' => 'ooc',
            'content_format' => 'markdown',
            'content' => 'WebPush Testbeitrag',
        ])->assertRedirect();

        Notification::assertSentTo(
            [$receiver],
            SceneNewPostWebPush::class
        );

        Notification::assertNotSentTo(
            [$muted, $author],
            SceneNewPostWebPush::class
        );
    }
}
