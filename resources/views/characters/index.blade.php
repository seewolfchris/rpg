@extends('layouts.auth')

@section('title', 'Charaktere | Chroniken der Asche')

@section('content')
    <section class="mx-auto w-full max-w-6xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Deine Chroniken</p>
                <h1 class="font-heading text-3xl text-stone-100">Charaktere</h1>
                <p class="mt-2 text-stone-300">Verwalte deine Figuren, Werte und Biografien.</p>
            </div>

            <a
                href="{{ route('characters.create') }}"
                class="rounded-md border border-amber-400/70 bg-amber-500/20 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-amber-100 transition hover:bg-amber-400/30"
            >
                Neuer Charakter
            </a>
        </div>

        @if ($characters->isEmpty())
            <div class="rounded-xl border border-stone-800 bg-black/45 p-8 text-center text-stone-300">
                Noch keine Charaktere vorhanden.
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($characters as $character)
                    <article class="overflow-hidden rounded-xl border border-stone-800 bg-neutral-900/65 shadow-lg shadow-black/30">
                        <img
                            src="{{ $character->avatarUrl() }}"
                            alt="Portraet von {{ $character->name }}"
                            class="h-48 w-full object-cover"
                            loading="lazy"
                        >

                        <div class="space-y-3 p-4">
                            <div>
                                <h2 class="font-heading text-xl text-stone-100">{{ $character->name }}</h2>
                                @if ($character->epithet)
                                    <p class="text-sm text-amber-300/90">{{ $character->epithet }}</p>
                                @endif
                            </div>

                            <div class="grid grid-cols-3 gap-2 text-xs text-stone-300">
                                <div class="rounded border border-stone-700/80 bg-black/40 px-2 py-1">STR {{ $character->strength }}</div>
                                <div class="rounded border border-stone-700/80 bg-black/40 px-2 py-1">DEX {{ $character->dexterity }}</div>
                                <div class="rounded border border-stone-700/80 bg-black/40 px-2 py-1">CON {{ $character->constitution }}</div>
                                <div class="rounded border border-stone-700/80 bg-black/40 px-2 py-1">INT {{ $character->intelligence }}</div>
                                <div class="rounded border border-stone-700/80 bg-black/40 px-2 py-1">WIS {{ $character->wisdom }}</div>
                                <div class="rounded border border-stone-700/80 bg-black/40 px-2 py-1">CHA {{ $character->charisma }}</div>
                            </div>

                            <a
                                href="{{ route('characters.show', $character) }}"
                                class="inline-flex rounded-md border border-stone-600/80 px-4 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                            >
                                Details
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>

            <div>
                {{ $characters->links() }}
            </div>
        @endif
    </section>
@endsection
