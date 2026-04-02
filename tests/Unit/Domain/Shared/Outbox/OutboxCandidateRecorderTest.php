<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\Outbox;

use App\Domain\Shared\Outbox\OutboxCandidateRecorder;
use App\Support\Observability\StructuredLogger;
use RuntimeException;
use Tests\TestCase;

class OutboxCandidateRecorderTest extends TestCase
{
    public function test_it_skips_logging_when_spike_flag_is_disabled(): void
    {
        config([
            'outbox.spike_log_candidates' => false,
        ]);

        $logger = $this->createMock(StructuredLogger::class);
        $logger->expects($this->never())->method('info');

        $recorder = new OutboxCandidateRecorder($logger);
        $recorder->record(
            stream: 'post.notifications',
            eventKey: 'scene_notifications_failed',
            payload: ['post_id' => 10],
            throwable: new RuntimeException('disabled'),
        );
    }

    public function test_it_logs_truncated_candidate_payload_when_enabled(): void
    {
        config([
            'outbox.spike_log_candidates' => true,
            'outbox.max_payload_bytes' => 256,
        ]);

        $largePayload = [
            'post_id' => 10,
            'details' => str_repeat('a', 1200),
        ];

        $logger = $this->createMock(StructuredLogger::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'outbox.candidate',
                $this->callback(static function (array $context): bool {
                    return ($context['stream'] ?? null) === 'post.notifications'
                        && ($context['event_key'] ?? null) === 'scene_notifications_failed'
                        && ($context['payload_truncated'] ?? null) === true
                        && ($context['error_class'] ?? null) === RuntimeException::class
                        && ($context['error_message'] ?? null) === 'forced'
                        && is_string($context['payload_json'] ?? null)
                        && strlen((string) $context['payload_json']) === 256;
                }),
            );

        $recorder = new OutboxCandidateRecorder($logger);
        $recorder->record(
            stream: 'post.notifications',
            eventKey: 'scene_notifications_failed',
            payload: $largePayload,
            throwable: new RuntimeException('forced'),
        );
    }
}
