<?php

namespace App\Domain\Post;

use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;
use App\Support\Gamification\PointService;
use App\Support\Observability\StructuredLogger;
use Illuminate\Database\DatabaseManager;

class StorePostService
{
    public function __construct(
        private readonly PostProbeService $postProbeService,
        private readonly PostInventoryAwardService $postInventoryAwardService,
        private readonly PostNotificationOrchestrator $postNotificationOrchestrator,
        private readonly PointService $pointService,
        private readonly StructuredLogger $logger,
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(Scene $scene, User $user, array $data, bool $isModerator, bool $requiresApproval): StorePostResult
    {
        $meta = [];
        $icQuote = trim((string) ($data['ic_quote'] ?? ''));

        if (($data['post_type'] ?? 'ooc') === 'ic' && $icQuote !== '') {
            $meta['ic_quote'] = $icQuote;
        }

        $isApproved = ! $requiresApproval;
        $post = null;
        $probeCreated = false;
        $inventoryAwardApplied = false;

        $this->db->transaction(function () use (
            $scene,
            $user,
            $data,
            $isModerator,
            $isApproved,
            $meta,
            &$post,
            &$probeCreated,
            &$inventoryAwardApplied,
        ): void {
            $post = Post::query()->create([
                'scene_id' => $scene->id,
                'user_id' => $user->id,
                'character_id' => $data['post_type'] === 'ic' ? $data['character_id'] : null,
                'post_type' => $data['post_type'],
                'content_format' => $data['content_format'],
                'content' => $data['content'],
                'meta' => $meta !== [] ? $meta : null,
                'moderation_status' => $isApproved ? 'approved' : 'pending',
                'approved_at' => $isApproved ? now() : null,
                'approved_by' => $isModerator && $isApproved ? $user->id : null,
            ]);

            $probeCreated = $this->postProbeService->createForPost(
                post: $post,
                data: $data,
                user: $user,
                scene: $scene,
                isModerator: $isModerator,
            );

            $inventoryAwardApplied = $this->postInventoryAwardService->applyForPost(
                post: $post,
                data: $data,
                scene: $scene,
                isModerator: $isModerator,
                user: $user,
            ) !== null;

            $this->ensureAuthorSubscription($post, $user);
            $this->pointService->syncApprovedPost($post);
        });

        if (! $post instanceof Post) {
            throw new \RuntimeException('Post creation failed unexpectedly.');
        }

        $notificationResult = $this->postNotificationOrchestrator->notifySceneParticipantsWithRetry($post, $user, 'store_post');
        $mentionRecipientCount = $this->postNotificationOrchestrator->notifyMentionsWithRetry($post, $user, 'store_post');

        $this->logger->info('post.created', [
            'user_id' => $user->id,
            'scene_id' => $scene->id,
            'post_id' => $post->id,
            'is_moderator' => $isModerator,
            'requires_approval' => $requiresApproval,
            'moderation_status' => $post->moderation_status,
            'probe_created' => $probeCreated,
            'inventory_award_applied' => $inventoryAwardApplied,
            'notification_recipients' => $notificationResult['in_app_recipients'],
            'webpush_recipients' => $notificationResult['webpush_recipients'],
            'mention_recipients' => $mentionRecipientCount,
        ]);

        return new StorePostResult(
            post: $post,
            probeCreated: $probeCreated,
            inventoryAwardApplied: $inventoryAwardApplied,
        );
    }

    private function ensureAuthorSubscription(Post $post, User $author): void
    {
        SceneSubscription::query()->firstOrCreate([
            'scene_id' => $post->scene_id,
            'user_id' => $author->id,
        ], [
            'is_muted' => false,
            'last_read_post_id' => $post->id,
            'last_read_at' => now(),
        ]);
    }
}
