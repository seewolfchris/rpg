<?php

declare(strict_types=1);

namespace App\Support\Observability;

use Illuminate\Http\Request;

class DomainEventLogger
{
    public function __construct(
        private readonly StructuredLogger $structuredLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $event, array $context = []): void
    {
        $normalized = array_merge(
            $this->defaultContext($event, $context),
            $context,
        );

        $this->structuredLogger->info($event, $normalized);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function defaultContext(string $event, array $context): array
    {
        [$targetType, $targetId] = $this->resolveTarget($context);

        return [
            'event' => $event,
            'event_version' => 1,
            'occurred_at' => now()->toIso8601String(),
            'request_id' => $this->resolveRequestId($context),
            'world_slug' => (string) ($context['world_slug'] ?? 'unknown'),
            'actor_user_id' => $this->resolveActorUserId($context),
            'target_type' => $targetType,
            'target_id' => $targetId,
            'outcome' => $this->resolveOutcome($context),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolveRequestId(array $context): string
    {
        $contextRequestId = trim((string) ($context['request_id'] ?? ''));
        if ($contextRequestId !== '') {
            return $contextRequestId;
        }

        if (! app()->bound('request')) {
            return 'unknown';
        }

        $request = app('request');
        if (! $request instanceof Request) {
            return 'unknown';
        }

        $attributeValue = trim((string) $request->attributes->get('request_id', ''));
        if ($attributeValue !== '') {
            return $attributeValue;
        }

        $headerValue = trim((string) $request->headers->get('X-Request-Id', ''));

        return $headerValue !== '' ? $headerValue : 'unknown';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolveActorUserId(array $context): int|string
    {
        foreach (['actor_user_id', 'user_id', 'author_id', 'moderator_id', 'recipient_id'] as $key) {
            if (! array_key_exists($key, $context)) {
                continue;
            }

            $value = $context[$key];

            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return 'unknown';
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{0: string, 1: int|string}
     */
    private function resolveTarget(array $context): array
    {
        if (isset($context['target_type'])) {
            $targetType = (string) $context['target_type'];
            $targetId = $context['target_id'] ?? 'unknown';

            return [$targetType !== '' ? $targetType : 'unknown', is_numeric($targetId) ? (int) $targetId : (string) $targetId];
        }

        $targetMap = [
            'post_id' => 'post',
            'scene_id' => 'scene',
            'character_id' => 'character',
            'campaign_id' => 'campaign',
            'world_id' => 'world',
            'endpoint_hash' => 'push_endpoint',
        ];

        foreach ($targetMap as $idKey => $targetType) {
            if (! array_key_exists($idKey, $context)) {
                continue;
            }

            $candidate = $context[$idKey];

            return [$targetType, is_numeric($candidate) ? (int) $candidate : (string) $candidate];
        }

        return ['unknown', 'unknown'];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolveOutcome(array $context): string
    {
        $explicit = trim((string) ($context['outcome'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        if (array_key_exists('error', $context) || array_key_exists('dispatch_error', $context)) {
            return 'failed';
        }

        $reason = trim((string) ($context['reason'] ?? ''));
        if ($reason !== '') {
            return 'skipped';
        }

        return 'succeeded';
    }
}
