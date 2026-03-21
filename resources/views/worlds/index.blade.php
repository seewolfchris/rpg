@extends('layouts.auth')

@section('title', 'Welten | C76-RPG')

@section('meta_description', 'Waehle eine Spielwelt fuer Kampagnen, Szenen und Wissen in C76-RPG.')

@section('content')
    <section class="ui-card p-6 sm:p-8">
        <p class="text-xs uppercase tracking-[0.14em] text-amber-300/80">C76-RPG</p>
        <h1 class="mt-2 font-heading text-3xl text-stone-100 sm:text-4xl">Betrete eine Welt</h1>
        <p class="mt-3 max-w-3xl text-base leading-relaxed text-stone-300 sm:text-lg">
            Wähle den Schauplatz für deine nächste Szene und steige direkt in Kampagnen,
            Threads und das passende Wissenszentrum ein.
        </p>
    </section>

    <section class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($worlds as $world)
            <article class="ui-card-soft rounded-2xl border border-stone-800/85 bg-neutral-900/65 p-5 shadow-lg shadow-black/25 transition duration-300 hover:-translate-y-0.5 hover:border-amber-600/55">
                <h2 class="font-heading break-words text-2xl text-stone-100">{{ $world->name }}</h2>
                @if ($world->tagline)
                    <p class="mt-2 break-words text-sm leading-relaxed text-amber-200">{{ $world->tagline }}</p>
                @endif
                <p class="mt-3 break-words text-sm leading-relaxed text-stone-300 sm:text-base">
                    {{ $world->description ?: 'Noch keine Beschreibung hinterlegt.' }}
                </p>
                <p class="mt-4 text-xs uppercase tracking-[0.1em] text-stone-500">
                    {{ $world->campaigns_count }} Kampagnen
                </p>

                <div class="mt-5 flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('worlds.activate', ['world' => $world]) }}">
                        @csrf
                        <button type="submit" class="ui-btn ui-btn-accent inline-flex">
                            Welt betreten
                        </button>
                    </form>
                    <a href="{{ route('worlds.show', ['world' => $world]) }}" class="ui-btn inline-flex">
                        Weltprofil
                    </a>
                </div>
            </article>
        @empty
            <article class="rounded-2xl border border-amber-700/60 bg-amber-900/20 p-5 text-amber-100">
                Keine aktiven Welten gefunden.
            </article>
        @endforelse
    </section>
@endsection
