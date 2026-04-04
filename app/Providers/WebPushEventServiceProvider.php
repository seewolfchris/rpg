<?php

namespace App\Providers;

use App\Support\Observability\DomainEventLogger;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use NotificationChannels\WebPush\Events\NotificationFailed;

class WebPushEventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(NotificationFailed::class, function (NotificationFailed $event): void {
            $statusCode = $event->report->getResponse()?->getStatusCode();

            if (in_array($statusCode, [404, 410], true)) {
                $event->subscription->delete();
            }

            app(DomainEventLogger::class)->info('webpush.delivery_failed', [
                'recipient_user_id' => data_get($event->subscription, 'user_id'),
                'user_id' => data_get($event->subscription, 'user_id'),
                'actor_type' => 'system',
                'world_id' => data_get($event->subscription, 'world_id'),
                'world_slug' => data_get($event->subscription, 'world.slug', 'unknown'),
                'endpoint_hash' => sha1((string) $event->subscription->endpoint),
                'target_type' => 'push_endpoint',
                'target_id' => sha1((string) $event->subscription->endpoint),
                'status_code' => $statusCode,
                'reason' => $event->report->getReason(),
                'expired' => $event->report->isSubscriptionExpired(),
                'outcome' => 'failed',
            ]);
        });
    }
}
