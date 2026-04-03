<?php

namespace Tests\Feature;

use App\Domain\Post\PostMentionNotificationService;
use App\Jobs\Post\RetryPostMentionNotificationsJob;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class PostUpdateNotificationFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_update_does_not_fail_when_mention_dispatch_throws(): void
    {
        config(['features.wave4.mentions' => true]);
        Queue::fake();

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
        $campaign->invitations()->create([
            'user_id' => $player->id,
            'invited_by' => $gm->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'accepted_at' => now(),
            'responded_at' => now(),
            'created_at' => now(),
        ]);
        $character = Character::factory()->create([
            'user_id' => $player->id,
            'world_id' => $campaign->world_id,
        ]);
        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'character_id' => $character->id,
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'content' => 'Alter Inhalt',
            'meta' => ['ic_quote' => 'Alt'],
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
            'is_edited' => false,
            'edited_at' => null,
        ]);

        $this->mock(PostMentionNotificationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('notifyMentions')
                ->once()
                ->andThrow(new RuntimeException('forced mention dispatch failure'));
        });

        $response = $this->actingAs($player)->patch(route('posts.update', [
            'world' => $campaign->world,
            'post' => $post,
        ]), [
            'post_type' => 'ic',
            'character_id' => $character->id,
            'content_format' => 'markdown',
            'content' => 'Neuer Inhalt mit @Testfigur',
            'ic_quote' => 'Neu',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $post->refresh();

        $this->assertSame('Neuer Inhalt mit @Testfigur', (string) $post->content);
        $this->assertTrue((bool) $post->is_edited);
        $this->assertDatabaseHas('post_revisions', [
            'post_id' => $post->id,
            'version' => 1,
            'content' => 'Alter Inhalt',
        ]);
        Queue::assertPushed(RetryPostMentionNotificationsJob::class);
    }
}
