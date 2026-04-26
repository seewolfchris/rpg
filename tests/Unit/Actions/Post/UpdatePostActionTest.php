<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Post;

use App\Actions\Post\UpdatePostAction;
use App\Domain\Post\PostImmersiveImageService;
use App\Domain\Post\PostModerationService;
use App\Domain\Post\PostNotificationOrchestrator;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdatePostActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_revision_and_notifies_mentions_when_content_changes(): void
    {
        [, $player, $post] = $this->seedPostContext();

        $moderationService = $this->createMock(PostModerationService::class);
        $moderationService->expects($this->once())
            ->method('synchronize')
            ->with(
                $this->callback(static fn (Post $updatedPost): bool => $updatedPost->is($post)),
                null,
                'pending',
                null,
            );

        $notificationOrchestrator = $this->createMock(PostNotificationOrchestrator::class);
        $notificationOrchestrator->expects($this->once())
            ->method('notifyMentionsWithRetry')
            ->with(
                $this->callback(static fn (Post $updatedPost): bool => $updatedPost->is($post)),
                $this->callback(static fn (User $author): bool => $author->is($player)),
                'update_post',
            )
            ->willReturn(0);

        $action = new UpdatePostAction(
            app(DatabaseManager::class),
            app(PostImmersiveImageService::class),
            $moderationService,
            $notificationOrchestrator,
        );

        $action->execute($post, $player, [
            'post_type' => 'ic',
            'character_id' => (int) $post->character_id,
            'content_format' => 'markdown',
            'content' => 'Neuer Inhalt mit @Fackeltraeger',
            'ic_quote' => 'Neues IC-Zitat',
        ]);

        $post->refresh();

        $this->assertSame('Neuer Inhalt mit @Fackeltraeger', (string) $post->content);
        $this->assertTrue((bool) $post->is_edited);
        $this->assertNotNull($post->edited_at);
        $this->assertSame('pending', (string) $post->moderation_status);

        $this->assertDatabaseCount('post_revisions', 1);
        $this->assertDatabaseHas('post_revisions', [
            'post_id' => $post->id,
            'version' => 1,
            'editor_id' => $player->id,
            'content' => 'Alter Inhalt',
            'moderation_status' => 'pending',
        ]);
    }

    public function test_it_throws_when_post_context_is_tampered(): void
    {
        [, $player, $post] = $this->seedPostContext();
        $foreignScene = Scene::factory()->create([
            'campaign_id' => (int) $post->scene->campaign_id,
            'created_by' => (int) $post->scene->created_by,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $moderationService = $this->createMock(PostModerationService::class);
        $moderationService->expects($this->never())->method('synchronize');

        $notificationOrchestrator = $this->createMock(PostNotificationOrchestrator::class);
        $notificationOrchestrator->expects($this->never())->method('notifyMentionsWithRetry');

        $action = new UpdatePostAction(
            app(DatabaseManager::class),
            app(PostImmersiveImageService::class),
            $moderationService,
            $notificationOrchestrator,
        );

        $post->setAttribute('scene_id', (int) $foreignScene->id);

        $this->expectException(ModelNotFoundException::class);

        try {
            $action->execute($post, $player, [
                'post_type' => 'ic',
                'character_id' => (int) $post->character_id,
                'content_format' => 'markdown',
                'content' => 'Wird nicht gespeichert',
                'ic_quote' => 'Unveraendert',
            ]);
        } finally {
            $this->assertDatabaseHas('posts', [
                'id' => $post->id,
                'content' => 'Alter Inhalt',
            ]);
        }
    }

    public function test_it_applies_moderation_without_revision_when_content_is_unchanged(): void
    {
        [$gm, , $post] = $this->seedPostContext();

        $moderationService = $this->createMock(PostModerationService::class);
        $moderationService->expects($this->once())
            ->method('synchronize')
            ->with(
                $this->callback(static fn (Post $updatedPost): bool => $updatedPost->is($post)),
                $this->callback(static fn (User $moderator): bool => $moderator->is($gm)),
                'pending',
                'Freigabe durch Spielleitung',
            );

        $notificationOrchestrator = $this->createMock(PostNotificationOrchestrator::class);
        $notificationOrchestrator->expects($this->never())->method('notifyMentionsWithRetry');

        $action = new UpdatePostAction(
            app(DatabaseManager::class),
            app(PostImmersiveImageService::class),
            $moderationService,
            $notificationOrchestrator,
        );

        $action->execute($post, $gm, [
            'post_type' => 'ic',
            'character_id' => (int) $post->character_id,
            'content_format' => 'markdown',
            'content' => 'Alter Inhalt',
            'ic_quote' => 'Altes IC-Zitat',
            'moderation_status' => 'approved',
            'moderation_note' => 'Freigabe durch Spielleitung',
        ]);

        $post->refresh();

        $this->assertSame('approved', (string) $post->moderation_status);
        $this->assertSame($gm->id, (int) $post->approved_by);
        $this->assertNotNull($post->approved_at);
        $this->assertFalse((bool) $post->is_edited);

        $this->assertDatabaseCount('post_revisions', 0);
    }

    public function test_it_applies_gm_narration_semantics_and_keeps_side_effects_intact(): void
    {
        [$gm, , $post] = $this->seedPostContext();
        $originalCharacterId = (int) $post->character_id;

        $moderationService = $this->createMock(PostModerationService::class);
        $moderationService->expects($this->once())
            ->method('synchronize')
            ->with(
                $this->callback(static fn (Post $updatedPost): bool => $updatedPost->is($post)),
                $this->callback(static fn (User $moderator): bool => $moderator->is($gm)),
                'pending',
                null,
            );

        $notificationOrchestrator = $this->createMock(PostNotificationOrchestrator::class);
        $notificationOrchestrator->expects($this->once())
            ->method('notifyMentionsWithRetry')
            ->with(
                $this->callback(static fn (Post $updatedPost): bool => $updatedPost->is($post)),
                $this->callback(static fn (User $editor): bool => $editor->is($gm)),
                'update_post',
            )
            ->willReturn(0);

        $action = new UpdatePostAction(
            app(DatabaseManager::class),
            app(PostImmersiveImageService::class),
            $moderationService,
            $notificationOrchestrator,
        );

        $action->execute($post, $gm, [
            'post_type' => 'ic',
            'post_mode' => 'gm',
            'character_id' => (int) $post->character_id,
            'content_format' => 'markdown',
            'content' => 'Alter Inhalt',
            'ic_quote' => '',
        ]);

        $post->refresh();

        $this->assertSame('ic', (string) $post->post_type);
        $this->assertNull($post->character_id);
        $this->assertSame('gm', data_get($post->meta, 'author_role'));
        $this->assertNull(data_get($post->meta, 'ic_quote'));
        $this->assertTrue((bool) $post->is_edited);
        $this->assertNotNull($post->edited_at);
        $this->assertSame('approved', (string) $post->moderation_status);

        $this->assertDatabaseCount('post_revisions', 1);
        $this->assertDatabaseHas('post_revisions', [
            'post_id' => $post->id,
            'character_id' => $originalCharacterId,
            'content' => 'Alter Inhalt',
        ]);
    }

    /**
     * @return array{0: User, 1: User, 2: Post}
     */
    private function seedPostContext(): array
    {
        $gm = User::factory()->gm()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
            'requires_post_moderation' => false,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
            'allow_ooc' => true,
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
            'meta' => ['ic_quote' => 'Altes IC-Zitat'],
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
            'is_edited' => false,
            'edited_at' => null,
        ]);

        return [$gm, $player, $post];
    }
}
