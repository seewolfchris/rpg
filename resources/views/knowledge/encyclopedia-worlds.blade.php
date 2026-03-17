@extends('layouts.auth')

@section('title', 'Enzyklopädie · Weltenauswahl')

@section('content')
    <section class="mx-auto w-full max-w-6xl space-y-6">
        <header class="relative overflow-hidden rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_20%_10%,rgba(168,85,39,0.26),transparent_42%),radial-gradient(circle_at_75%_30%,rgba(127,29,29,0.32),transparent_40%),linear-gradient(to_bottom,rgba(17,17,17,0.96),rgba(8,8,8,0.98))]"></div>

            <p class="text-xs uppercase tracking-[0.14em] text-amber-400/80">Wissenszentrum</p>
            <h1 class="mt-2 font-heading text-3xl text-stone-100 sm:text-4xl">Enzyklopädie je Welt</h1>
            <p class="mt-4 max-w-4xl text-base leading-relaxed text-[#cccccc] sm:text-lg">
                Weltwissen ist strikt getrennt. Waehle zuerst eine Welt, um ihre Enzyklopädie zu öffnen.
            </p>
        </header>

        @include('knowledge._nav')

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($worlds as $catalogWorld)
                @php($isSelected = $selectedWorldSlug !== '' && $selectedWorldSlug === $catalogWorld->slug)
                <article class="rounded-2xl border {{ $isSelected ? 'border-amber-500/70 bg-amber-900/15' : 'border-stone-800 bg-neutral-900/60' }} p-5">
                    <h2 class="font-heading text-2xl text-stone-100">{{ $catalogWorld->name }}</h2>
                    @if ($catalogWorld->tagline)
                        <p class="mt-2 text-sm text-amber-200">{{ $catalogWorld->tagline }}</p>
                    @endif
                    <p class="mt-3 text-sm text-stone-300">
                        {{ $catalogWorld->description ?: 'Keine Beschreibung hinterlegt.' }}
                    </p>
                    <p class="mt-3 text-xs uppercase tracking-widest text-stone-500">
                        {{ $catalogWorld->campaigns_count }} Kampagnen
                    </p>

                    @if ($isSelected)
                        <p class="mt-3 text-xs font-semibold uppercase tracking-widest text-amber-300">
                            Aktive Welt in dieser Session
                        </p>
                    @endif

                    <div class="mt-4 flex flex-wrap gap-2">
                        <a
                            href="{{ route('knowledge.encyclopedia', ['world' => $catalogWorld]) }}"
                            class="ui-btn ui-btn-accent inline-flex"
                        >
                            Enzyklopädie öffnen
                        </a>
                        <a
                            href="{{ route('knowledge.index', ['world' => $catalogWorld]) }}"
                            class="ui-btn inline-flex"
                        >
                            Weltwissen
                        </a>
                        <form method="POST" action="{{ route('worlds.activate', ['world' => $catalogWorld]) }}">
                            @csrf
                            <button type="submit" class="ui-btn inline-flex">
                                Welt aktivieren
                            </button>
                        </form>
                    </div>
                </article>
            @endforeach
        </section>
    </section>
@endsection
