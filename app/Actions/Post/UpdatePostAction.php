<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Domain\Post\PostMentionNotificationService;
use App\Domain\Post\PostModerationService;
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
        private readonly PostMentionNotificationService $postMentionNotificationService,
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
        $scene = $this->resolveScene($post);
        $campaign = $this->resolveCampaign($scene);

        $isModerator = $editor->can('moderate', $post);
        $requiresApproval = $campaign->requiresPostModeration()
            && ! $campaign->userCanPostWithoutModeration($editor)
            && ! $isModerator;
        $previousModerationStatus = (string) $post->moderation_status;
        $moderationNote = $isModerator
            ? $this->normalizeModerationNote((string) ($data['moderation_note'] ?? ''))
            : null;

        $moderationStatus = $requiresApproval ? 'pending' : 'approved';
        $approvedAt = $requiresApproval ? null : Carbon::now();
        $approvedBy = null;

        if ($isModerator && isset($data['moderation_status'])) {
            $moderationStatus = (string) $data['moderation_status'];

            if ($moderationStatus === 'approved') {
                $approvedAt = Carbon::now();
                $approvedBy = (int) $editor->id;
            }
        }

        $postType = (string) $data['post_type'];
        $nextCharacterId = $postType === 'ic'
            ? (int) ($data['character_id'] ?? 0)
            : null;
        $normalizedNextMeta = $this->normalizedNextMeta($post, $postType, (string) ($data['ic_quote'] ?? ''));

        $hasContentChange = $this->hasTrackedChanges($post, [
            'character_id' => $nextCharacterId,
            'post_type' => $postType,
            'content_format' => (string) $data['content_format'],
            'content' => (string) $data['content'],
            'meta' => $normalizedNextMeta,
        ]);

        if ($hasContentChange) {
            $this->createRevisionSnapshot($post, $editor);
        }

        $post->update([
            'character_id' => $nextCharacterId,
            'post_type' => $postType,
            'content_format' => (string) $data['content_format'],
            'content' => (string) $data['content'],
            'meta' => $normalizedNextMeta,
            'moderation_status' => $moderationStatus,
            'approved_at' => $approvedAt,
            'approved_by' => $approvedBy,
            'is_edited' => $hasContentChange ? true : $post->is_edited,
            'edited_at' => $hasContentChange ? now() : $post->edited_at,
        ]);

        $this->postModerationService->synchronize(
            post: $post,
            moderator: $isModerator ? $editor : null,
            previousStatus: $previousModerationStatus,
            moderationNote: $moderationNote,
        );

        if ($hasContentChange) {
            $this->postMentionNotificationService->notifyMentions($post, $editor);
        }
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
        DB::transaction(function () use ($post, $editor): void {
            $lockedPost = Post::query()
                ->whereKey($post->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $nextVersion = ((int) $lockedPost->revisions()->max('version')) + 1;

            $lockedPost->revisions()->create([
                'version' => $nextVersion,
                'editor_id' => $editor->id,
                'character_id' => $lockedPost->character_id,
                'post_type' => $lockedPost->post_type,
                'content_format' => $lockedPost->content_format,
                'content' => $lockedPost->content,
                'meta' => $lockedPost->meta,
                'moderation_status' => $lockedPost->moderation_status,
                'created_at' => now(),
            ]);
        }, 3);
    }
}
