<?php

namespace App\Http\Controllers;

use App\Http\Requests\Notification\UpdateNotificationPreferencesRequest;
use App\Models\SceneSubscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

    public function read(Request $request, string $notificationId): View|RedirectResponse
    {
        $user = $request->user();
        $notification = $user
            ->notifications()
            ->whereKey($notificationId)
            ->firstOrFail();

        if (! $notification->read_at) {
            $notification->markAsRead();
        }

        if ($request->header('HX-Request') === 'true') {
            return $this->renderInboxPanel($request, $user);
        }

        $fallbackUrl = route('notifications.index');
        $actionUrl = data_get($notification->data, 'action_url');
        $resolvedUrl = $this->resolveSafeActionUrl($request, $actionUrl, $fallbackUrl);

        return redirect()->to($resolvedUrl);
    }

    private function resolveSafeActionUrl(Request $request, mixed $actionUrl, string $fallbackUrl): string
    {
        if (! is_string($actionUrl) || trim($actionUrl) === '') {
            return $fallbackUrl;
        }

        $candidate = trim($actionUrl);
        // Block protocol-relative targets such as //evil.example.
        if (Str::startsWith($candidate, '//')) {
            return $fallbackUrl;
        }

        if (Str::startsWith($candidate, ['/'])) {
            return $candidate;
        }

        $parsed = parse_url($candidate);
        if (! is_array($parsed)) {
            return $fallbackUrl;
        }

        $host = strtolower((string) ($parsed['host'] ?? ''));
        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        $port = isset($parsed['port']) ? (int) $parsed['port'] : null;

        if ($host === '' || ! in_array($scheme, ['http', 'https'], true)) {
            return $fallbackUrl;
        }

        $requestHost = strtolower($request->getHost());
        $requestScheme = strtolower($request->getScheme());
        $requestPort = $request->getPort();

        if ($host !== $requestHost || $scheme !== $requestScheme) {
            return $fallbackUrl;
        }

        if ($port !== null && $port !== $requestPort) {
            return $fallbackUrl;
        }

        return $candidate;
    }

    public function readAll(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        $user
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        if ($request->header('HX-Request') === 'true') {
            return $this->renderInboxPanel($request, $user);
        }

        return back()->with('status', 'Alle Benachrichtigungen als gelesen markiert.');
    }

    private function renderInboxPanel(Request $request, \App\Models\User $user): View
    {
        $notifications = $user->notifications()
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $unreadCount = $user->unreadNotifications()->count();

        return view('notifications.partials.inbox', compact('notifications', 'unreadCount'));
    }
}
