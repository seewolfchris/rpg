@extends('layouts.auth')

@section('title', $character->name.' | Charakter')

@section('content')
    <section class="mx-auto w-full max-w-5xl space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Charakterblatt</p>
                <h1 class="font-heading break-words text-2xl text-stone-100 sm:text-3xl">{{ $character->name }}</h1>
                @if ($character->epithet)
                    <p class="mt-1 break-words text-lg text-amber-300/90">{{ $character->epithet }}</p>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a
                    href="{{ route('characters.edit', $character) }}"
                    class="rounded-md border border-stone-600/80 px-4 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                >
                    Bearbeiten
                </a>
                <form method="POST" action="{{ route('characters.destroy', $character) }}" onsubmit="return confirm('Diesen Charakter wirklich loeschen?');">
                    @csrf
                    @method('DELETE')
                    <button
                        type="submit"
                        class="rounded-md border border-red-700/80 bg-red-900/20 px-4 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-red-200 transition hover:bg-red-900/40"
                    >
                        Loeschen
                    </button>
                </form>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[20rem_1fr]">
            <aside class="space-y-4 rounded-xl border border-stone-800 bg-neutral-900/70 p-4">
                <img
                    src="{{ $character->avatarUrl() }}"
                    alt="Portraet von {{ $character->name }}"
                    class="h-72 w-full rounded-lg object-cover"
                >

                <div class="grid grid-cols-2 gap-2 text-sm text-stone-200">
                    <div class="rounded border border-stone-700/80 bg-black/35 px-3 py-2">STR: {{ $character->strength }}</div>
                    <div class="rounded border border-stone-700/80 bg-black/35 px-3 py-2">DEX: {{ $character->dexterity }}</div>
                    <div class="rounded border border-stone-700/80 bg-black/35 px-3 py-2">CON: {{ $character->constitution }}</div>
                    <div class="rounded border border-stone-700/80 bg-black/35 px-3 py-2">INT: {{ $character->intelligence }}</div>
                    <div class="rounded border border-stone-700/80 bg-black/35 px-3 py-2">WIS: {{ $character->wisdom }}</div>
                    <div class="rounded border border-stone-700/80 bg-black/35 px-3 py-2">CHA: {{ $character->charisma }}</div>
                </div>
            </aside>

            <article class="rounded-xl border border-stone-800 bg-black/45 p-6">
                <h2 class="font-heading text-2xl text-stone-100">Biografie</h2>
                <div class="mt-4 whitespace-pre-line leading-relaxed text-stone-300">{{ $character->bio }}</div>
            </article>
        </div>

        <a
            href="{{ route('characters.index') }}"
            class="inline-flex rounded-md border border-stone-600/80 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
        >
            Zurueck zur Uebersicht
        </a>
    </section>
@endsection
