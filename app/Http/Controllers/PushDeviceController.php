<?php

namespace App\Http\Controllers;

use App\Actions\Notification\DeleteAllWebPushSubscriptionsAction;
use App\Actions\Notification\DeleteOwnedWebPushSubscriptionAction;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PushDeviceController extends Controller
{
    public function __construct(
        private readonly DeleteOwnedWebPushSubscriptionAction $deleteOwnedWebPushSubscriptionAction,
        private readonly DeleteAllWebPushSubscriptionsAction $deleteAllWebPushSubscriptionsAction,
    ) {}

    public function destroy(Request $request, PushSubscription $pushSubscription): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $this->authorize('delete', $pushSubscription);

        $this->deleteOwnedWebPushSubscriptionAction->execute($user, $pushSubscription);

        return redirect()
            ->route('notifications.preferences')
            ->with('status', 'Push-Gerät entfernt.');
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $this->deleteAllWebPushSubscriptionsAction->execute($user);

        return redirect()
            ->route('notifications.preferences')
            ->with('status', 'Alle Push-Geräte entfernt.');
    }
}
