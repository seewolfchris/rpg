<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Domain\Post\PostModerationService;
use App\Domain\Post\PostNotificationOrchestrator;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UpdatePostAction
{
    public function __construct(
        private readonly PostModerationService $postModerationService,
        private readonly PostNotificationOrchestrator $postNotificationOrchestrator,
    ) {}

    /**
     * @param  array{
     *   post_type: string,
     *   character_id?: mixed,
     *   content_format: string,
     *   content: string,
     *   ic_quote?: mixed,
     *   moderation_status?: mixed,
     *   moderation_note?: mixed
     * }  $data
     */
    public function execute(Post $post, User $editor, array $data): void
    {
        $postId = (int) $post->getKey();
        $postType = (string) $data['post_type'];
        $nextCharacterId = $postType === 'ic'
            ? (int) ($data['character_id'] ?? 0)
            : null;
        $contentFormat = (string) $data['content_format'];
        $content = (string) $data['content'];
        $icQuote = (string) ($data['ic_quote'] ?? '');
        $providedModerationStatus = isset($data['moderation_status'])
            ? (string) $data['moderation_status']
            : null;
        $providedModerationNote = (string) ($data['moderation_note'] ?? '');

        $updatedPost = null;
        $isModerator = false;
        $previousModerationStatus = '';
        $moderationNote = null;
        $hasContentChange = false;

        DB::transaction(function () use (
            $postId,
            $editor,
            $postType,
            $nextCharacterId,
            $contentFormat,
            $content,
            $icQuote,
            $providedModerationStatus,
            $providedModerationNote,
            &$updatedPost,
            &$isModerator,
            &$previousModerationStatus,
            &$moderationNote,
            &$hasContentChange,
        ): void {
            $lockedPost = Post::query()
                ->whereKey($postId)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedPost->loadMissing('scene.campaign');

            $scene = $this->resolveScene($lockedPost);
            $campaign = $this->resolveCampaign($scene);

            $isModerator = $editor->can('moderate', $lockedPost);
            $requiresApproval = $campaign->requiresPostModeration()
                && ! $campaign->userCanPostWithoutModeration($editor)
                && ! $isModerator;
            $previousModerationStatus = (string) $lockedPost->moderation_status;
            $moderationNote = $isModerator
                ? $this->normalizeModerationNote($providedModerationNote)
                : null;

            $moderationStatus = $requiresApproval ? 'pending' : 'approved';
            $approvedAt = $requiresApproval ? null : Carbon::now();
            $approvedBy = null;

            if ($isModerator && $providedModerationStatus !== null) {
                $moderationStatus = $providedModerationStatus;

                if ($moderationStatus === 'approved') {
                    $approvedAt = Carbon::now();
                    $approvedBy = (int) $editor->id;
                }
            }

            $normalizedNextMeta = $this->normalizedNextMeta($lockedPost, $postType, $icQuote);
            $hasContentChange = $this->hasTrackedChanges($lockedPost, [
                'character_id' => $nextCharacterId,
                'post_type' => $postType,
                'content_format' => $contentFormat,
                'content' => $content,
                'meta' => $normalizedNextMeta,
            ]);

            if ($hasContentChange) {
                $this->createRevisionSnapshot($lockedPost, $editor);
            }

            $lockedPost->update([
                'character_id' => $nextCharacterId,
                'post_type' => $postType,
                'content_format' => $contentFormat,
                'content' => $content,
                'meta' => $normalizedNextMeta,
                'moderation_status' => $moderationStatus,
                'approved_at' => $approvedAt,
                'approved_by' => $approvedBy,
                'is_edited' => $hasContentChange ? true : $lockedPost->is_edited,
                'edited_at' => $hasContentChange ? now() : $lockedPost->edited_at,
            ]);

            $updatedPost = $lockedPost;
        }, 3);

        if (! $updatedPost instanceof Post) {
            throw new RuntimeException('Post update failed unexpectedly.');
        }

        $this->postModerationService->synchronize(
            post: $updatedPost,
            moderator: $isModerator ? $editor : null,
            previousStatus: $previousModerationStatus,
            moderationNote: $moderationNote,
        );

        if ($hasContentChange) {
            $this->postNotificationOrchestrator->notifyMentionsWithRetry($updatedPost, $editor, 'update_post');
        }

        $post->refresh();
    }

    private function resolveScene(Post $post): Scene
    {
        $scene = $post->scene;

        if (! $scene instanceof Scene) {
            throw new RuntimeException('Post without valid scene relation cannot be updated.');
        }

        return $scene;
    }

    private function resolveCampaign(Scene $scene): Campaign
    {
        $campaign = $scene->campaign;

        if (! $campaign instanceof Campaign) {
            throw new RuntimeException('Scene without valid campaign relation cannot be updated.');
        }

        return $campaign;
    }

    private function normalizeModerationNote(string $note): ?string
    {
        $normalized = trim($note);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizedNextMeta(Post $post, string $postType, string $icQuote): ?array
    {
        /** @var array<string, mixed> $nextMeta */
        $nextMeta = (array) ($post->meta ?? []);
        $nextIcQuote = trim($icQuote);

        if ($postType === 'ic' && $nextIcQuote !== '') {
            $nextMeta['ic_quote'] = $nextIcQuote;
        } else {
            unset($nextMeta['ic_quote']);
        }

        return $nextMeta !== [] ? $nextMeta : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function hasTrackedChanges(Post $post, array $attributes): bool
    {
        foreach ($attributes as $key => $value) {
            if ($post->{$key} !== $value) {
                return true;
            }
        }

        return false;
    }

    private function createRevisionSnapshot(Post $post, User $editor): void
    {
        $nextVersion = ((int) $post->revisions()->max('version')) + 1;

        $post->revisions()->create([
            'version' => $nextVersion,
            'editor_id' => $editor->id,
            'character_id' => $post->character_id,
            'post_type' => $post->post_type,
            'content_format' => $post->content_format,
            'content' => $post->content,
            'meta' => $post->meta,
            'moderation_status' => $post->moderation_status,
            'created_at' => now(),
        ]);
    }
}
