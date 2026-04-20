<?php

namespace App\Http\Controllers;

use App\Actions\Notification\MarkAllNotificationsReadAction;
use App\Actions\Notification\MarkNotificationReadAction;
use App\Actions\Notification\UpdateNotificationPreferencesAction;
use App\Http\Controllers\Concerns\BuildsVisibleCampaignSubquery;
use App\Http\Requests\Notification\UpdateNotificationPreferencesRequest;
use App\Models\SceneSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NotificationController extends Controller
{
    use BuildsVisibleCampaignSubquery;

    public function __construct(
        private readonly UpdateNotificationPreferencesAction $updateNotificationPreferencesAction,
        private readonly MarkNotificationReadAction $markNotificationReadAction,
        private readonly MarkAllNotificationsReadAction $markAllNotificationsReadAction,
    ) {}

    public function preferences(Request $request): View
    {
        $user = $this->authenticatedUser($request);
        $preferences = $user->resolvedNotificationPreferences();
        $offlineQueueEnabled = $user->offlineQueueEnabled();

        return view('notifications.preferences', compact('preferences', 'offlineQueueEnabled'));
    }

    public function updatePreferences(UpdateNotificationPreferencesRequest $request): RedirectResponse|JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $offlineQueueEnabled = $request->offlineQueueEnabled();

        $this->updateNotificationPreferencesAction->execute(
            $user,
            $request->preferences(),
            $offlineQueueEnabled,
        );

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'ok',
                'message' => 'Benachrichtigungspräferenzen gespeichert.',
                'offline_queue_enabled' => $offlineQueueEnabled,
            ]);
        }

        return redirect()
            ->route('notifications.preferences')
            ->with('status', 'Benachrichtigungspräferenzen gespeichert.');
    }

    public function index(Request $request): View
    {
        $user = $this->authenticatedUser($request);

        $notifications = $user->notifications()
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $unreadCount = $user->unreadNotifications()->count();
        $subscriptionsBaseQuery = SceneSubscription::query()
            ->where('user_id', $user->id)
            ->whereHas('scene', function (Builder $sceneQuery) use ($user): void {
                $sceneQuery->whereIn('campaign_id', $this->visibleCampaignIdsSubquery($user));
            });

        $subscriptions = (clone $subscriptionsBaseQuery)
            ->with(['scene.campaign.world'])
            ->latest('updated_at')
            ->paginate(20, ['*'], 'subscriptions_page')
            ->withQueryString();

        $subscriptionCounts = (array) ((clone $subscriptionsBaseQuery)
            ->toBase()
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('SUM(CASE WHEN is_muted = 0 THEN 1 ELSE 0 END) as active_count')
            ->selectRaw('SUM(CASE WHEN is_muted = 1 THEN 1 ELSE 0 END) as muted_count')
            ->first() ?? []);
        $activeSubscriptionCount = (int) ($subscriptionCounts['active_count'] ?? 0);
        $mutedSubscriptionCount = (int) ($subscriptionCounts['muted_count'] ?? 0);

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
        $user = $this->authenticatedUser($request);
        $notification = $this->markNotificationReadAction->execute($user, $notificationId);

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
        $user = $this->authenticatedUser($request);
        $this->markAllNotificationsReadAction->execute($user);

        if ($request->header('HX-Request') === 'true') {
            return $this->renderInboxPanel($request, $user);
        }

        return back()->with('status', 'Alle Benachrichtigungen als gelesen markiert.');
    }

    private function renderInboxPanel(Request $request, User $user): View
    {
        $notifications = $user->notifications()
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $unreadCount = $user->unreadNotifications()->count();

        return view('notifications.partials.inbox', compact('notifications', 'unreadCount'));
    }
}
