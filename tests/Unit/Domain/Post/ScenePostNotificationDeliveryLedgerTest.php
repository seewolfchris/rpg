<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Post;

use App\Domain\Post\ScenePostNotificationDeliveryLedger;
use App\Models\Post;
use App\Models\PostSceneNotificationDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScenePostNotificationDeliveryLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_first_claim_creates_sending_delivery_and_initial_attempt_metadata(): void
    {
        Carbon::setTestNow('2026-05-11 12:00:00');

        [$post, $recipient] = $this->seedPostAndRecipient();

        $ledger = app(ScenePostNotificationDeliveryLedger::class);
        $claimed = $ledger->claim($post, $recipient, PostSceneNotificationDelivery::CHANNEL_DATABASE);

        $this->assertInstanceOf(PostSceneNotificationDelivery::class, $claimed);
        $this->assertSame(PostSceneNotificationDelivery::STATUS_SENDING, $claimed->status);
        $this->assertSame(1, (int) $claimed->attempt_count);
        $this->assertNotNull($claimed->first_attempted_at);
        $this->assertNotNull($claimed->last_attempted_at);
        $this->assertSame(now()->toDateTimeString(), $claimed->first_attempted_at?->toDateTimeString());
        $this->assertSame(now()->toDateTimeString(), $claimed->last_attempted_at?->toDateTimeString());
        $this->assertNull($claimed->sent_at);
        $this->assertNull($claimed->last_error);

        $this->assertDatabaseHas('post_scene_notification_deliveries', [
            'post_id' => $post->id,
            'recipient_user_id' => $recipient->id,
            'channel' => PostSceneNotificationDelivery::CHANNEL_DATABASE,
            'status' => PostSceneNotificationDelivery::STATUS_SENDING,
            'attempt_count' => 1,
        ]);
    }

    public function test_sent_delivery_is_not_claimed_again_and_attempt_count_stays_stable(): void
    {
        Carbon::setTestNow('2026-05-11 12:10:00');

        [$post, $recipient] = $this->seedPostAndRecipient();

        PostSceneNotificationDelivery::query()->create([
            'post_id' => $post->id,
            'recipient_user_id' => $recipient->id,
            'channel' => PostSceneNotificationDelivery::CHANNEL_DATABASE,
            'status' => PostSceneNotificationDelivery::STATUS_SENT,
            'attempt_count' => 3,
            'first_attempted_at' => now()->subMinutes(5),
            'last_attempted_at' => now()->subMinutes(1),
            'sent_at' => now()->subMinute(),
            'last_error' => null,
        ]);

        $ledger = app(ScenePostNotificationDeliveryLedger::class);
        $claimed = $ledger->claim($post, $recipient, PostSceneNotificationDelivery::CHANNEL_DATABASE);

        $this->assertNull($claimed);
        $this->assertDatabaseHas('post_scene_notification_deliveries', [
            'post_id' => $post->id,
            'recipient_user_id' => $recipient->id,
            'channel' => PostSceneNotificationDelivery::CHANNEL_DATABASE,
            'status' => PostSceneNotificationDelivery::STATUS_SENT,
            'attempt_count' => 3,
        ]);
    }

    public function test_failed_delivery_can_be_reclaimed_and_retried_with_incremented_attempt_count(): void
    {
        Carbon::setTestNow('2026-05-11 12:20:00');

        [$post, $recipient] = $this->seedPostAndRecipient();

        $firstAttempt = now()->subMinutes(15);
        PostSceneNotificationDelivery::query()->create([
            'post_id' => $post->id,
            'recipient_user_id' => $recipient->id,
            'channel' => PostSceneNotificationDelivery::CHANNEL_WEBPUSH,
            'status' => PostSceneNotificationDelivery::STATUS_FAILED,
            'attempt_count' => 1,
            'first_attempted_at' => $firstAttempt,
            'last_attempted_at' => $firstAttempt,
            'sent_at' => null,
            'last_error' => 'Initial failure',
        ]);

        $ledger = app(ScenePostNotificationDeliveryLedger::class);

        $claimed = $ledger->claim($post, $recipient, PostSceneNotificationDelivery::CHANNEL_WEBPUSH);

        $this->assertInstanceOf(PostSceneNotificationDelivery::class, $claimed);
        $this->assertSame(PostSceneNotificationDelivery::STATUS_SENDING, $claimed->status);
        $this->assertSame(2, (int) $claimed->attempt_count);
        $this->assertSame($firstAttempt->toDateTimeString(), $claimed->first_attempted_at?->toDateTimeString());
        $this->assertSame(now()->toDateTimeString(), $claimed->last_attempted_at?->toDateTimeString());

        $ledger->markFailed($claimed, '  '.str_repeat('x', 1005).'  ');

        $failedAgain = $claimed->fresh();

        $this->assertInstanceOf(PostSceneNotificationDelivery::class, $failedAgain);
        $this->assertSame(PostSceneNotificationDelivery::STATUS_FAILED, $failedAgain->status);
        $this->assertNotNull($failedAgain->last_error);
        $this->assertSame(1000, mb_strlen((string) $failedAgain->last_error));

        $reclaimed = $ledger->claim($post, $recipient, PostSceneNotificationDelivery::CHANNEL_WEBPUSH);

        $this->assertInstanceOf(PostSceneNotificationDelivery::class, $reclaimed);
        $this->assertSame(PostSceneNotificationDelivery::STATUS_SENDING, $reclaimed->status);
        $this->assertSame(3, (int) $reclaimed->attempt_count);
    }

    public function test_recent_sending_delivery_is_not_reclaimed(): void
    {
        Carbon::setTestNow('2026-05-11 12:30:00');

        [$post, $recipient] = $this->seedPostAndRecipient();

        $delivery = PostSceneNotificationDelivery::query()->create([
            'post_id' => $post->id,
            'recipient_user_id' => $recipient->id,
            'channel' => PostSceneNotificationDelivery::CHANNEL_DATABASE,
            'status' => PostSceneNotificationDelivery::STATUS_SENDING,
            'attempt_count' => 2,
            'first_attempted_at' => now()->subMinutes(10),
            'last_attempted_at' => now()->subSeconds(30),
            'sent_at' => null,
            'last_error' => 'Transient error before re-send',
        ]);
        PostSceneNotificationDelivery::query()
            ->whereKey((int) $delivery->id)
            ->update([
                'updated_at' => now()->subSeconds(120),
            ]);

        $ledger = app(ScenePostNotificationDeliveryLedger::class);
        $claimed = $ledger->claim($post, $recipient, PostSceneNotificationDelivery::CHANNEL_DATABASE);

        $this->assertNull($claimed);
        $this->assertDatabaseHas('post_scene_notification_deliveries', [
            'post_id' => $post->id,
            'recipient_user_id' => $recipient->id,
            'channel' => PostSceneNotificationDelivery::CHANNEL_DATABASE,
            'status' => PostSceneNotificationDelivery::STATUS_SENDING,
            'attempt_count' => 2,
        ]);
    }

    public function test_stale_sending_delivery_is_reclaimed_and_mark_sent_clears_last_error(): void
    {
        Carbon::setTestNow('2026-05-11 12:40:00');

        [$post, $recipient] = $this->seedPostAndRecipient();

        $delivery = PostSceneNotificationDelivery::query()->create([
            'post_id' => $post->id,
            'recipient_user_id' => $recipient->id,
            'channel' => PostSceneNotificationDelivery::CHANNEL_DATABASE,
            'status' => PostSceneNotificationDelivery::STATUS_SENDING,
            'attempt_count' => 4,
            'first_attempted_at' => now()->subHour(),
            'last_attempted_at' => now()->subMinutes(30),
            'sent_at' => null,
            'last_error' => 'Previous transient outage',
        ]);
        PostSceneNotificationDelivery::query()
            ->whereKey((int) $delivery->id)
            ->update([
                'updated_at' => now()->subSeconds(301),
            ]);

        $ledger = app(ScenePostNotificationDeliveryLedger::class);

        $reclaimed = $ledger->claim($post, $recipient, PostSceneNotificationDelivery::CHANNEL_DATABASE);

        $this->assertInstanceOf(PostSceneNotificationDelivery::class, $reclaimed);
        $this->assertSame(PostSceneNotificationDelivery::STATUS_SENDING, $reclaimed->status);
        $this->assertSame(5, (int) $reclaimed->attempt_count);

        $ledger->markSent($delivery);

        $sent = $delivery->fresh();

        $this->assertInstanceOf(PostSceneNotificationDelivery::class, $sent);
        $this->assertSame(PostSceneNotificationDelivery::STATUS_SENT, $sent->status);
        $this->assertNotNull($sent->sent_at);
        $this->assertNull($sent->last_error);

        $claimedAfterSent = $ledger->claim($post, $recipient, PostSceneNotificationDelivery::CHANNEL_DATABASE);

        $this->assertNull($claimedAfterSent);
    }

    public function test_repeated_claim_does_not_create_duplicate_ledger_rows_for_same_key(): void
    {
        Carbon::setTestNow('2026-05-11 12:50:00');

        [$post, $recipient] = $this->seedPostAndRecipient();

        $ledger = app(ScenePostNotificationDeliveryLedger::class);

        $firstClaim = $ledger->claim($post, $recipient, PostSceneNotificationDelivery::CHANNEL_DATABASE);
        $secondClaim = $ledger->claim($post, $recipient, PostSceneNotificationDelivery::CHANNEL_DATABASE);

        $this->assertInstanceOf(PostSceneNotificationDelivery::class, $firstClaim);
        $this->assertNull($secondClaim);
        $this->assertSame(1, PostSceneNotificationDelivery::query()
            ->where('post_id', $post->id)
            ->where('recipient_user_id', $recipient->id)
            ->where('channel', PostSceneNotificationDelivery::CHANNEL_DATABASE)
            ->count());
    }

    /**
     * @return array{0: Post, 1: User}
     */
    private function seedPostAndRecipient(): array
    {
        $post = Post::factory()->create([
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'Ledger characterization test post',
            'moderation_status' => 'approved',
            'approved_at' => now(),
        ]);

        $recipient = User::factory()->create();

        return [$post, $recipient];
    }
}
