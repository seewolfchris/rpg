<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Actions\Post\Support\PostUpdateModerationContext;
use App\Actions\Post\Support\PostUpdateMutationInput;
use App\Actions\Post\Support\PostUpdateTransactionResult;
use App\Domain\Post\PostModerationService;
use App\Domain\Post\PostNotificationOrchestrator;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use RuntimeException;

final class UpdatePostAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly PostModerationService $postModerationService,
        private readonly PostNotificationOrchestrator $postNotificationOrchestrator,
    ) {}

    /**
     * @param  array{
     *   post_type: string,
     *   post_mode?: string,
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
        $mutation = PostUpdateMutationInput::fromArray($data);
        $result = $this->db->transaction(
            fn (): PostUpdateTransactionResult => $this->applyMutation($post, $editor, $mutation),
            3,
        );
        $this->orchestrateSideEffects($result, $editor);

        $post->refresh();
    }

    private function applyMutation(Post $post, User $editor, PostUpdateMutationInput $mutation): PostUpdateTransactionResult
    {
        $lockedPost = $this->lockAndVerifyContext($post);
        $lockedPost->loadMissing('scene.campaign');

        $campaign = $this->resolveCampaignFromPost($lockedPost);
        $moderationContext = $this->resolveModerationContext($campaign, $lockedPost, $editor, $mutation);
        $normalizedMeta = $this->normalizedNextMeta($lockedPost, $mutation);

        $hasContentChange = $this->hasTrackedChanges($lockedPost, [
            'character_id' => $mutation->characterId,
            'post_type' => $mutation->postType,
            'content_format' => $mutation->contentFormat,
            'content' => $mutation->content,
            'meta' => $normalizedMeta,
        ]);

        if ($hasContentChange) {
            $this->createRevisionSnapshot($lockedPost, $editor);
        }

        $this->persistPostMutation($lockedPost, $mutation, $normalizedMeta, $hasContentChange, $moderationContext);

        return new PostUpdateTransactionResult($lockedPost, $moderationContext, $hasContentChange);
    }

    private function orchestrateSideEffects(PostUpdateTransactionResult $result, User $editor): void
    {
        $this->postModerationService->synchronize(
            post: $result->post,
            moderator: $result->moderationContext->isModerator ? $editor : null,
            previousStatus: $result->moderationContext->previousStatus,
            moderationNote: $result->moderationContext->moderationNote,
        );

        if ($result->hasContentChange) {
            $this->postNotificationOrchestrator->notifyMentionsWithRetry($result->post, $editor, 'update_post');
        }
    }

    private function lockAndVerifyContext(Post $post): Post
    {
        /** @var Post $lockedPost */
        $lockedPost = Post::query()
            ->whereKey((int) $post->id)
            ->where('scene_id', (int) $post->scene_id)
            ->whereHas('scene.campaign.world')
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedPost;
    }

    private function resolveCampaignFromPost(Post $post): Campaign
    {
        $scene = $post->scene;
        if (! $scene instanceof Scene) {
            throw new RuntimeException('Post without valid scene relation cannot be updated.');
        }

        $campaign = $scene->campaign;
        if (! $campaign instanceof Campaign) {
            throw new RuntimeException('Scene without valid campaign relation cannot be updated.');
        }

        return $campaign;
    }

    private function resolveModerationContext(
        Campaign $campaign,
        Post $post,
        User $editor,
        PostUpdateMutationInput $mutation,
    ): PostUpdateModerationContext
    {
        $isModerator = $editor->can('moderate', $post);
        $requiresApproval = $campaign->requiresPostModeration()
            && ! $campaign->userCanPostWithoutModeration($editor)
            && ! $isModerator;
        $previousStatus = (string) $post->moderation_status;
        $normalizedNote = $isModerator
            ? $this->normalizeModerationNote($mutation->moderationNote)
            : null;

        $moderationStatus = $requiresApproval ? 'pending' : 'approved';
        $approvedAt = $requiresApproval ? null : Carbon::now();
        $approvedBy = null;

        if ($isModerator && $mutation->moderationStatus !== null) {
            $moderationStatus = $mutation->moderationStatus;

            if ($moderationStatus === 'approved') {
                $approvedAt = Carbon::now();
                $approvedBy = (int) $editor->id;
            }
        }

        return new PostUpdateModerationContext(
            isModerator: $isModerator,
            previousStatus: $previousStatus,
            moderationNote: $normalizedNote,
            moderationStatus: $moderationStatus,
            approvedAt: $approvedAt,
            approvedBy: $approvedBy,
        );
    }

    /**
     * @param  array<string, mixed>|null  $normalizedMeta
     */
    private function persistPostMutation(
        Post $post,
        PostUpdateMutationInput $mutation,
        ?array $normalizedMeta,
        bool $hasContentChange,
        PostUpdateModerationContext $moderationContext,
    ): void {
        $post->update([
            'character_id' => $mutation->characterId,
            'post_type' => $mutation->postType,
            'content_format' => $mutation->contentFormat,
            'content' => $mutation->content,
            'meta' => $normalizedMeta,
            'moderation_status' => $moderationContext->moderationStatus,
            'approved_at' => $moderationContext->approvedAt,
            'approved_by' => $moderationContext->approvedBy,
            'is_edited' => $hasContentChange ? true : $post->is_edited,
            'edited_at' => $hasContentChange ? now() : $post->edited_at,
        ]);
    }

    private function normalizeModerationNote(string $note): ?string
    {
        $normalized = trim($note);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizedNextMeta(Post $post, PostUpdateMutationInput $mutation): ?array
    {
        /** @var array<string, mixed> $nextMeta */
        $nextMeta = (array) ($post->meta ?? []);
        $nextIcQuote = trim($mutation->icQuote);

        if ($mutation->postType === 'ic' && $mutation->postMode === 'gm') {
            $nextMeta['author_role'] = 'gm';
        } else {
            unset($nextMeta['author_role']);
        }

        if ($mutation->postType === 'ic' && $nextIcQuote !== '') {
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
