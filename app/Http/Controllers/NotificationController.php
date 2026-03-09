<?php

namespace App\Http\Controllers;

use App\Http\Requests\Notification\UpdateNotificationPreferencesRequest;
use App\Models\SceneSubscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function preferences(Request $request): View
    {
        $preferences = $request->user()->resolvedNotificationPreferences();

        return view('notifications.preferences', compact('preferences'));
    }

    public function updatePreferences(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->notification_preferences = $request->preferences();
        $user->save();

        return redirect()
            ->route('notifications.preferences')
            ->with('status', 'Benachrichtigungspräferenzen gespeichert.');
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $unreadCount = $user->unreadNotifications()->count();
        $subscriptions = SceneSubscription::query()
            ->where('user_id', $user->id)
            ->whereHas('scene.campaign', fn (Builder $campaignQuery) => $campaignQuery->visibleTo($user))
            ->with(['scene.campaign.world'])
            ->latest('updated_at')
            ->get();

        $activeSubscriptionCount = $subscriptions->where('is_muted', false)->count();
        $mutedSubscriptionCount = $subscriptions->where('is_muted', true)->count();

        return view('notifications.index', compact(
            'notifications',
            'unreadCount',
            'subscriptions',
            'activeSubscriptionCount',
            'mutedSubscriptionCount',
        ));
    }

    public function read(Request $request, string $notificationId): RedirectResponse
    {
        $notification = $request->user()
            ->notifications()
            ->whereKey($notificationId)
            ->firstOrFail();

        if (! $notification->read_at) {
            $notification->markAsRead();
        }

        $actionUrl = data_get($notification->data, 'action_url', route('notifications.index'));

        return redirect()->to($actionUrl);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        return back()->with('status', 'Alle Benachrichtigungen als gelesen markiert.');
    }

    public function poll(Request $request): JsonResponse
    {
        $user = $request->user();
        $preferences = $user->resolvedNotificationPreferences();

        $browserEnabledKinds = collect($preferences)
            ->filter(static fn (array $channels): bool => (bool) ($channels['browser'] ?? false))
            ->keys()
            ->values();

        if ($browserEnabledKinds->isEmpty()) {
            return response()->json([
                'browser_enabled_kinds' => [],
                'unread_count' => 0,
                'notifications' => [],
            ]);
        }

        $unreadCount = (int) $user->unreadNotifications()->count();

        $notifications = $user->unreadNotifications()
            ->latest()
            ->limit(20)
            ->get()
            ->filter(fn ($notification): bool => $browserEnabledKinds->contains((string) data_get($notification->data, 'kind', '')))
            ->sortBy('created_at')
            ->take(8)
            ->values()
            ->map(function ($notification): array {
                return [
                    'id' => (string) $notification->id,
                    'kind' => (string) data_get($notification->data, 'kind', ''),
                    'title' => (string) data_get($notification->data, 'title', 'Benachrichtigung'),
                    'message' => (string) data_get($notification->data, 'message', 'Neue Aktivität.'),
                    'action_url' => (string) data_get($notification->data, 'action_url', route('notifications.index')),
                    'created_at' => optional($notification->created_at)?->toIso8601String(),
                ];
            })
            ->all();

        return response()->json([
            'browser_enabled_kinds' => $browserEnabledKinds->all(),
            'unread_count' => $unreadCount,
            'notifications' => $notifications,
        ]);
    }
}
