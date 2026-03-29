@extends('layouts.auth')

@section('title', 'Wissenszentrum · C76-RPG')

@section('content')
    @php
        $isWorldContext = isset($world) && $world instanceof \App\Models\World;
        $howToPlayUrl = $isWorldContext
            ? route('knowledge.how-to-play', ['world' => $world])
            : route('knowledge.global.how-to-play');
        $rulesUrl = $isWorldContext
            ? route('knowledge.rules', ['world' => $world])
            : route('knowledge.global.rules');
        $encyclopediaUrl = $isWorldContext
            ? route('knowledge.encyclopedia', ['world' => $world])
            : route('knowledge.global.encyclopedia');
    @endphp

    <section class="mx-auto w-full max-w-6xl space-y-6">
        <header class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <p class="text-xs uppercase tracking-[0.14em] text-amber-400/80">Wissenszentrum</p>
            @if ($isWorldContext)
                <h1 class="mt-2 font-heading text-3xl text-stone-100 sm:text-4xl">Leitfaden für Spiel, Welt und Regeln</h1>
                <p class="mt-4 max-w-4xl text-base leading-relaxed text-stone-300 sm:text-lg">
                    Strukturierter Einstieg für die Welt <strong class="text-amber-200">{{ $world->name }}</strong>:
                    Wie Play-by-Post hier funktioniert, welche Regeln gelten und wie du dich schnell orientierst.
                </p>
            @else
                <h1 class="mt-2 font-heading text-3xl text-stone-100 sm:text-4xl">Plattformwissen für C76-RPG</h1>
                <p class="mt-4 max-w-4xl text-base leading-relaxed text-stone-300 sm:text-lg">
                    Dieser Bereich ist weltunabhängig. Hier findest du den allgemeinen Einstieg, grundlegende Regeln
                    und den Zugang zu weltgebundenem Wissen.
                </p>
            @endif
        </header>

        @include('knowledge._nav')

        <section class="grid gap-4 lg:grid-cols-3">
            <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-5">
                <p class="text-xs uppercase tracking-widest text-amber-300">Einsteigerpfad</p>
                <h2 class="mt-2 font-heading text-xl text-stone-100">Wie spielt man?</h2>
                <p class="mt-3 text-sm leading-relaxed text-stone-300">
                    In 7 Schritten von der ersten Anmeldung bis zum ersten IC-Post in Ich-Perspektive.
                </p>
                <a
                    href="{{ $howToPlayUrl }}"
                    class="mt-4 inline-flex rounded-md border border-amber-500/70 bg-amber-500/20 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/30"
                >
                    Einstieg öffnen
                </a>
            </article>

            <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-5">
                <p class="text-xs uppercase tracking-widest text-amber-300">System</p>
                <h2 class="mt-2 font-heading text-xl text-stone-100">Regelwerk</h2>
                <p class="mt-3 text-sm leading-relaxed text-stone-300">
                    IC/OOC-Konventionen, Posting-Standards, Prozentproben (d100), Moderation und Spoiler-Richtlinien.
                </p>
                <a
                    href="{{ $rulesUrl }}"
                    class="mt-4 inline-flex rounded-md border border-amber-500/70 bg-amber-500/20 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/30"
                >
                    Regeln lesen
                </a>
            </article>

            <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-5">
                <p class="text-xs uppercase tracking-widest text-amber-300">{{ $isWorldContext ? 'Lore' : 'Weltenwissen' }}</p>
                <h2 class="mt-2 font-heading text-xl text-stone-100">{{ $isWorldContext ? 'Enzyklopädie' : 'Welten & Enzyklopädien' }}</h2>
                <p class="mt-3 text-sm leading-relaxed text-stone-300">
                    {{ $isWorldContext
                        ? 'Kategorien, Fraktionen, Regionen und Begriffe der ausgewählten Welt.'
                        : 'Weltwissen ist getrennt je Welt. Wähle eine Welt, um deren Enzyklopädie und Regelkontext zu öffnen.' }}
                </p>
                <a
                    href="{{ $encyclopediaUrl }}"
                    class="mt-4 inline-flex rounded-md border border-amber-500/70 bg-amber-500/20 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/30"
                >
                    {{ $isWorldContext ? 'Welt erkunden' : 'Welt auswählen' }}
                </a>
            </article>
        </section>

        <section class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <h2 class="font-heading text-2xl text-stone-100">Empfohlener Start für neue Spieler</h2>
            <ol class="mt-5 space-y-3 text-sm leading-relaxed text-stone-300">
                <li>1. Lies zuerst <strong>Wie spielt man?</strong> komplett durch.</li>
                <li>2. Erstelle einen Charakter mit klarer Motivation und Grenzen.</li>
                <li>3. Nimm dir danach nur die Regelwerk-Abschnitte zu IC/OOC und Prozentproben vor.</li>
                <li>4. {{ $isWorldContext ? 'Nutze die Enzyklopädie als Nachschlagewerk während des Schreibens.' : 'Wähle dann eine Welt, bevor du weltbezogene Inhalte oder Kampagnen öffnest.' }}</li>
                <li>5. Schreibe den ersten IC-Post kurz, konkret und in Ich-Perspektive.</li>
            </ol>
        </section>

        @if (! $isWorldContext)
            <section class="rounded-2xl border border-stone-800 bg-black/35 p-6 shadow-xl shadow-black/30 sm:p-8">
                <h2 class="font-heading text-2xl text-stone-100">Weltenbezogenes Wissen</h2>
                <p class="mt-3 max-w-4xl text-sm leading-relaxed text-stone-300">
                    Diese Bereiche sind bewusst getrennt. Jede Welt hat eigene Enzyklopädie-Einträge, Kampagnen und Kontext.
                </p>

                <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($worlds as $catalogWorld)
                        @php($isSelected = $selectedWorldSlug !== '' && $selectedWorldSlug === $catalogWorld->slug)
                        <article class="rounded-xl border {{ $isSelected ? 'border-amber-500/70 bg-amber-900/15' : 'border-stone-800 bg-neutral-900/60' }} p-4">
                            <h3 class="font-heading text-xl text-stone-100">{{ $catalogWorld->name }}</h3>
                            @if ($catalogWorld->tagline)
                                <p class="mt-2 text-sm text-amber-200">{{ $catalogWorld->tagline }}</p>
                            @endif
                            <p class="mt-2 text-sm text-stone-300">
                                {{ $catalogWorld->description ?: 'Keine Beschreibung hinterlegt.' }}
                            </p>

                            @if ($isSelected)
                                <p class="mt-3 text-xs font-semibold uppercase tracking-widest text-amber-300">
                                    Aktive Welt in dieser Session
                                </p>
                            @endif

                            <div class="mt-4 flex flex-wrap gap-2">
                                <a href="{{ route('knowledge.index', ['world' => $catalogWorld]) }}" class="ui-btn inline-flex">
                                    Weltwissen
                                </a>
                                <a href="{{ route('knowledge.encyclopedia', ['world' => $catalogWorld]) }}" class="ui-btn ui-btn-accent inline-flex">
                                    Enzyklopädie
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    </section>
@endsection
