@extends('layouts.auth')

@section('title', 'GM Hub | Chroniken der Asche')

@section('content')
    <section class="mx-auto w-full max-w-5xl space-y-6">
        <div class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Game Master Bereich</p>
            <h1 class="font-heading text-3xl text-stone-100">GM Hub</h1>
            <p class="font-body mt-3 text-stone-300">
                Zentrale Werkzeuge fuer Moderation und Verwaltung.
            </p>
        </div>

        <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <h2 class="font-heading text-2xl text-stone-100">Moderations-Queue</h2>
            <p class="mt-3 text-sm text-stone-300">
                Pruefe pending/approved/rejected Posts, filtere die Liste und setze Status direkt mit Quick-Buttons.
            </p>
            <a
                href="{{ route('gm.moderation.index') }}"
                class="mt-5 inline-flex rounded-md border border-amber-500/60 bg-amber-500/15 px-4 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-amber-100 transition hover:bg-amber-500/30"
            >
                Zur Queue
            </a>
        </article>
    </section>
@endsection
