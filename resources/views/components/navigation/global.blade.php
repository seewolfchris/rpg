@props([
    'unreadNotificationsCount' => 0,
    'bookmarkCount' => 0,
    'pendingCampaignInvitationsCount' => 0,
])

@php
    $standardLinkBase = 'rounded-md border px-3 py-2 text-xs font-semibold uppercase tracking-widest transition';
    $standardLinkInactive = 'border-stone-600/70 text-stone-200 hover:border-stone-400 hover:text-stone-100';
    $standardLinkActive = 'border-amber-500/70 bg-amber-500/20 text-amber-100';

    $isWorldsCurrent = request()->routeIs('worlds.*');
    $isKnowledgeCurrent = request()->routeIs('knowledge.*');
    $isDashboardCurrent = request()->routeIs('dashboard');
    $isLeaderboardCurrent = request()->routeIs('leaderboard.*');
    $isCampaignsCurrent = request()->routeIs('campaigns.*');
    $isCharactersCurrent = request()->routeIs('characters.*');
    $isNotificationsCurrent = request()->routeIs('notifications.*');
    $isSubscriptionsCurrent = request()->routeIs('scene-subscriptions.*');
    $isBookmarksCurrent = request()->routeIs('bookmarks.*');
    $isInvitationsCurrent = request()->routeIs('campaign-invitations.*');
    $isGmCurrent = request()->routeIs('gm.*');
    $isAdminCurrent = request()->routeIs('admin.*');
@endphp

<nav id="app-mobile-navigation" class="app-nav" data-mobile-sheet-panel aria-label="Hauptnavigation">
    @include('partials.pwa-install-button')
    <a
        href="{{ route('worlds.index') }}"
        class="{{ $standardLinkBase }} {{ $isWorldsCurrent ? $standardLinkActive : $standardLinkInactive }}"
        @if ($isWorldsCurrent) aria-current="page" @endif
    >
        Welten
    </a>
    <a
        href="{{ route('knowledge.global.index') }}"
        class="{{ $standardLinkBase }} {{ $isKnowledgeCurrent ? $standardLinkActive : $standardLinkInactive }}"
        @if ($isKnowledgeCurrent) aria-current="page" @endif
    >
        Wissen
    </a>
    @auth
        @php($activeWorld = request()->route('world'))
        @if ($activeWorld instanceof \App\Models\World)
            <span class="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100">
                Welt: {{ $activeWorld->name }}
            </span>
        @endif
        <span class="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100">
            {{ auth()->user()->points }} Punkte
        </span>
        <a
            href="{{ route('dashboard') }}"
            class="{{ $standardLinkBase }} {{ $isDashboardCurrent ? $standardLinkActive : $standardLinkInactive }}"
            @if ($isDashboardCurrent) aria-current="page" @endif
        >
            Dashboard
        </a>
        <a
            href="{{ route('leaderboard.index') }}"
            class="{{ $standardLinkBase }} {{ $isLeaderboardCurrent ? $standardLinkActive : $standardLinkInactive }}"
            @if ($isLeaderboardCurrent) aria-current="page" @endif
        >
            Rangliste
        </a>
        <a
            href="{{ route('campaigns.index') }}"
            class="{{ $standardLinkBase }} {{ $isCampaignsCurrent ? $standardLinkActive : $standardLinkInactive }}"
            @if ($isCampaignsCurrent) aria-current="page" @endif
        >
            Kampagnen
        </a>
        <a
            href="{{ route('characters.index') }}"
            class="{{ $standardLinkBase }} {{ $isCharactersCurrent ? $standardLinkActive : $standardLinkInactive }}"
            @if ($isCharactersCurrent) aria-current="page" @endif
        >
            Charaktere
        </a>
        <a
            href="{{ route('notifications.index') }}"
            class="relative {{ $standardLinkBase }} {{ $isNotificationsCurrent ? $standardLinkActive : $standardLinkInactive }}"
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
        <a
            href="{{ route('scene-subscriptions.index') }}"
            class="{{ $standardLinkBase }} {{ $isSubscriptionsCurrent ? $standardLinkActive : $standardLinkInactive }}"
            @if ($isSubscriptionsCurrent) aria-current="page" @endif
        >
            Abos
        </a>
        <a
            href="{{ route('bookmarks.index') }}"
            class="relative {{ $standardLinkBase }} {{ $isBookmarksCurrent ? $standardLinkActive : $standardLinkInactive }}"
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
            class="relative {{ $standardLinkBase }} {{ $isInvitationsCurrent ? $standardLinkActive : $standardLinkInactive }}"
            @if ($isInvitationsCurrent) aria-current="page" @endif
        >
            Einladungen
            @if ($pendingCampaignInvitationsCount > 0)
                <span class="absolute -right-1.5 -top-1.5 inline-flex min-w-5 items-center justify-center rounded-full border border-amber-300/80 bg-amber-500 px-1.5 text-[0.6rem] font-bold text-black">
                    {{ $pendingCampaignInvitationsCount > 99 ? '99+' : $pendingCampaignInvitationsCount }}
                </span>
            @endif
        </a>
        @if (auth()->user()->isGmOrAdmin() || auth()->user()->hasAnyCoGmCampaignAccess())
            <a
                href="{{ route('gm.index') }}"
                class="rounded-md border px-3 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition {{ $isGmCurrent ? 'border-amber-400/80 bg-amber-500/25' : 'border-amber-500/60 hover:bg-amber-500/20' }}"
                @if ($isGmCurrent) aria-current="page" @endif
            >
                GM-Bereich
            </a>
        @endif
        @if (auth()->user()->hasRole(\App\Enums\UserRole::ADMIN))
            <a
                href="{{ route('admin.users.moderation.index') }}"
                class="rounded-md border px-3 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition {{ $isAdminCurrent ? 'border-amber-400/80 bg-amber-500/25' : 'border-amber-500/60 hover:bg-amber-500/20' }}"
                @if ($isAdminCurrent) aria-current="page" @endif
            >
                Benutzerverwaltung
            </a>
        @endif
        <form method="POST" action="{{ route('logout') }}" data-logout-form>
            @csrf
            <button
                type="submit"
                class="rounded-md border border-amber-500/60 bg-amber-500/15 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/30"
            >
                Abmelden
            </button>
        </form>
    @else
        <a
            href="{{ route('login') }}"
            class="rounded-md border border-stone-600/70 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
        >
            Anmelden
        </a>
        <a
            href="{{ route('register') }}"
            class="rounded-md border border-amber-500/60 bg-amber-500/15 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/30"
        >
            Registrierung
        </a>
    @endauth
</nav>
