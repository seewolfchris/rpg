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
        @php($ogImage = asset('images/og/c76-rpg-og.png'))
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
    <body class="app-shell min-h-full overflow-x-clip bg-neutral-950 text-stone-200 antialiased">
        @php($registerUrl = Route::has('register') ? route('register') : url('/register'))
        @php($loginUrl = Route::has('login') ? route('login') : url('/login'))
        @php(
            $landingTeasers = [
                '„Das letzte Licht im Turm verlosch, und niemand erinnerte sich, wer dort oben Wache hatte.“',
                '„Sie legte den Dolch auf den Tisch und sagte nur: Heute Nacht schuldet mir die Stadt eine Antwort.“',
                '„Als die Glocke dreizehn schlug, wusste jeder in der Gasse, dass der Frieden vorbei war.“',
                '„Er lächelte, obwohl sein Schatten in die falsche Richtung fiel.“',
                '„Der Brief war verbrannt, aber ein Satz blieb lesbar: Wenn du das liest, sind wir schon zu spät.“',
            ]
        )
        @php($randomTeaser = $landingTeasers[array_rand($landingTeasers)])
        @php(
            $worldSnippets = [
                'Zwischen flackernden Fackeln wartet eine Entscheidung, die niemand zurücknehmen kann.',
                'Eine Tür steht offen, obwohl sie gestern noch zugemauert war.',
                'Aus der Ferne trägt der Wind ein Gerücht, das nur heute wahr sein könnte.',
                'Jemand hat den Namen deiner Figur bereits in das Protokoll von morgen geschrieben.',
                'Im Nebenraum endet ein Schwur genau in dem Moment, in dem du eintrittst.',
                'Der Rat schweigt, aber die Wachen greifen bereits zu den Klingen.',
            ]
        )

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
                    <a href="{{ route('knowledge.global.index') }}" class="ui-btn inline-flex !rounded-full sm:tracking-[0.14em]">
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

            <main class="mx-auto grid w-full max-w-6xl gap-9 break-words px-5 pb-16 pt-2 sm:px-8 md:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)] md:items-center md:gap-12 lg:gap-14 lg:pb-24">
                <section class="md:pr-2">
                    <p class="mb-3 text-xs uppercase tracking-[0.16em] text-amber-300/85 sm:text-sm">
                        Letzter Satz einer laufenden Szene
                    </p>
                    <blockquote class="rounded-2xl border border-amber-700/45 bg-amber-900/10 px-5 py-4 text-base italic leading-relaxed text-amber-100 shadow-lg shadow-black/25 sm:text-lg">
                        {{ $randomTeaser }}
                    </blockquote>

                    <h1 class="mt-6 font-heading text-3xl leading-tight text-stone-100 sm:text-4xl lg:text-5xl">
                        Betrete eine Welt.<br class="hidden sm:block">Schreibe weiter, wo andere aufgehört haben.
                    </h1>

                    <div class="font-body mt-5 max-w-2xl space-y-4 text-lg leading-relaxed text-stone-300 sm:text-xl">
                        <p>
                            In jeder Kampagne wartet bereits ein offener Konflikt.
                            In jeder Szene liegt ein Satz, der auf deine Figur wartet.
                        </p>
                        <p>
                            C76-RPG verbindet Charaktere, Szenen und Lore zu einem Schreibraum,
                            der sich wie ein Roman liest und wie ein Rollenspiel atmet.
                        </p>
                    </div>

                    <div class="mt-8 flex flex-wrap items-center gap-3 sm:gap-4">
                        @guest
                            <a href="{{ $registerUrl }}" class="ui-btn ui-btn-accent inline-flex px-6 py-3 text-sm">
                                Jetzt eintreten
                            </a>
                            <a href="{{ $loginUrl }}" class="ui-btn inline-flex px-6 py-3 text-sm">
                                Bereits registriert
                            </a>
                        @else
                            <a href="{{ route('dashboard') }}" class="ui-btn ui-btn-success inline-flex px-6 py-3 text-sm">
                                Zurück ins Dashboard
                            </a>
                        @endguest
                        <a href="#welten" class="ui-btn ui-btn-danger inline-flex px-6 py-3 text-sm">
                            Betrete eine Welt
                        </a>
                    </div>
                </section>

                <section class="relative">
                    <figure class="landing-hero-figure group relative overflow-hidden rounded-3xl border border-stone-700/70 bg-black/40 shadow-2xl shadow-black/35">
                        <img
                            src="{{ asset('images/og/c76-rpg-og.png') }}"
                            alt="Atmosphärische Vorschau auf das C76-RPG Universum"
                            class="landing-hero-image h-[18rem] w-full object-cover sm:h-[22rem] lg:h-[28rem]"
                            loading="lazy"
                        >
                        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/70 via-black/25 to-transparent"></div>
                        <figcaption class="absolute inset-x-0 bottom-0 p-4 text-sm leading-relaxed text-stone-100 sm:p-5 sm:text-base">
                            „Die Szene läuft bereits. Du setzt den nächsten Satz.“
                        </figcaption>
                    </figure>
                </section>
            </main>

            <section id="welten" class="mx-auto w-full max-w-6xl px-5 pb-20 sm:px-8">
                <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.12em] text-amber-300/80">Weltenübersicht</p>
                        <h2 class="font-heading text-3xl text-stone-100">Betrete eine Welt</h2>
                    </div>
                    <a href="{{ route('worlds.index') }}" class="ui-btn">Alle Welten</a>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @forelse ($worlds as $world)
                        @php($snippetSource = trim((string) ($world->tagline ?: $world->description ?: 'Eine neue Szene beginnt, sobald du den ersten Beitrag setzt.')))
                        @php($worldSnippet = \Illuminate\Support\Str::limit($snippetSource, 150))
                        @php($hoverSnippet = $worldSnippets[$loop->index % count($worldSnippets)])
                        <article class="group landing-world-card relative rounded-2xl border border-stone-800 bg-neutral-900/65 p-5 shadow-xl shadow-black/25 transition duration-300 hover:-translate-y-0.5 hover:border-amber-600/60 hover:bg-neutral-900/80">
                            <h3 class="font-heading text-2xl text-stone-100">{{ $world->name }}</h3>
                            @if ($world->tagline)
                                <p class="mt-2 text-sm text-amber-200">{{ $world->tagline }}</p>
                            @endif
                            <p class="mt-3 text-sm leading-relaxed text-stone-300">{{ $worldSnippet }}</p>

                            <p class="landing-world-snippet mt-3 rounded-lg border border-amber-700/40 bg-amber-900/10 px-3 py-2 text-xs italic leading-relaxed text-amber-100 opacity-100 transition duration-300 sm:opacity-0 sm:translate-y-1 sm:group-hover:translate-y-0 sm:group-hover:opacity-100">
                                {{ $hoverSnippet }}
                            </p>

                            <p class="mt-4 text-xs uppercase tracking-widest text-stone-500">{{ $world->campaigns_count }} Kampagnen</p>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <a href="{{ route('worlds.show', ['world' => $world]) }}" class="ui-btn inline-flex">Welt ansehen</a>
                                @auth
                                    <a href="{{ route('campaigns.index', ['world' => $world]) }}" class="ui-btn ui-btn-accent inline-flex">Welt betreten</a>
                                @else
                                    <form method="POST" action="{{ route('worlds.activate', ['world' => $world]) }}">
                                        @csrf
                                        <button type="submit" class="ui-btn ui-btn-accent inline-flex">Welt betreten</button>
                                    </form>
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
