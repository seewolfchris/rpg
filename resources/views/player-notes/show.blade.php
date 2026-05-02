@extends('layouts.auth')

@section('title', $playerNote->title.' | Meine Notizen')

@section('content')
    <section class="mx-auto w-full max-w-5xl space-y-6">
        <div class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <x-navigation.back-link :href="$backUrl" label="Zurück" />

            <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Notiz</p>
                    <h1 class="font-heading break-words text-2xl text-stone-100 sm:text-3xl">{{ $playerNote->title }}</h1>
                    <p class="mt-2 text-xs uppercase tracking-[0.08em] text-stone-500">
                        @if ($playerNote->scene)
                            Szene: {{ $playerNote->scene->title }}
                        @else
                            Kampagne
                        @endif
                        @if ($playerNote->character)
                            • Charakter: {{ $playerNote->character->name }}
                        @endif
                        @if ($playerNote->sort_order !== null)
                            • Sortierung {{ $playerNote->sort_order }}
                        @endif
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded border border-indigo-600/70 bg-indigo-900/20 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-indigo-200">
                        Privat
                    </span>
                    <span class="rounded border border-stone-600/70 bg-black/30 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-stone-300">
                        Nur für dich sichtbar
                    </span>
                </div>
            </div>

            @if ($playerNote->body)
                <article class="mt-6 rounded-xl border border-stone-800 bg-neutral-900/50 p-5">
                    <div class="whitespace-pre-line leading-relaxed text-stone-300">{{ $playerNote->body }}</div>
                </article>
            @endif

            @can('update', $playerNote)
                <div class="mt-6 flex flex-wrap items-center gap-2">
                    <a
                        href="{{ route('campaigns.player-notes.edit', [
                            'world' => $campaign->world,
                            'campaign' => $campaign,
                            'playerNote' => $playerNote,
                            'return_to' => is_string($returnTo) ? $returnTo : null,
                        ]) }}"
                        class="rounded-md border border-amber-500/70 bg-amber-500/15 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/30"
                    >
                        Bearbeiten
                    </a>

                    @can('delete', $playerNote)
                        <form method="POST" action="{{ route('campaigns.player-notes.destroy', ['world' => $campaign->world, 'campaign' => $campaign, 'playerNote' => $playerNote]) }}" data-confirm="Notiz wirklich löschen?">
                            @csrf
                            @method('DELETE')
                            <button
                                type="submit"
                                class="rounded-md border border-red-700/80 bg-red-900/20 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-red-200 transition hover:bg-red-900/40"
                            >
                                Löschen
                            </button>
                        </form>
                    @endcan
                </div>
            @endcan
        </div>
    </section>
@endsection
