@props([
    'unreadNotificationsCount' => 0,
    'bookmarkCount' => 0,
    'pendingCampaignInvitationsCount' => 0,
])

@php
    $standardLinkBase = 'app-nav-link rounded-md border px-3 py-2 text-xs font-semibold uppercase tracking-widest transition';
    $secondaryLinkBase = 'app-nav-link app-nav-link-secondary rounded-md border px-2.5 py-1.5 text-[0.68rem] font-semibold uppercase tracking-[0.08em] transition';
    $standardLinkInactive = 'border-stone-600/70 text-stone-200 hover:border-stone-400 hover:text-stone-100';
    $standardLinkActive = 'border-amber-500/70 bg-amber-500/20 text-amber-100';
    $secondaryLinkInactive = 'border-stone-700/70 text-stone-300 hover:border-stone-500 hover:text-stone-100';
    $secondaryLinkActive = 'border-amber-500/60 bg-amber-500/15 text-amber-100';
    $statusChipClass = 'app-nav-status-chip rounded-md border border-stone-700/70 bg-black/25 px-2.5 py-1.5 text-[0.68rem] font-semibold uppercase tracking-[0.06em] text-stone-300';
    $accountActionClass = 'app-nav-account-action rounded-md border border-stone-700/70 bg-black/20 px-2.5 py-1.5 text-[0.68rem] font-semibold uppercase tracking-[0.08em] text-stone-300 transition hover:border-stone-500 hover:text-stone-100';

    $isWorldsSection = request()->routeIs('worlds.*');
    $isKnowledgeSection = request()->routeIs('knowledge.*');
    $isCampaignsSection = request()->routeIs('campaigns.*');
    $isCharactersSection = request()->routeIs('characters.*');
    $isNotificationsSection = request()->routeIs('notifications.*');
    $isGmSection = request()->routeIs('gm.*');
    $isAdminSection = request()->routeIs('admin.*');

    $isWorldsCurrent = request()->routeIs('worlds.index');
    $isKnowledgeCurrent = request()->routeIs('knowledge.global.index');
    $isDashboardCurrent = request()->routeIs('dashboard');
    $isLeaderboardCurrent = request()->routeIs('leaderboard.*');
    $isCampaignsCurrent = request()->routeIs('campaigns.index');
    $isCharactersCurrent = request()->routeIs('characters.index');
    $isNotificationsCurrent = request()->routeIs('notifications.index');
    $isSubscriptionsCurrent = request()->routeIs('scene-subscriptions.index');
    $isBookmarksCurrent = request()->routeIs('bookmarks.index');
    $isInvitationsCurrent = request()->routeIs('campaign-invitations.index');
    $isGmCurrent = request()->routeIs('gm.index');
    $isAdminCurrent = request()->routeIs('admin.users.*');
    $showManagementNavigation = auth()->check() && (
        auth()->user()->isGmOrAdmin()
        || auth()->user()->hasAnyCoGmCampaignAccess()
        || auth()->user()->hasRole(\App\Enums\UserRole::ADMIN)
    );
@endphp

