<?php

namespace App\Domain\Shared\Outbox;

use App\Support\Observability\StructuredLogger;
use Throwable;

class OutboxCandidateRecorder
{
    public function __construct(
        private readonly StructuredLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(string $stream, string $eventKey, array $payload, Throwable $throwable): void
    {
        if (! (bool) config('outbox.spike_log_candidates', false)) {
            return;
        }

        $maxPayloadBytes = max(256, (int) config('outbox.max_payload_bytes', 4096));
        $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $payloadJson = json_encode($payload, $jsonFlags);

        if (! is_string($payloadJson)) {
            $payloadJson = '{}';
        }

        $payloadTruncated = false;
        if (strlen($payloadJson) > $maxPayloadBytes) {
            $payloadJson = substr($payloadJson, 0, $maxPayloadBytes);
            $payloadTruncated = true;
        }

        $this->logger->info('outbox.candidate', [
            'stream' => $stream,
            'event_key' => $eventKey,
            'payload_json' => $payloadJson,
            'payload_truncated' => $payloadTruncated,
            'error_class' => $throwable::class,
            'error_message' => $throwable->getMessage(),
        ]);
    }
}
