<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="C76-RPG ist eine asynchrone Multi-Welt Play-by-Post Plattform mit Kampagnen, Szenen und Charakterverwaltung.">
        <meta name="theme-color" content="#0f0f14">
        <meta name="robots" content="{{ config('privacy.x_robots_tag') }}">
        <meta name="googlebot" content="{{ config('privacy.x_robots_tag') }}">
        <meta name="bingbot" content="{{ config('privacy.x_robots_tag') }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        @php($appName = config('app.name', 'C76-RPG'))
        @php($pageTitle = 'C76-RPG | Multi-Welt Play-by-Post Plattform')
        @php($metaDescription = 'C76-RPG ist eine asynchrone Multi-Welt Play-by-Post Plattform mit Kampagnen, Szenen und Charakterverwaltung.')
        @php($ogImage = asset('images/og/chroniken-der-asche-og.png'))
        @php($pageUrl = url()->current())

        <meta property="og:site_name" content="{{ $appName }}">
        <meta property="og:title" content="{{ $pageTitle }}">
        <meta property="og:description" content="{{ $metaDescription }}">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ $pageUrl }}">
        <meta property="og:image" content="{{ $ogImage }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $pageTitle }}">
        <meta name="twitter:description" content="{{ $metaDescription }}">
        <meta name="twitter:image" content="{{ $ogImage }}">

        <title>{{ $pageTitle }}</title>

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
        @php($registerUrl = Route::has('register') ? route('register') : url('/register'))
        @php($loginUrl = Route::has('login') ? route('login') : url('/login'))

        <div class="relative isolate overflow-x-clip">
            <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top,_rgba(166,100,38,0.35),_transparent_44%),radial-gradient(circle_at_82%_28%,_rgba(90,66,129,0.18),_transparent_36%),linear-gradient(to_bottom,_#0a0a0f,_#020202)]"></div>

            <header class="mx-auto flex w-full max-w-6xl flex-col gap-4 px-5 py-6 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                <div class="font-heading break-words text-xl tracking-[0.12em] text-amber-300 sm:text-2xl sm:tracking-[0.20em]">
                    C76-RPG
                </div>
                <div class="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto sm:flex-wrap sm:items-center sm:justify-end sm:gap-3">
                    @include('partials.pwa-install-button')
                    <a href="{{ route('worlds.index') }}" class="ui-btn inline-flex !rounded-full sm:tracking-[0.14em]">
                        Welten
                    </a>
                    <a href="{{ route('knowledge.index') }}" class="ui-btn inline-flex !rounded-full sm:tracking-[0.14em]">
                        Wissen
                    </a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="ui-btn ui-btn-success inline-flex !rounded-full sm:tracking-[0.14em]">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ $loginUrl }}" class="ui-btn ui-btn-accent inline-flex !rounded-full sm:tracking-[0.14em]">
                            Login
                        </a>
                    @endauth
                    <div class="ui-badge inline-flex !rounded-full sm:tracking-[0.14em]">
                        Multi-World Beta
                    </div>
                </div>
            </header>

            <main class="mx-auto grid w-full max-w-6xl gap-10 break-words px-5 pb-16 pt-2 sm:px-8 lg:grid-cols-2 lg:items-center lg:gap-14 lg:pb-24">
                <section>
                    <p class="mb-4 text-xs uppercase tracking-[0.12em] text-amber-400/80 sm:text-sm sm:tracking-[0.18em]">
                        Asynchron • Story-Driven • Multi-Welt
                    </p>
                    <h1 class="mb-6 font-heading text-3xl leading-tight text-stone-100 sm:text-5xl lg:text-6xl">
                        Eine Plattform, viele Welten
                    </h1>
                    <p class="font-body max-w-xl text-lg leading-relaxed text-stone-300 sm:text-xl">
                        C76-RPG verbindet Charakterbogen, Kampagnenmanagement, Szenen-Threads und Wissenszentrum in einem konsistenten Play-by-Post Workflow.
                        Du wählst die Welt, der Rest bleibt vertraut.
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-4">
                        @guest
                            <a href="{{ $registerUrl }}" class="ui-btn ui-btn-accent inline-flex px-6 py-3 text-sm">
                                Jetzt registrieren
                            </a>
                            <a href="{{ $loginUrl }}" class="ui-btn inline-flex px-6 py-3 text-sm">
                                Zum Login
                            </a>
                        @else
                            <a href="{{ route('dashboard') }}" class="ui-btn ui-btn-success inline-flex px-6 py-3 text-sm">
                                Zum Dashboard
                            </a>
                        @endguest
                        <a href="#welten" class="ui-btn ui-btn-danger inline-flex px-6 py-3 text-sm">
                            Welten auswählen
                        </a>
                    </div>
                </section>

                <section class="relative">
                    <div class="absolute -inset-4 rounded-2xl bg-gradient-to-br from-amber-500/20 via-transparent to-transparent blur-2xl"></div>
                    <div class="relative overflow-hidden rounded-2xl border border-stone-700/80 bg-black/50 shadow-2xl shadow-black/60">
                        <img
                            src="{{ asset('images/hero-placeholder.svg') }}"
                            alt="C76-RPG Plattform"
                            class="h-[22rem] w-full object-cover sm:h-[28rem] lg:max-h-[70vh]"
                            loading="lazy"
                        >
                    </div>
                </section>
            </main>

            <section id="welten" class="mx-auto w-full max-w-6xl px-5 pb-20 sm:px-8">
                <div class="mb-6 flex items-center justify-between gap-3">
                    <h2 class="font-heading text-3xl text-stone-100">Verfügbare Welten</h2>
                    <a href="{{ route('worlds.index') }}" class="ui-btn">Alle Welten</a>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @forelse ($worlds as $world)
                        <article class="rounded-2xl border border-stone-800 bg-neutral-900/60 p-5">
                            <h3 class="font-heading text-2xl text-stone-100">{{ $world->name }}</h3>
                            @if ($world->tagline)
                                <p class="mt-2 text-sm text-amber-200">{{ $world->tagline }}</p>
                            @endif
                            <p class="mt-3 text-sm text-stone-300">{{ $world->description ?: 'Keine Beschreibung hinterlegt.' }}</p>
                            <p class="mt-3 text-xs uppercase tracking-widest text-stone-500">{{ $world->campaigns_count }} Kampagnen</p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <a href="{{ route('worlds.show', ['world' => $world]) }}" class="ui-btn inline-flex">Welt ansehen</a>
                                @auth
                                    <a href="{{ route('campaigns.index', ['world' => $world]) }}" class="ui-btn ui-btn-accent inline-flex">Kampagnen öffnen</a>
                                @else
                                    <a href="{{ route('worlds.activate', ['world' => $world]) }}" class="ui-btn ui-btn-accent inline-flex">Aktivieren</a>
                                @endauth
                            </div>
                        </article>
                    @empty
                        <article class="rounded-2xl border border-amber-700/60 bg-amber-900/20 p-5 text-amber-100">
                            Aktuell sind keine aktiven Welten verfügbar.
                        </article>
                    @endforelse
                </div>
            </section>

            <footer class="mx-auto w-full max-w-6xl border-t border-stone-800/80 px-5 py-6 text-center text-xs tracking-widest text-stone-500 sm:px-8">
                <div>
                    @include('partials.version-footer')
                </div>
                <div class="mt-3">
                    @include('partials.legal-links')
                </div>
            </footer>
        </div>
    </body>
</html>