<nav id="app-mobile-navigation" class="app-nav" data-mobile-sheet-panel aria-label="Hauptnavigation">
    <div class="app-nav-group app-nav-primary" data-nav-group="primary" role="group" aria-label="Primäre Navigation">
        <p class="app-nav-group-label">Hauptnavigation</p>
        <a
            href="{{ route('worlds.index') }}"
            class="{{ $standardLinkBase }} {{ $isWorldsSection ? $standardLinkActive : $standardLinkInactive }}"
            @if ($isWorldsCurrent) aria-current="page" @endif
        >
            Welten
        </a>
        <a
            href="{{ route('knowledge.global.index') }}"
            class="{{ $standardLinkBase }} {{ $isKnowledgeSection ? $standardLinkActive : $standardLinkInactive }}"
            @if ($isKnowledgeCurrent) aria-current="page" @endif
        >
            Wissen
        </a>
        @auth
            <a
                href="{{ route('dashboard') }}"
                class="{{ $standardLinkBase }} {{ $isDashboardCurrent ? $standardLinkActive : $standardLinkInactive }}"
                @if ($isDashboardCurrent) aria-current="page" @endif
            >
                Übersicht
            </a>
            <a
                href="{{ route('campaigns.index') }}"
                class="{{ $standardLinkBase }} {{ $isCampaignsSection ? $standardLinkActive : $standardLinkInactive }}"
                @if ($isCampaignsCurrent) aria-current="page" @endif
            >
                Kampagnen
            </a>
            <a
                href="{{ route('characters.index') }}"
                class="{{ $standardLinkBase }} {{ $isCharactersSection ? $standardLinkActive : $standardLinkInactive }}"
                @if ($isCharactersCurrent) aria-current="page" @endif
            >
                Charaktere
            </a>
            <a
                href="{{ route('notifications.index') }}"
                class="relative {{ $standardLinkBase }} {{ $isNotificationsSection ? $standardLinkActive : $standardLinkInactive }}"
                @if ($isNotificationsCurrent) aria-current="page" @endif
            >
                Mitteilungen
                @if ($unreadNotificationsCount > 0)
                    <span id="nav-unread-notifications-badge" class="absolute -right-1.5 -top-1.5 inline-flex min-w-5 items-center justify-center rounded-full border border-amber-300/80 bg-amber-500 px-1.5 text-[0.6rem] font-bold text-black">
                        {{ $unreadNotificationsCount > 99 ? '99+' : $unreadNotificationsCount }}
                    </span>
                @else
                    <span id="nav-unread-notifications-badge" class="hidden"></span>
                @endif
            </a>
        @endauth
    </div>

    @auth
        <div class="app-nav-group app-nav-secondary" data-nav-group="secondary" role="group" aria-label="Meine Bereiche">
            <p class="app-nav-group-label">Meine Bereiche</p>
            <a
                href="{{ route('leaderboard.index') }}"
                class="{{ $secondaryLinkBase }} {{ $isLeaderboardCurrent ? $secondaryLinkActive : $secondaryLinkInactive }}"
                @if ($isLeaderboardCurrent) aria-current="page" @endif
            >
                Rangliste
            </a>
            <a
                href="{{ route('scene-subscriptions.index') }}"
                class="{{ $secondaryLinkBase }} {{ $isSubscriptionsCurrent ? $secondaryLinkActive : $secondaryLinkInactive }}"
                @if ($isSubscriptionsCurrent) aria-current="page" @endif
            >
                Abos
            </a>
            <a
                href="{{ route('bookmarks.index') }}"
                class="relative {{ $secondaryLinkBase }} {{ $isBookmarksCurrent ? $secondaryLinkActive : $secondaryLinkInactive }}"
                @if ($isBookmarksCurrent) aria-current="page" @endif
            >
                Lesezeichen
                @if ($bookmarkCount > 0)
                    <span id="nav-bookmark-count-badge" class="absolute -right-1.5 -top-1.5 inline-flex min-w-5 items-center justify-center rounded-full border border-emerald-300/80 bg-emerald-500 px-1.5 text-[0.6rem] font-bold text-black">
                        {{ $bookmarkCount > 99 ? '99+' : $bookmarkCount }}
                    </span>
                @else
                    <span id="nav-bookmark-count-badge" class="hidden"></span>
                @endif
            </a>
            <a
                href="{{ route('campaign-invitations.index') }}"
                class="relative {{ $secondaryLinkBase }} {{ $isInvitationsCurrent ? $secondaryLinkActive : $secondaryLinkInactive }}"
                @if ($isInvitationsCurrent) aria-current="page" @endif
            >
                Einladungen
                @if ($pendingCampaignInvitationsCount > 0)
                    <span class="absolute -right-1.5 -top-1.5 inline-flex min-w-5 items-center justify-center rounded-full border border-amber-300/80 bg-amber-500 px-1.5 text-[0.6rem] font-bold text-black">
                        {{ $pendingCampaignInvitationsCount > 99 ? '99+' : $pendingCampaignInvitationsCount }}
                    </span>
                @endif
            </a>
        </div>

        @if ($showManagementNavigation)
            <div class="app-nav-group app-nav-management" data-nav-group="management" role="group" aria-label="Verwaltung">
                <p class="app-nav-group-label">Verwaltung</p>
            @if (auth()->user()->isGmOrAdmin() || auth()->user()->hasAnyCoGmCampaignAccess())
                <a
                    href="{{ route('gm.index') }}"
                    class="{{ $secondaryLinkBase }} {{ $isGmSection ? $secondaryLinkActive : $secondaryLinkInactive }}"
                    @if ($isGmCurrent) aria-current="page" @endif
                >
                    SL-Bereich
                </a>
            @endif
            @if (auth()->user()->hasRole(\App\Enums\UserRole::ADMIN))
                <a
                    href="{{ route('admin.users.index') }}"
                    class="{{ $secondaryLinkBase }} {{ $isAdminSection ? $secondaryLinkActive : $secondaryLinkInactive }}"
                    @if ($isAdminCurrent) aria-current="page" @endif
                >
                    Benutzerverwaltung
                </a>
            @endif
            </div>
        @endif

        <div class="app-nav-group app-nav-account" data-nav-group="account">
            <span class="{{ $statusChipClass }}">
                {{ auth()->user()->points }} Punkte
            </span>
            @include('partials.pwa-install-button')
            <form method="POST" action="{{ route('logout') }}" data-logout-form>
                @csrf
                <button
                    type="submit"
                    class="{{ $accountActionClass }}"
                >
                    Abmelden
                </button>
            </form>
        </div>
    @else
        <div class="app-nav-group app-nav-account" data-nav-group="account">
            @include('partials.pwa-install-button')
            <a
                href="{{ route('login') }}"
                class="{{ $secondaryLinkBase }} {{ $secondaryLinkInactive }}"
            >
                Anmelden
            </a>
            <a
                href="{{ route('register') }}"
                class="{{ $standardLinkBase }} border-amber-500/60 bg-amber-500/15 text-amber-100 hover:bg-amber-500/30"
            >
                Registrierung
            </a>
        </div>
    @endauth
</nav>
