<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebPush\DestroyWebPushSubscriptionRequest;
use App\Http\Requests\WebPush\StoreWebPushSubscriptionRequest;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\Observability\DomainEventLogger;
use Illuminate\Http\JsonResponse;

class WebPushSubscriptionController extends Controller
{
    public function __construct(
        private readonly DomainEventLogger $logger,
    ) {}

    public function subscribe(StoreWebPushSubscriptionRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $world = $request->world();
        $this->authorize('create', [PushSubscription::class, $world]);

        $subscription = $user->updatePushSubscription(
            endpoint: $request->endpoint(),
            key: $request->publicKey(),
            token: $request->authToken(),
            contentEncoding: $request->contentEncoding(),
        );
        abort_unless($subscription instanceof PushSubscription, 500);

        $subscription->user_id = $user->id;
        $subscription->world_id = $world->id;
        $subscription->save();

        $this->logger->info('webpush.subscription_upserted', [
            'user_id' => $user->id,
            'world_id' => $world->id,
            'world_slug' => $world->slug,
            'endpoint_hash' => sha1($request->endpoint()),
            'target_type' => 'push_endpoint',
            'target_id' => sha1($request->endpoint()),
            'outcome' => 'succeeded',
        ]);

        return response()->json([
            'status' => 'ok',
            'world_slug' => $world->slug,
        ]);
    }

    public function unsubscribe(DestroyWebPushSubscriptionRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $world = $request->world();

        $subscription = PushSubscription::query()
            ->forUser($user)
            ->forWorld($world)
            ->where('endpoint', $request->endpoint())
            ->first();

        $deleted = false;

        if ($subscription) {
            $this->authorize('delete', $subscription);
            $subscription->delete();
            $deleted = true;
        }

        $this->logger->info('webpush.subscription_deleted', [
            'user_id' => $user->id,
            'world_id' => $world->id,
            'world_slug' => $world->slug,
            'endpoint_hash' => sha1($request->endpoint()),
            'target_type' => 'push_endpoint',
            'target_id' => sha1($request->endpoint()),
            'deleted' => $deleted,
            'outcome' => $deleted ? 'succeeded' : 'skipped',
        ]);

        return response()->json([
            'status' => 'ok',
            'deleted' => $deleted,
        ]);
    }
}
