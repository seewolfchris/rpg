<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        @php($appVersion = (string) config('app.version', 'v0.21-beta'))
        @php($appBuild = (string) config('app.build', ''))
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#0f0f14">
        <meta name="application-version" content="{{ $appVersion }}{{ $appBuild !== '' ? ' ('.$appBuild.')' : '' }}">
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
        @php($characterSheetGlobalPath = public_path('js/character-sheet.global.js'))
        @if (file_exists($characterSheetGlobalPath))
            <script defer src="{{ asset('js/character-sheet.global.js') }}?v={{ filemtime($characterSheetGlobalPath) }}"></script>
        @endif
        <script>
            window.deferLoadingAlpine = function (startAlpine) {
                window.__startAlpine = startAlpine;
            };
        </script>
        @php($alpineScriptPath = public_path('js/alpinejs-3.14.8.min.js'))
        @if (file_exists($alpineScriptPath))
            <script defer src="{{ asset('js/alpinejs-3.14.8.min.js') }}?v={{ filemtime($alpineScriptPath) }}"></script>
        @endif

        <title>@yield('title', config('app.name', 'C76-RPG'))</title>
        <meta name="description" content="{{ $metaDescription }}">
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
    <body class="min-h-full overflow-x-clip bg-neutral-950 text-stone-200 antialiased">
        <div class="relative isolate min-h-screen overflow-x-clip">
            <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top,_rgba(166,100,38,0.34),_transparent_44%),radial-gradient(circle_at_82%_28%,_rgba(90,66,129,0.18),_transparent_36%),linear-gradient(to_bottom,_#0a0a0f,_#020202)]"></div>

            <header class="mx-auto flex w-full max-w-6xl flex-col gap-4 px-4 py-6 sm:flex-row sm:items-center sm:justify-between sm:px-8">
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
                                <span class="absolute -right-1.5 -top-1.5 inline-flex min-w-5 items-center justify-center rounded-full border border-amber-300/80 bg-amber-500 px-1.5 text-[0.6rem] font-bold text-black">
                                    {{ $unreadNotificationsCount > 99 ? '99+' : $unreadNotificationsCount }}
                                </span>
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
                            Bookmarks
                            @if ($bookmarkCount > 0)
                                <span class="absolute -right-1.5 -top-1.5 inline-flex min-w-5 items-center justify-center rounded-full border border-emerald-300/80 bg-emerald-500 px-1.5 text-[0.6rem] font-bold text-black">
                                    {{ $bookmarkCount > 99 ? '99+' : $bookmarkCount }}
                                </span>
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
                        @if (auth()->user()->isGmOrAdmin())
                            <a
                                href="{{ route('gm.index') }}"
                                class="rounded-md border border-amber-500/60 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/20"
                            >
                                GM Hub
                            </a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button
                                type="submit"
                                class="rounded-md border border-amber-500/60 bg-amber-500/15 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/30"
                            >
                                Logout
                            </button>
                        </form>
                    @else
                        <a
                            href="{{ route('login') }}"
                            class="rounded-md border border-stone-600/70 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                        >
                            Login
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

            <main class="mx-auto w-full max-w-6xl break-words px-5 pb-16 pt-2 sm:px-8">
                @if (session('status'))
                    <div class="mb-6 rounded-lg border border-emerald-600/40 bg-emerald-900/20 px-4 py-3 text-sm text-emerald-200">
                        {{ session('status') }}
                    </div>
                @endif

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
                @php($activeWorldContext = request()->route('world'))
                @php(
                    $activeWorldSlug = $activeWorldContext instanceof \App\Models\World
                        ? $activeWorldContext->slug
                        : (session('world_slug') ?: \App\Models\World::defaultSlug())
                )
                <div
                    data-browser-notifications
                    data-subscribe-url="{{ route('api.webpush.subscribe') }}"
                    data-unsubscribe-url="{{ route('api.webpush.unsubscribe') }}"
                    data-enabled-kinds='@json($browserNotificationKinds)'
                    data-app-name="{{ config('app.name', 'C76-RPG') }}"
                    data-world-slug="{{ $activeWorldSlug }}"
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
