<?php

namespace Tests\Unit\Observability;

use App\Support\Observability\DomainEventLogger;
use App\Support\Observability\StructuredLogger;
use Tests\TestCase;

class DomainEventLoggerTest extends TestCase
{
    public function test_it_adds_required_domain_event_fields_and_keeps_context_values(): void
    {
        $structuredLogger = $this->createMock(StructuredLogger::class);
        $structuredLogger->expects($this->once())
            ->method('info')
            ->with(
                'webpush.subscription_upserted',
                $this->callback(function (array $context): bool {
                    $requiredKeys = [
                        'event',
                        'event_version',
                        'occurred_at',
                        'request_id',
                        'world_slug',
                        'actor_user_id',
                        'target_type',
                        'target_id',
                        'outcome',
                    ];

                    foreach ($requiredKeys as $key) {
                        $this->assertArrayHasKey($key, $context);
                    }

                    $this->assertSame('webpush.subscription_upserted', $context['event']);
                    $this->assertSame(1, $context['event_version']);
                    $this->assertSame('chroniken-der-asche', $context['world_slug']);
                    $this->assertSame(42, $context['actor_user_id']);
                    $this->assertSame('push_endpoint', $context['target_type']);
                    $this->assertSame('abc123', $context['target_id']);
                    $this->assertSame('succeeded', $context['outcome']);

                    return true;
                }),
            );

        $logger = new DomainEventLogger($structuredLogger);
        $logger->info('webpush.subscription_upserted', [
            'world_slug' => 'chroniken-der-asche',
            'user_id' => 42,
            'endpoint_hash' => 'abc123',
            'outcome' => 'succeeded',
        ]);
    }

    public function test_it_infers_actor_target_and_failure_outcome_for_error_context(): void
    {
        $structuredLogger = $this->createMock(StructuredLogger::class);
        $structuredLogger->expects($this->once())
            ->method('info')
            ->with(
                'post.scene_notifications_failed',
                $this->callback(function (array $context): bool {
                    $this->assertSame(7, $context['actor_user_id']);
                    $this->assertSame('post', $context['target_type']);
                    $this->assertSame(88, $context['target_id']);
                    $this->assertSame('failed', $context['outcome']);
                    $this->assertSame('unknown', $context['world_slug']);

                    return true;
                }),
            );

        $logger = new DomainEventLogger($structuredLogger);
        $logger->info('post.scene_notifications_failed', [
            'user_id' => 7,
            'post_id' => 88,
            'error' => 'dispatch failed',
        ]);
    }
}
