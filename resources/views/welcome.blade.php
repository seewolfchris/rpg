<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Chroniken der Asche ist ein asynchrones Dark-Fantasy Play-by-Post RPG mit epischer Welt, Intrigen und düsterer Magie.">
        <meta name="theme-color" content="#0f0f14">
        <meta name="robots" content="{{ config('privacy.x_robots_tag') }}">
        <meta name="googlebot" content="{{ config('privacy.x_robots_tag') }}">
        <meta name="bingbot" content="{{ config('privacy.x_robots_tag') }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

        <title>{{ config('app.name', 'Chroniken der Asche') }}</title>

        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/icons/icon-192.svg') }}">

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-full overflow-x-clip bg-neutral-950 text-stone-200 antialiased">
        @php($registerUrl = Route::has('register') ? route('register') : url('/register'))

        <div class="relative isolate overflow-x-clip">
            <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top,_rgba(166,100,38,0.35),_transparent_44%),radial-gradient(circle_at_80%_30%,_rgba(87,53,120,0.15),_transparent_40%),linear-gradient(to_bottom,_#0a0a0f,_#020202)]"></div>
            <div class="pointer-events-none absolute -left-16 top-32 -z-10 h-56 w-56 rounded-full bg-amber-900/20 blur-3xl"></div>
            <div class="pointer-events-none absolute -right-16 bottom-24 -z-10 h-64 w-64 rounded-full bg-slate-500/10 blur-3xl"></div>

            <header class="mx-auto flex w-full max-w-6xl flex-col gap-4 px-5 py-6 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                <div class="font-heading break-words text-xl tracking-[0.12em] text-amber-300 sm:text-2xl sm:tracking-[0.20em]">
                    CHRONIKEN DER ASCHE
                </div>
                <div class="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto sm:flex-wrap sm:items-center sm:justify-end sm:gap-3">
                    @include('partials.pwa-install-button')
                    <a
                        href="{{ route('knowledge.index') }}"
                        class="inline-flex items-center justify-center rounded-full border border-stone-600/70 bg-black/35 px-4 py-2 text-center text-xs uppercase tracking-[0.1em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100 sm:tracking-[0.14em]"
                    >
                        Wissen
                    </a>
                    <div class="inline-flex items-center justify-center rounded-full border border-amber-500/40 bg-black/40 px-4 py-2 text-center text-xs uppercase tracking-[0.1em] text-amber-200/80 sm:tracking-[0.14em]">
                        PbP RPG Beta
                    </div>
                </div>
            </header>

            <main class="mx-auto grid w-full max-w-6xl gap-10 break-words px-5 pb-16 pt-2 sm:px-8 lg:grid-cols-2 lg:items-center lg:gap-14 lg:pb-24">
                <section>
                    <p class="mb-4 text-xs uppercase tracking-[0.12em] text-amber-400/80 sm:text-sm sm:tracking-[0.18em]">
                        Dark Fantasy • Asynchron • Community-Driven
                    </p>
                    <h1 class="mb-6 font-heading text-3xl leading-tight text-stone-100 sm:text-5xl lg:text-6xl">
                        Betritt die zersplitterten Reiche von Vhal'Tor
                    </h1>
                    <p class="font-body max-w-xl text-lg leading-relaxed text-stone-300 sm:text-xl">
                        Zwischen verfluchten Dynastien, uralten Blutpforten und flüsternden Ruinen schreiben Spieler und Spielleiter gemeinsame Geschichten. 
                        Jede Entscheidung hinterlässt Narben in der Welt, jeder Würfelwurf kann den Untergang oder die Erlösung bedeuten.
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-4">
                        <a
                            href="{{ $registerUrl }}"
                            class="inline-flex items-center justify-center rounded-md border border-amber-400/70 bg-amber-500/20 px-6 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-amber-100 transition hover:bg-amber-400/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300"
                        >
                            Jetzt mitspielen
                        </a>
                        <a
                            href="#welt"
                            class="inline-flex items-center justify-center rounded-md border border-stone-500/60 px-6 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-stone-200 transition hover:border-stone-300 hover:text-stone-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-stone-200"
                        >
                            Welt entdecken
                        </a>
                    </div>

                    <div class="mt-10 grid grid-cols-1 gap-4 sm:max-w-md sm:grid-cols-2">
                        <article class="rounded-lg border border-stone-700/70 bg-black/35 p-4 backdrop-blur-sm">
                            <h2 class="text-xs uppercase tracking-[0.14em] text-stone-400">Kampagnenstil</h2>
                            <p class="mt-2 text-sm text-stone-200">Asynchrone Story-Threads mit IC/OOC-Tiefe.</p>
                        </article>
                        <article class="rounded-lg border border-stone-700/70 bg-black/35 p-4 backdrop-blur-sm">
                            <h2 class="text-xs uppercase tracking-[0.14em] text-stone-400">System</h2>
                            <p class="mt-2 text-sm text-stone-200">d20-Würfe, Logbuch und narrative Konsequenzen.</p>
                        </article>
                    </div>
                </section>

                <section class="relative">
                    <div class="absolute -inset-4 rounded-2xl bg-gradient-to-br from-amber-500/20 via-transparent to-transparent blur-2xl"></div>
                    <div class="relative overflow-hidden rounded-2xl border border-stone-700/80 bg-black/50 shadow-2xl shadow-black/60">
                        <img
                            src="{{ asset('images/hero-placeholder.svg') }}"
                            alt="Ruinen einer Festung unter rotem Mond"
                            class="h-[22rem] w-full object-cover sm:h-[28rem] lg:max-h-[70vh]"
                            loading="lazy"
                        >
                    </div>
                </section>
            </main>

            <section id="welt" class="mx-auto w-full max-w-6xl px-5 pb-20 sm:px-8">
                <div class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
                    <h2 class="font-heading text-3xl text-stone-100">Welt der letzten Schwüre</h2>
                    <p class="font-body mt-4 max-w-4xl text-lg leading-relaxed text-stone-300">
                        Nach dem Fall der Sonnenkronen regieren Splitterreiche mit kaltem Stahl und verbotener Liturgie. 
                        In den Aschelanden kämpfen Orden, Häretiker und Schattenhäuser um Relikte, die Realität selbst verzerren. 
                        Deine Figur ist kein Zuschauer: Sie knüpft Bündnisse, schreibt Chroniken im Blut und entscheidet, welche Legenden überleben.
                    </p>
                </div>
            </section>

            <footer class="border-t border-stone-800/80 px-5 py-6 text-center text-xs tracking-[0.12em] text-stone-500 sm:px-8">
                Chroniken der Asche • Laravel PbP Plattform
            </footer>
        </div>
    </body>
</html>
