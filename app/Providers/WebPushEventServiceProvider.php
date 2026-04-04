<?php

namespace App\Providers;

use App\Models\World;
use App\Support\Observability\DomainEventLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use NotificationChannels\WebPush\Events\NotificationFailed;

class WebPushEventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(NotificationFailed::class, function (NotificationFailed $event): void {
            $statusCode = $event->report->getResponse()?->getStatusCode();
            $subscription = $event->subscription;

            if (in_array($statusCode, [404, 410], true)) {
                $subscription->delete();
            }

            app(DomainEventLogger::class)->info('webpush.delivery_failed', [
                'recipient_user_id' => data_get($subscription, 'user_id'),
                'user_id' => data_get($subscription, 'user_id'),
                'actor_type' => 'system',
                'world_id' => data_get($subscription, 'world_id'),
                'world_slug' => $this->resolveLoadedWorldSlug($subscription),
                'endpoint_hash' => sha1((string) $subscription->endpoint),
                'target_type' => 'push_endpoint',
                'target_id' => sha1((string) $subscription->endpoint),
                'status_code' => $statusCode,
                'reason' => $event->report->getReason(),
                'expired' => $event->report->isSubscriptionExpired(),
                'outcome' => 'failed',
            ]);
        });
    }

    private function resolveLoadedWorldSlug(mixed $subscription): string
    {
        if (! $subscription instanceof Model) {
            return 'unknown';
        }

        if (! $subscription->relationLoaded('world')) {
            return 'unknown';
        }

        $world = $subscription->getRelation('world');

        return $world instanceof World ? (string) $world->slug : 'unknown';
    }
}
