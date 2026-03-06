@extends('layouts.auth')

@section('title', 'Wissenszentrum · Chroniken der Asche')

@section('content')
    <section class="mx-auto w-full max-w-6xl space-y-6">
        <header class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <p class="text-xs uppercase tracking-[0.14em] text-amber-400/80">Wissenszentrum</p>
            <h1 class="mt-2 font-heading text-3xl text-stone-100 sm:text-4xl">Leitfaden für Spiel, Welt und Regeln</h1>
            <p class="mt-4 max-w-4xl text-base leading-relaxed text-stone-300 sm:text-lg">
                Hier findest du den strukturierten Einstieg für Chroniken der Asche: wie das Play-by-Post funktioniert,
                welche Regeln im Alltag gelten und wie die Welt Vhal'Tor aufgebaut ist.
            </p>
        </header>

        @include('knowledge._nav')

        <section class="grid gap-4 lg:grid-cols-3">
            <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-5">
                <p class="text-xs uppercase tracking-[0.1em] text-amber-300">Einsteigerpfad</p>
                <h2 class="mt-2 font-heading text-xl text-stone-100">Wie spielt man?</h2>
                <p class="mt-3 text-sm leading-relaxed text-stone-300">
                    In 7 Schritten vom ersten Login bis zum ersten IC-Post in Ich-Perspektive.
                </p>
                <a
                    href="{{ route('knowledge.how-to-play') }}"
                    class="mt-4 inline-flex rounded-md border border-amber-500/70 bg-amber-500/20 px-3 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-amber-100 transition hover:bg-amber-500/30"
                >
                    Einstieg öffnen
                </a>
            </article>

            <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-5">
                <p class="text-xs uppercase tracking-[0.1em] text-amber-300">System</p>
                <h2 class="mt-2 font-heading text-xl text-stone-100">Regelwerk</h2>
                <p class="mt-3 text-sm leading-relaxed text-stone-300">
                    IC/OOC-Konventionen, Posting-Standards, Prozentproben (d100), Moderation und Spoiler-Richtlinien.
                </p>
                <a
                    href="{{ route('knowledge.rules') }}"
                    class="mt-4 inline-flex rounded-md border border-amber-500/70 bg-amber-500/20 px-3 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-amber-100 transition hover:bg-amber-500/30"
                >
                    Regeln lesen
                </a>
            </article>

            <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-5">
                <p class="text-xs uppercase tracking-[0.1em] text-amber-300">Lore</p>
                <h2 class="mt-2 font-heading text-xl text-stone-100">Enzyklopädie</h2>
                <p class="mt-3 text-sm leading-relaxed text-stone-300">
                    Zeitalter, Fraktionen, Regionen und Begriffe der Welt der letzten Schwüre.
                </p>
                <a
                    href="{{ route('knowledge.encyclopedia') }}"
                    class="mt-4 inline-flex rounded-md border border-amber-500/70 bg-amber-500/20 px-3 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-amber-100 transition hover:bg-amber-500/30"
                >
                    Welt erkunden
                </a>
            </article>
        </section>

        <section class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <h2 class="font-heading text-2xl text-stone-100">Empfohlener Start für neue Spieler</h2>
            <ol class="mt-5 space-y-3 text-sm leading-relaxed text-stone-300">
                <li>1. Lies zuerst <strong>Wie spielt man?</strong> komplett durch.</li>
                <li>2. Erstelle einen Charakter mit klarer Motivation und Grenzen.</li>
                <li>3. Nimm dir danach nur die Regelwerk-Abschnitte zu IC/OOC und Prozentproben vor.</li>
                <li>4. Nutze die Enzyklopädie als Nachschlagewerk während des Schreibens.</li>
                <li>5. Schreibe den ersten IC-Post kurz, konkret und in Ich-Perspektive.</li>
            </ol>
        </section>
    </section>
@endsection
