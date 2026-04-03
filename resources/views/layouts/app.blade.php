<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    class="h-full {{ data_get($activeWorldTheme ?? [], 'html_class') }}"
    data-world-slug="{{ $activeWorldSlug ?? \App\Models\World::defaultSlug() }}"
    data-world-theme="{{ data_get($activeWorldTheme ?? [], 'theme_key', 'default') }}"
    @if ((string) data_get($activeWorldTheme ?? [], 'css_variable_style', '') !== '')
        style="{{ data_get($activeWorldTheme ?? [], 'css_variable_style', '') }}"
    @endif
>
    <head>
        @php($appVersion = (string) config('app.version', 'v0.27-beta'))
        @php($appBuild = (string) config('app.build', ''))
        @php($swVersion = $appBuild !== '' ? $appVersion.'-'.$appBuild : $appVersion)
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="auth-user-id" content="{{ auth()->check() ? (string) auth()->id() : 'guest' }}">
        <meta name="theme-color" content="{{ data_get($activeWorldTheme ?? [], 'theme_color', '#0f0f14') }}">
        <meta name="application-version" content="{{ $appVersion }}{{ $appBuild !== '' ? ' ('.$appBuild.')' : '' }}">
        <meta name="sw-version" content="{{ $swVersion }}">
        <meta name="robots" content="{{ config('privacy.x_robots_tag') }}">
        <meta name="googlebot" content="{{ config('privacy.x_robots_tag') }}">
        <meta name="bingbot" content="{{ config('privacy.x_robots_tag') }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        @php($appName = config('app.name', 'C76-RPG'))
        @php($metaDescription = trim((string) $__env->yieldContent('meta_description', 'C76-RPG ist eine asynchrone Play-by-Post Plattform mit Kampagnen, Szenen und Charakterverwaltung in mehreren Welten.')))
        @php($ogImage = asset('images/og/c76-rpg-og.png'))
        @php($pageUrl = url()->current())
        @php($unreadNotificationsCount = (int) ($unreadNotificationsCount ?? 0))
        @php($bookmarkCount = (int) ($bookmarkCount ?? 0))
        @php($pendingCampaignInvitationsCount = (int) ($pendingCampaignInvitationsCount ?? 0))
        @php(
            $htmxConfig = json_encode([
                'selfRequestsOnly' => true,
                'historyCacheSize' => 15,
            ], JSON_UNESCAPED_SLASHES)
        )
        @php($characterSheetGlobalPath = public_path('js/character-sheet.global.js'))
        @if (file_exists($characterSheetGlobalPath))
            <script defer src="{{ asset('js/character-sheet.global.js') }}?v={{ filemtime($characterSheetGlobalPath) }}"></script>
        @endif

        <title>@yield('title', config('app.name', 'C76-RPG'))</title>
        <meta name="description" content="{{ $metaDescription }}">
        <meta name="htmx-config" content='{{ $htmxConfig }}'>
        <meta property="og:site_name" content="{{ $appName }}">
        <meta property="og:title" content="@yield('title', $appName)">
        <meta property="og:description" content="{{ $metaDescription }}">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ $pageUrl }}">
        <meta property="og:image" content="{{ $ogImage }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="@yield('title', $appName)">
        <meta name="twitter:description" content="{{ $metaDescription }}">
        <meta name="twitter:image" content="{{ $ogImage }}">

        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/icons/apple-touch-icon.png') }}">

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body
        class="app-shell min-h-full overflow-x-clip bg-neutral-950 text-stone-200 antialiased {{ data_get($activeWorldTheme ?? [], 'body_class') }}"
        data-world-slug="{{ $activeWorldSlug ?? \App\Models\World::defaultSlug() }}"
        data-world-theme="{{ data_get($activeWorldTheme ?? [], 'theme_key', 'default') }}"
        hx-headers='{"X-CSRF-TOKEN":"{{ csrf_token() }}"}'
    >
        <div class="relative isolate min-h-screen overflow-x-clip">
            <div class="app-atmosphere pointer-events-none absolute inset-0 -z-10"></div>
            <div id="global-hx-indicator" hx-indicator class="pointer-events-none fixed right-4 top-4 z-50 rounded-md border border-amber-600/60 bg-black/75 px-3 py-1 text-xs uppercase tracking-[0.12em] text-amber-200">
                Laden ...
            </div>

            <header class="app-header mx-auto flex w-full max-w-6xl flex-col gap-4 px-4 py-6 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                <a href="{{ route('home') }}" class="font-heading break-words text-lg tracking-[0.12em] text-amber-300 sm:text-xl sm:tracking-[0.18em]">
                    C76-RPG
                </a>

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
                                Admin-Nutzer
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
            </header>

            <main
                id="app-main"
                class="app-main-shell mx-auto w-full max-w-6xl break-words px-5 pb-16 pt-2 sm:px-8"
            >
                @include('partials.flash')

                @yield('content')
            </main>

            @auth
                @php(
                    $browserNotificationKinds = collect(auth()->user()->resolvedNotificationPreferences())
                        ->filter(fn (array $channels): bool => (bool) ($channels['browser'] ?? false))
                        ->keys()
                        ->values()
                        ->all()
                )
                <div
                    data-browser-notifications
                    data-subscribe-url="{{ route('api.webpush.subscribe') }}"
                    data-unsubscribe-url="{{ route('api.webpush.unsubscribe') }}"
                    data-enabled-kinds='@json($browserNotificationKinds)'
                    data-app-name="{{ config('app.name', 'C76-RPG') }}"
                    data-world-slug="{{ $activeWorldSlug ?? \App\Models\World::defaultSlug() }}"
                    data-vapid-public-key="{{ config('webpush.vapid.public_key') }}"
                    class="hidden"
                    aria-hidden="true"
                ></div>
            @endauth

            <footer class="mx-auto w-full max-w-6xl px-5 pb-8 sm:px-8">
                @include('partials.version-footer')
                <div class="mt-3">
                    @include('partials.legal-links')
                </div>
            </footer>
        </div>
    </body>
</html>
