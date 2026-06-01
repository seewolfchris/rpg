@props([
    'unreadNotificationsCount' => 0,
    'bookmarkCount' => 0,
    'pendingCampaignInvitationsCount' => 0,
])

<nav class="app-nav" aria-label="Hauptnavigation">
    @include('partials.pwa-install-button')
    <a
        href="{{ route('worlds.index') }}"
        class="rounded-md border border-stone-600/70 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
    >
        Welten
    </a>
    <a
        href="{{ route('knowledge.global.index') }}"
        class="rounded-md border border-stone-600/70 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
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
            class="rounded-md border border-stone-600/70 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
        >
            Dashboard
        </a>
        <a
            href="{{ route('leaderboard.index') }}"
            class="rounded-md border border-stone-600/70 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
        >
            Rangliste
        </a>
        <a
            href="{{ route('campaigns.index') }}"
            class="rounded-md border border-stone-600/70 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
        >
            Kampagnen
        </a>
        <a
            href="{{ route('characters.index') }}"
            class="rounded-md border border-stone-600/70 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
        >
            Charaktere
        </a>
        <a
            href="{{ route('notifications.index') }}"
            class="relative rounded-md border border-stone-600/70 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
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
            class="rounded-md border border-stone-600/70 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
        >
            Abos
        </a>
        <a
            href="{{ route('bookmarks.index') }}"
            class="relative rounded-md border border-stone-600/70 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
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
            class="relative rounded-md border border-stone-600/70 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
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
                class="rounded-md border border-amber-500/60 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/20"
            >
                GM-Bereich
            </a>
        @endif
        @if (auth()->user()->hasRole(\App\Enums\UserRole::ADMIN))
            <a
                href="{{ route('admin.users.moderation.index') }}"
                class="rounded-md border border-amber-500/60 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/20"
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
