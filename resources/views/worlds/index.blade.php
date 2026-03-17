@extends('layouts.auth')

@section('title', 'Welten | C76-RPG')

@section('meta_description', 'Waehle eine Spielwelt fuer Kampagnen, Szenen und Wissen in C76-RPG.')

@section('content')
    <section class="rounded-2xl border border-stone-800 bg-neutral-900/60 p-6 shadow-xl shadow-black/25">
        <p class="text-xs uppercase tracking-widest text-amber-300/80">C76-RPG</p>
        <h1 class="mt-2 font-heading text-3xl text-stone-100 sm:text-4xl">Weltenauswahl</h1>
        <p class="mt-3 max-w-3xl text-stone-300">
            Waehle eine Welt und starte direkt in Kampagnen, Szenen und das passende Wissenszentrum.
        </p>
    </section>

    <section class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($worlds as $world)
            <article class="rounded-2xl border border-stone-800 bg-neutral-900/60 p-5">
                <h2 class="font-heading text-2xl text-stone-100">{{ $world->name }}</h2>
                @if ($world->tagline)
                    <p class="mt-2 text-sm text-amber-200">{{ $world->tagline }}</p>
                @endif
                <p class="mt-3 text-sm text-stone-300">
                    {{ $world->description ?: 'Noch keine Beschreibung hinterlegt.' }}
                </p>
                <p class="mt-4 text-xs uppercase tracking-widest text-stone-500">
                    {{ $world->campaigns_count }} Kampagnen
                </p>

                <div class="mt-5 flex flex-wrap gap-2">
                    <a href="{{ route('worlds.show', ['world' => $world]) }}" class="ui-btn ui-btn-accent inline-flex">
                        Welt ansehen
                    </a>
                    <form method="POST" action="{{ route('worlds.activate', ['world' => $world]) }}">
                        @csrf
                        <button type="submit" class="ui-btn inline-flex">
                            Welt aktivieren
                        </button>
                    </form>
                </div>
            </article>
        @empty
            <article class="rounded-2xl border border-amber-700/60 bg-amber-900/20 p-5 text-amber-100">
                Keine aktiven Welten gefunden.
            </article>
        @endforelse
    </section>
@endsection
