<?php

namespace App\Http\Controllers;

use App\Actions\Notification\MarkAllNotificationsReadAction;
use App\Actions\Notification\MarkNotificationReadAction;
use App\Actions\Notification\UpdateNotificationPreferencesAction;
use App\Http\Controllers\Concerns\BuildsVisibleCampaignSubquery;
use App\Http\Requests\Notification\UpdateNotificationPreferencesRequest;
use App\Models\SceneSubscription;
use App\Support\Navigation\SafeReturnUrl;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    use BuildsVisibleCampaignSubquery;

    public function __construct(
        private readonly UpdateNotificationPreferencesAction $updateNotificationPreferencesAction,
        private readonly MarkNotificationReadAction $markNotificationReadAction,
        private readonly MarkAllNotificationsReadAction $markAllNotificationsReadAction,
        private readonly SafeReturnUrl $safeReturnUrl,
    ) {}

    public function preferences(Request $request): View
    {
        $user = $this->authenticatedUser($request);
        $preferences = $user->resolvedNotificationPreferences();
        $offlineQueueEnabled = $user->offlineQueueEnabled();
        $backUrl = $this->safeReturnUrl->resolve($request, route('notifications.index'));
        $returnTo = $this->safeReturnUrl->carry($request);

        return view('notifications.preferences', compact('preferences', 'offlineQueueEnabled', 'backUrl', 'returnTo'));
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

        $parameters = [];
        $returnTo = $this->safeReturnUrl->carry($request);
        if (is_string($returnTo) && $returnTo !== '') {
            $parameters['return_to'] = $returnTo;
        }

        return redirect()
            ->route('notifications.preferences', $parameters)
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
        $returnTo = $this->notificationReturnTo($request);

        return view('notifications.index', compact(
            'notifications',
            'unreadCount',
            'subscriptions',
            'activeSubscriptionCount',
            'mutedSubscriptionCount',
            'returnTo',
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
        $resolvedUrl = $this->safeReturnUrl->sanitizeCandidate(is_string($actionUrl) ? $actionUrl : null, $request) ?? $fallbackUrl;
        $returnTo = $this->safeReturnUrl->carry($request);
        if (is_string($returnTo) && $returnTo !== '') {
            $resolvedUrl = $this->appendQueryParameter($resolvedUrl, 'return_to', $returnTo);
        }

        return redirect()->to($resolvedUrl);
    }

    public function readAll(Request $request): View|RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $this->markAllNotificationsReadAction->execute($user);

        if ($request->header('HX-Request') === 'true') {
            return $this->renderInboxPanel($request, $user);
        }

        $backUrl = $this->safeReturnUrl->resolve($request, route('notifications.index'));

        return redirect()
            ->to($backUrl)
            ->with('status', 'Alle Benachrichtigungen als gelesen markiert.');
    }

    private function renderInboxPanel(Request $request, User $user): View
    {
        $notifications = $user->notifications()
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $unreadCount = $user->unreadNotifications()->count();
        $returnTo = $this->notificationReturnTo($request, false);

        return view('notifications.partials.inbox', compact('notifications', 'unreadCount', 'returnTo'));
    }

    private function notificationReturnTo(Request $request, bool $allowCurrentUri = true): string
    {
        $explicitReturnTo = $this->safeReturnUrl->carry($request);
        if (is_string($explicitReturnTo) && $explicitReturnTo !== '') {
            return $explicitReturnTo;
        }

        if ($allowCurrentUri) {
            $currentUri = $this->safeReturnUrl->sanitizeCandidate($request->getRequestUri(), $request);
            if (is_string($currentUri) && $currentUri !== '') {
                return $currentUri;
            }
        }

        return route('notifications.index');
    }

    private function appendQueryParameter(string $url, string $key, string $value): string
    {
        $fragment = '';
        $fragmentPosition = strpos($url, '#');
        if ($fragmentPosition !== false) {
            $fragment = substr($url, $fragmentPosition);
            $url = substr($url, 0, $fragmentPosition);
        }

        if (str_contains($url, $key.'=')) {
            return $url.$fragment;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.rawurlencode($key).'='.rawurlencode($value).$fragment;
    }
}
