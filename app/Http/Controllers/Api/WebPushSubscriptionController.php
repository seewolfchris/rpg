<?php

namespace App\Http\Controllers\Api;

use App\Actions\Notification\DeleteWebPushSubscriptionAction;
use App\Actions\Notification\UpsertWebPushSubscriptionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\WebPush\DestroyWebPushSubscriptionRequest;
use App\Http\Requests\WebPush\StoreWebPushSubscriptionRequest;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class WebPushSubscriptionController extends Controller
{
    public function __construct(
        private readonly UpsertWebPushSubscriptionAction $upsertWebPushSubscriptionAction,
        private readonly DeleteWebPushSubscriptionAction $deleteWebPushSubscriptionAction,
    ) {}

    public function subscribe(StoreWebPushSubscriptionRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $world = $request->world();
        $this->authorize('create', [PushSubscription::class, $world]);

        $subscription = $this->upsertWebPushSubscriptionAction->execute(
            user: $user,
            world: $world,
            endpoint: $request->endpoint(),
            publicKey: $request->publicKey(),
            authToken: $request->authToken(),
            contentEncoding: $request->contentEncoding(),
        );

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
        $deleted = $this->deleteWebPushSubscriptionAction->execute(
            user: $user,
            world: $world,
            endpoint: $request->endpoint(),
        );

        return response()->json([
            'status' => 'ok',
            'deleted' => $deleted,
        ]);
    }
}
