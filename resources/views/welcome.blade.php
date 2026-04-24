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
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="C76-RPG ist eine asynchrone Multi-Welt Play-by-Post Plattform mit Kampagnen, Szenen und Charakterverwaltung.">
        <meta name="theme-color" content="{{ data_get($activeWorldTheme ?? [], 'theme_color', '#0f0f14') }}">
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
    <body
        class="app-shell min-h-full overflow-x-clip bg-neutral-950 text-stone-200 antialiased {{ data_get($activeWorldTheme ?? [], 'body_class') }}"
        data-world-slug="{{ $activeWorldSlug ?? \App\Models\World::defaultSlug() }}"
        data-world-theme="{{ data_get($activeWorldTheme ?? [], 'theme_key', 'default') }}"
    >
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
        @php(
            $heroBannerPath = file_exists(public_path('images/landing/banner_landingpage.png'))
                ? asset('images/landing/banner_landingpage.png')
                : asset('images/og/c76-rpg-og.png')
        )

        <div class="relative isolate overflow-x-clip">
            <div class="app-atmosphere pointer-events-none absolute inset-0 -z-10"></div>

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
                            Anmelden
                        </a>
                    @endauth
                    <div class="ui-badge inline-flex !rounded-full sm:tracking-[0.14em]">
                        Multi-Welt Beta
                    </div>
                </div>
            </header>

            <main class="mx-auto w-full max-w-6xl break-words px-5 pb-16 pt-2 sm:px-8 lg:pb-24">
                <section id="hero" class="landing-hero-shell relative mt-1 overflow-hidden rounded-[1.75rem] border border-stone-700/80 bg-black/35 shadow-2xl shadow-black/35" data-parallax-scene>
                    <figure class="landing-hero-media absolute inset-0 -z-10">
                        <img
                            src="{{ $heroBannerPath }}"
                            alt="Mehrere Figuren vor Portalen in verschiedene Welten: Fantasy, Noir, Gegenwart, Sci-Fi und Postapokalypse"
                            class="landing-hero-banner h-full w-full object-cover object-center"
                            data-parallax-layer
                            data-parallax-depth="0.024"
                            loading="lazy"
                        >
                    </figure>
                    <div class="landing-hero-overlay pointer-events-none absolute inset-0 -z-10" data-parallax-layer data-parallax-depth="0.012"></div>
                    <div class="landing-hero-content grid gap-8 p-6 sm:p-8 lg:p-10 xl:grid-cols-[minmax(0,1fr)_minmax(19rem,0.68fr)] xl:items-end">
                        <div class="landing-hero-text max-w-3xl">
                            <p class="text-xs uppercase tracking-[0.16em] text-amber-300/90 sm:text-sm">
                                Schriftbasiertes Rollenspiel · Play-by-Post
                            </p>

                            <h1 class="mt-3 font-heading text-3xl leading-tight text-stone-100 sm:text-4xl lg:text-5xl">
                                Eine Plattform. Viele Welten. Deine nächste Geschichte.
                            </h1>

                            <p class="mt-4 max-w-2xl text-base leading-relaxed text-stone-200 sm:text-lg">
                                C76-RPG verbindet Fantasy, Noir, Gegenwart, Sci-Fi und Postapokalypse
                                in asynchronen Kampagnen für Einsteiger und erfahrene Spieler.
                            </p>

                            <ul class="landing-hero-points mt-5 grid gap-2 text-sm text-stone-200 sm:text-base">
                                <li>Starte ohne Vorerfahrung mit kurzen Beiträgen.</li>
                                <li>Wähle eine Welt, tritt einer Szene bei, schreibe den nächsten Satz.</li>
                                <li>Ein Regelkern, mehrere Genres, gemeinsames Geschichtenerleben.</li>
                            </ul>

                            <div class="landing-hero-actions mt-7 flex flex-wrap items-center gap-3 sm:gap-4">
                                @guest
                                    <a href="{{ $registerUrl }}" class="ui-btn ui-btn-accent landing-hero-cta-primary inline-flex px-6 py-3 text-sm">
                                        Jetzt starten
                                    </a>
                                @else
                                    <a href="{{ route('dashboard') }}" class="ui-btn ui-btn-success landing-hero-cta-primary inline-flex px-6 py-3 text-sm">
                                        Jetzt starten
                                    </a>
                                @endguest
                                <a href="#wie-funktionierts" class="ui-btn landing-hero-cta-secondary inline-flex px-6 py-3 text-sm">
                                    So funktioniert’s
                                </a>
                                <a href="#welten" class="ui-btn landing-hero-cta-tertiary inline-flex px-6 py-3 text-sm">
                                    Welten entdecken
                                </a>
                            </div>
                        </div>

                        <aside class="landing-hero-worlds hidden rounded-2xl border border-stone-600/70 bg-black/42 p-4 shadow-xl shadow-black/35 backdrop-blur-sm lg:block">
                            <p class="text-xs uppercase tracking-[0.13em] text-amber-300/90">Multi-World Fokus</p>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs uppercase tracking-[0.08em] text-stone-200">
                                <span class="ui-badge">Düstere Fantasy</span>
                                <span class="ui-badge">Klassische Fantasy</span>
                                <span class="ui-badge">Noir & Ermittlungen</span>
                                <span class="ui-badge">Gegenwart</span>
                                <span class="ui-badge">Sci-Fi</span>
                                <span class="ui-badge">Postapokalypse</span>
                            </div>
                            <p class="mt-3 text-sm leading-relaxed text-stone-300">
                                Eine Startseite, viele Genre-Türen.
                            </p>
                        </aside>
                    </div>
                </section>

                <section id="kurzintro" class="mt-8 rounded-2xl border border-stone-800 bg-black/35 p-5 sm:p-6">
                    <p class="text-xs uppercase tracking-[0.12em] text-amber-300/80">Kurzintro</p>
                    <h2 class="mt-2 font-heading text-2xl text-stone-100 sm:text-3xl">Gemeinsam Geschichten erleben</h2>
                    <p class="mt-3 max-w-3xl text-sm leading-relaxed text-stone-300 sm:text-base">
                        C76-RPG ist eine asynchrone Play-by-Post-Plattform für mehrere Welten.
                        Du steigst in laufende Szenen ein und schreibst die Geschichte weiter.
                    </p>
                    <a href="{{ route('knowledge.global.index') }}" class="ui-btn mt-4 inline-flex">Wissenszentrum öffnen</a>
                </section>

                <section id="was-ist-rpg" class="mt-8">
                    <p class="text-xs uppercase tracking-[0.12em] text-amber-300/80">Für Einsteiger</p>
                    <h2 class="mt-2 font-heading text-2xl text-stone-100 sm:text-3xl">Was ist RPG?</h2>
                    <div class="mt-4 grid gap-3 md:grid-cols-3">
                        <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">
                            <h3 class="font-heading text-lg text-stone-100">Gemeinsames Erzählen</h3>
                            <p class="mt-2 text-sm leading-relaxed text-stone-300">Mehrere Figuren treiben dieselbe Handlung voran.</p>
                        </article>
                        <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">
                            <h3 class="font-heading text-lg text-stone-100">Rolle statt Zuschauer</h3>
                            <p class="mt-2 text-sm leading-relaxed text-stone-300">Du schreibst aus Sicht deiner Figur und reagierst auf andere.</p>
                        </article>
                        <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">
                            <h3 class="font-heading text-lg text-stone-100">Asynchron</h3>
                            <p class="mt-2 text-sm leading-relaxed text-stone-300">Du brauchst keinen festen Termin, nur einen nächsten Beitrag.</p>
                        </article>
                    </div>
                </section>

                <section id="wie-funktionierts" class="mt-8 rounded-2xl border border-stone-800 bg-black/35 p-5 sm:p-6">
                    <p class="text-xs uppercase tracking-[0.12em] text-amber-300/80">Ablauf</p>
                    <h2 class="mt-2 font-heading text-2xl text-stone-100 sm:text-3xl">Wie funktioniert C76-RPG?</h2>
                    <ol class="mt-4 grid gap-3 text-sm leading-relaxed text-stone-300 md:grid-cols-2">
                        <li class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">1. Welt auswählen und Kontext lesen.</li>
                        <li class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">2. Figur erstellen oder vorhandene Figur nutzen.</li>
                        <li class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">3. In eine Szene einsteigen und IC posten.</li>
                        <li class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">4. Auf Reaktionen antworten und den Thread weiterführen.</li>
                    </ol>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('knowledge.global.how-to-play') }}" class="ui-btn ui-btn-accent inline-flex">Schnellstart ansehen</a>
                        <a href="{{ route('knowledge.global.rules') }}" class="ui-btn inline-flex">Regelwerk öffnen</a>
                    </div>
                </section>

                <section id="welten" class="mt-8">
                    <div class="landing-worlds-intro rounded-2xl border border-stone-800 bg-black/35 p-5 sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="max-w-3xl">
                                <p class="text-xs uppercase tracking-[0.12em] text-amber-300/80">Multi-World</p>
                                <h2 class="mt-2 font-heading text-2xl text-stone-100 sm:text-3xl">Viele Welten, ein gemeinsames Dach</h2>
                                <p class="mt-3 text-sm leading-relaxed text-stone-300 sm:text-base">
                                    C76-RPG vereint unterschiedliche Erzählstile auf einer Plattform:
                                    Jede Welt hat ihren Ton, aber Einstieg und Zusammenarbeit bleiben vertraut.
                                </p>
                            </div>
                            <a href="{{ route('worlds.index') }}" class="ui-btn inline-flex">Alle Welten</a>
                        </div>

                        <ul class="landing-world-genre-strip mt-4 flex flex-wrap gap-2">
                            <li><span class="landing-world-genre-chip">Düstere Fantasy</span></li>
                            <li><span class="landing-world-genre-chip">Klassische Abenteuer</span></li>
                            <li><span class="landing-world-genre-chip">Ermittlungen und graue Wahrheiten</span></li>
                            <li><span class="landing-world-genre-chip">Geschichten im Hier und Jetzt</span></li>
                            <li><span class="landing-world-genre-chip">Sterne und synthetische Konflikte</span></li>
                            <li><span class="landing-world-genre-chip">Fragile Ordnung nach dem Zusammenbruch</span></li>
                        </ul>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3" data-parallax-scene>
                        @forelse ($worlds as $world)
                            @php($worldTagline = trim((string) ($world->tagline ?? '')))
                            @php($descriptionSource = trim((string) ($world->description ?? '')))
                            @php($fallbackSnippet = $worldSnippets[$loop->index % count($worldSnippets)])
                            @php($teaserSource = $descriptionSource !== '' ? $descriptionSource : $fallbackSnippet)
                            @php($worldSnippet = \Illuminate\Support\Str::limit($teaserSource, 180))
                            <article class="landing-world-card relative rounded-2xl border border-stone-800 bg-neutral-900/65 p-5 shadow-xl shadow-black/25 transition duration-300 hover:-translate-y-0.5 hover:border-amber-600/60 hover:bg-neutral-900/80" data-parallax-layer data-parallax-depth="0.018">
                                <div class="flex items-start justify-between gap-3">
                                    <h3 class="font-heading text-2xl text-stone-100">{{ $world->name }}</h3>
                                    <span class="landing-world-campaigns ui-badge whitespace-nowrap">{{ $world->campaigns_count }} Kampagnen</span>
                                </div>

                                @if ($worldTagline !== '')
                                    <p class="mt-2 text-sm text-amber-200">{{ \Illuminate\Support\Str::limit($worldTagline, 110) }}</p>
                                @endif

                                <p class="landing-world-snippet mt-3 text-sm leading-relaxed text-stone-300">{{ $worldSnippet }}</p>

                                <div class="mt-5 flex flex-wrap gap-2">
                                    <a href="{{ route('worlds.show', ['world' => $world]) }}" class="ui-btn ui-btn-accent inline-flex">Welt entdecken</a>
                                    <a href="{{ route('campaigns.index', ['world' => $world]) }}" class="ui-btn inline-flex">Kampagnen ansehen</a>
                                </div>
                            </article>
                        @empty
                            <article class="rounded-2xl border border-amber-700/60 bg-amber-900/20 p-5 text-amber-100">
                                Aktuell sind keine aktiven Welten verfügbar.
                            </article>
                        @endforelse
                    </div>
                </section>

                <section id="einstieg" class="mt-8 rounded-2xl border border-stone-800 bg-black/35 p-5 sm:p-6">
                    <p class="text-xs uppercase tracking-[0.12em] text-amber-300/80">Startpfad</p>
                    <h2 class="mt-2 font-heading text-2xl text-stone-100 sm:text-3xl">Einstieg in 3 Schritten</h2>
                    <ol class="mt-4 grid gap-3 text-sm leading-relaxed text-stone-300 md:grid-cols-3">
                        <li class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">1. Konto anlegen und erste Welt wählen.</li>
                        <li class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">2. Kurz den Szenenkontext lesen.</li>
                        <li class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">3. Deinen ersten IC-Satz posten.</li>
                    </ol>
                </section>

                <section id="warum-schriftbasiert" class="mt-8">
                    <p class="text-xs uppercase tracking-[0.12em] text-amber-300/80">Warum Play-by-Post?</p>
                    <h2 class="mt-2 font-heading text-2xl text-stone-100 sm:text-3xl">Warum schriftbasiert?</h2>
                    <ul class="mt-4 grid gap-3 text-sm leading-relaxed text-stone-300 md:grid-cols-3">
                        <li class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">Mehr Zeit für gute Szenen statt Echtzeitdruck.</li>
                        <li class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">Besserer Überblick über Verlauf, Figuren und Konsequenzen.</li>
                        <li class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">Ideal für gemeinsames Storytelling über mehrere Welten.</li>
                    </ul>
                </section>

                <section id="faq-anfaenger" class="mt-8 rounded-2xl border border-stone-800 bg-black/35 p-5 sm:p-6">
                    <p class="text-xs uppercase tracking-[0.12em] text-amber-300/80">FAQ</p>
                    <h2 class="mt-2 font-heading text-2xl text-stone-100 sm:text-3xl">FAQ für Anfänger</h2>
                    <div class="mt-4 grid gap-3 text-sm leading-relaxed text-stone-300 md:grid-cols-2">
                        <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">
                            <h3 class="font-heading text-lg text-stone-100">Brauche ich Erfahrung?</h3>
                            <p class="mt-2">Nein. Du kannst mit kurzen Beiträgen starten und dich Schritt für Schritt einfinden.</p>
                        </article>
                        <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">
                            <h3 class="font-heading text-lg text-stone-100">Wie viel Zeit braucht es?</h3>
                            <p class="mt-2">Du schreibst asynchron. Ein Beitrag dauert oft nur wenige Minuten.</p>
                        </article>
                        <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">
                            <h3 class="font-heading text-lg text-stone-100">Wo beginne ich am besten?</h3>
                            <p class="mt-2">Am schnellsten geht es über den geführten Einstieg im Wissenszentrum.</p>
                        </article>
                        <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">
                            <h3 class="font-heading text-lg text-stone-100">Was ist IC und OOC?</h3>
                            <p class="mt-2">IC ist Spieltext in der Figur. OOC ist kurze Abstimmung außerhalb der Szene.</p>
                        </article>
                    </div>
                    <a href="{{ route('knowledge.global.index') }}" class="ui-btn mt-4 inline-flex">Mehr im Wissenszentrum</a>
                </section>

                <section id="finaler-cta" class="mt-8 rounded-2xl border border-amber-700/45 bg-amber-900/10 p-6 sm:p-7">
                    <p class="text-xs uppercase tracking-[0.12em] text-amber-300/85">Dein Platz in der Geschichte</p>
                    <h2 class="mt-2 font-heading text-2xl text-stone-100 sm:text-3xl">Starte mit deinem nächsten Satz.</h2>
                    <div class="mt-5 flex flex-wrap items-center gap-3 sm:gap-4">
                        @guest
                            <a href="{{ $registerUrl }}" class="ui-btn ui-btn-accent inline-flex px-6 py-3 text-sm">
                                Jetzt starten
                            </a>
                        @else
                            <a href="{{ route('dashboard') }}" class="ui-btn ui-btn-success inline-flex px-6 py-3 text-sm">
                                Jetzt starten
                            </a>
                        @endguest
                        <a href="#wie-funktionierts" class="ui-btn inline-flex px-6 py-3 text-sm">
                            So funktioniert’s
                        </a>
                        <a href="#welten" class="ui-btn ui-btn-danger inline-flex px-6 py-3 text-sm">
                            Welten entdecken
                        </a>
                    </div>
                </section>
            </main>

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
