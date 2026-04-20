<?php

namespace App\Domain\Post;

use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;
use App\Support\Gamification\PointService;
use App\Support\Observability\DomainEventLogger;
use Illuminate\Database\DatabaseManager;

class StorePostService
{
    public function __construct(
        private readonly PostProbeService $postProbeService,
        private readonly PostInventoryAwardService $postInventoryAwardService,
        private readonly PostNotificationOrchestrator $postNotificationOrchestrator,
        private readonly PointService $pointService,
        private readonly DomainEventLogger $logger,
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(
        Scene $scene,
        User $user,
        array $data,
        bool $isModerator,
        bool $requiresApproval,
        ?string $worldSlug = null,
    ): StorePostResult
    {
        $normalizedPayload = $this->normalizePostPayload($data);
        $transactionResult = $this->executeStoreTransaction(
            scene: $scene,
            user: $user,
            data: $data,
            isModerator: $isModerator,
            requiresApproval: $requiresApproval,
            normalizedPayload: $normalizedPayload,
        );

        $notificationMetrics = $this->dispatchPostCreatedNotifications(
            post: $transactionResult['post'],
            author: $user,
        );

        $this->logPostCreated(
            post: $transactionResult['post'],
            user: $user,
            scene: $scene,
            isModerator: $isModerator,
            requiresApproval: $requiresApproval,
            worldSlug: $worldSlug,
            probeCreated: $transactionResult['probeCreated'],
            inventoryAwardApplied: $transactionResult['inventoryAwardApplied'],
            notificationInAppRecipients: $notificationMetrics['in_app_recipients'],
            notificationWebpushRecipients: $notificationMetrics['webpush_recipients'],
            mentionRecipients: $notificationMetrics['mention_recipients'],
        );

        return new StorePostResult(
            post: $transactionResult['post'],
            probeCreated: $transactionResult['probeCreated'],
            inventoryAwardApplied: $transactionResult['inventoryAwardApplied'],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *   postType: string,
     *   characterId: int|null,
     *   meta: array<string, mixed>|null
     * }
     */
    private function normalizePostPayload(array $data): array
    {
        $postType = (string) ($data['post_type'] ?? 'ooc');
        $postMode = $postType === 'ic'
            ? (string) ($data['post_mode'] ?? 'character')
            : 'character';
        $characterId = null;

        if ($postType === 'ic' && $postMode === 'character') {
            $rawCharacterId = $data['character_id'] ?? null;
            $characterId = $rawCharacterId !== null
                ? (int) $rawCharacterId
                : null;
        }

        $meta = [];
        $icQuote = trim((string) ($data['ic_quote'] ?? ''));

        if ($postType === 'ic' && $icQuote !== '') {
            $meta['ic_quote'] = $icQuote;
        }

        if ($postType === 'ic' && $postMode === 'gm') {
            $meta['author_role'] = 'gm';
        }

        return [
            'postType' => $postType,
            'characterId' => $characterId,
            'meta' => $meta !== [] ? $meta : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{
     *   postType: string,
     *   characterId: int|null,
     *   meta: array<string, mixed>|null
     * }  $normalizedPayload
     * @return array{
     *   post: Post,
     *   probeCreated: bool,
     *   inventoryAwardApplied: bool
     * }
     */
    private function executeStoreTransaction(
        Scene $scene,
        User $user,
        array $data,
        bool $isModerator,
        bool $requiresApproval,
        array $normalizedPayload,
    ): array {
        /** @var array{post: Post, probeCreated: bool, inventoryAwardApplied: bool} $result */
        $result = $this->db->transaction(function () use ($scene, $user, $data, $isModerator, $requiresApproval, $normalizedPayload): array {
            $post = $this->persistPost(
                scene: $scene,
                user: $user,
                data: $data,
                isModerator: $isModerator,
                requiresApproval: $requiresApproval,
                normalizedPayload: $normalizedPayload,
            );
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

            $this->applyInTransactionPostSideEffects($post, $user);

            return [
                'post' => $post,
                'probeCreated' => $probeCreated,
                'inventoryAwardApplied' => $inventoryAwardApplied,
            ];
        });

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{
     *   postType: string,
     *   characterId: int|null,
     *   meta: array<string, mixed>|null
     * }  $normalizedPayload
     */
    private function persistPost(
        Scene $scene,
        User $user,
        array $data,
        bool $isModerator,
        bool $requiresApproval,
        array $normalizedPayload,
    ): Post {
        $isApproved = ! $requiresApproval;

        /** @var Post $post */
        $post = Post::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'character_id' => $normalizedPayload['characterId'],
            'post_type' => $normalizedPayload['postType'],
            'content_format' => $data['content_format'],
            'content' => $data['content'],
            'meta' => $normalizedPayload['meta'],
            'moderation_status' => $isApproved ? 'approved' : 'pending',
            'approved_at' => $isApproved ? now() : null,
            'approved_by' => $isModerator && $isApproved ? $user->id : null,
        ]);

        return $post;
    }

    private function applyInTransactionPostSideEffects(Post $post, User $user): void
    {
        $this->ensureAuthorSubscription($post, $user);
        $this->pointService->syncApprovedPost($post);
    }

    /**
     * @return array{
     *   in_app_recipients: int,
     *   webpush_recipients: int,
     *   mention_recipients: int
     * }
     */
    private function dispatchPostCreatedNotifications(Post $post, User $author): array
    {
        $sceneNotificationResult = $this->postNotificationOrchestrator->notifySceneParticipantsWithRetry($post, $author, 'store_post');
        $mentionRecipientCount = $this->postNotificationOrchestrator->notifyMentionsWithRetry($post, $author, 'store_post');

        return [
            'in_app_recipients' => (int) $sceneNotificationResult['in_app_recipients'],
            'webpush_recipients' => (int) $sceneNotificationResult['webpush_recipients'],
            'mention_recipients' => $mentionRecipientCount,
        ];
    }

    private function logPostCreated(
        Post $post,
        User $user,
        Scene $scene,
        bool $isModerator,
        bool $requiresApproval,
        ?string $worldSlug,
        bool $probeCreated,
        bool $inventoryAwardApplied,
        int $notificationInAppRecipients,
        int $notificationWebpushRecipients,
        int $mentionRecipients,
    ): void {
        $resolvedWorldSlug = $worldSlug !== null && trim($worldSlug) !== ''
            ? trim($worldSlug)
            : 'unknown';

        $this->logger->info('post.created', [
            'world_slug' => $resolvedWorldSlug,
            'actor_user_id' => $user->id,
            'user_id' => $user->id,
            'scene_id' => $scene->id,
            'post_id' => $post->id,
            'is_moderator' => $isModerator,
            'requires_approval' => $requiresApproval,
            'moderation_status' => $post->moderation_status,
            'probe_created' => $probeCreated,
            'inventory_award_applied' => $inventoryAwardApplied,
            'notification_recipients' => $notificationInAppRecipients,
            'webpush_recipients' => $notificationWebpushRecipients,
            'mention_recipients' => $mentionRecipients,
            'outcome' => 'succeeded',
        ]);
    }

    private function ensureAuthorSubscription(Post $post, User $author): void
    {
        $subscription = SceneSubscription::query()->firstOrCreate([
            'scene_id' => $post->scene_id,
            'user_id' => $author->id,
        ], [
            'is_muted' => false,
            'last_read_post_id' => $post->id,
            'last_read_at' => now(),
        ]);

        if ($subscription->wasRecentlyCreated) {
            return;
        }

        $nextLastReadPostId = max((int) ($subscription->last_read_post_id ?? 0), (int) $post->id);

        $subscription->last_read_post_id = $nextLastReadPostId;
        $subscription->last_read_at = now();
        $subscription->save();
    }
}
