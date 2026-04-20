<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Post;

use App\Actions\Post\ApplyPostModerationTransitionAction;
use App\Actions\Post\ModeratePostAction;
use App\Domain\Post\PostModerationService;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModeratePostActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_approved_state_and_synchronizes_with_normalized_note(): void
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
        ]);
        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $moderationService = $this->createMock(PostModerationService::class);
        $moderationService->expects($this->once())
            ->method('synchronize')
            ->with(
                $this->callback(static fn (Post $updatedPost): bool => $updatedPost->is($post)),
                $this->callback(static fn (User $moderator): bool => $moderator->is($gm)),
                'pending',
                'Freigabe',
            );

        $action = new ModeratePostAction(new ApplyPostModerationTransitionAction($moderationService));
        $action->execute($post, $gm, 'approved', '  Freigabe  ');

        $post->refresh();

        $this->assertSame('approved', (string) $post->moderation_status);
        $this->assertSame((int) $gm->id, (int) ($post->approved_by ?? 0));
        $this->assertNotNull($post->approved_at);
    }

    public function test_it_clears_approval_state_for_rejected_status(): void
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
        ]);
        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'moderation_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $gm->id,
        ]);

        $moderationService = $this->createMock(PostModerationService::class);
        $moderationService->expects($this->once())
            ->method('synchronize')
            ->with(
                $this->callback(static fn (Post $updatedPost): bool => $updatedPost->is($post)),
                $this->callback(static fn (User $moderator): bool => $moderator->is($gm)),
                'approved',
                null,
            );

        $action = new ModeratePostAction(new ApplyPostModerationTransitionAction($moderationService));
        $action->execute($post, $gm, 'rejected', '   ');

        $post->refresh();

        $this->assertSame('rejected', (string) $post->moderation_status);
        $this->assertNull($post->approved_at);
        $this->assertNull($post->approved_by);
    }
}
