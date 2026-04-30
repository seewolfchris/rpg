@extends('layouts.auth')

@section('title', $campaign->title.' | Meine Notizen')

@section('content')
    <section class="mx-auto w-full max-w-6xl space-y-6">
        <div class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Kampagnen-Navigation</p>
                    <h1 class="font-heading break-words text-2xl text-stone-100 sm:text-3xl">Meine Notizen</h1>
                    <p class="mt-2 text-sm text-stone-300">Private Notizen zu dieser Kampagne. Nur du kannst sie sehen.</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @can('create', [\App\Models\PlayerNote::class, $campaign])
                        <a
                            href="{{ route('campaigns.player-notes.create', ['world' => $campaign->world, 'campaign' => $campaign]) }}"
                            class="rounded-md border border-amber-400/70 bg-amber-500/20 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-400/30"
                        >
                            Notiz erstellen
                        </a>
                    @endcan
                    <a
                        href="{{ route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]) }}"
                        class="rounded-md border border-stone-600/80 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                    >
                        Zur Kampagne
                    </a>
                </div>
            </div>
        </div>

        @if ($playerNotes->isEmpty())
            <section class="rounded-2xl border border-stone-800 bg-black/45 p-6 text-sm text-stone-400 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
                <p>Noch keine Notizen vorhanden.</p>
                <p class="mt-2 text-xs text-stone-500">Lege private Gedanken, Hinweise oder offene Fragen zu dieser Kampagne ab.</p>
                @can('create', [\App\Models\PlayerNote::class, $campaign])
                    <a
                        href="{{ route('campaigns.player-notes.create', ['world' => $campaign->world, 'campaign' => $campaign]) }}"
                        class="mt-3 inline-flex rounded-md border border-amber-400/70 bg-amber-500/20 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-400/30"
                    >
                        Notiz erstellen
                    </a>
                @endcan
            </section>
        @else
            <section class="space-y-3">
                @foreach ($playerNotes as $playerNote)
                    <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="font-heading break-words text-lg text-stone-100">{{ $playerNote->title }}</h2>
                            <span class="rounded border border-indigo-600/70 bg-indigo-900/20 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-indigo-200">
                                Privat
                            </span>
                        </div>

                        <p class="mt-1 text-xs uppercase tracking-[0.08em] text-stone-500">
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

                        @if ($playerNote->body)
                            <p class="mt-2 line-clamp-4 whitespace-pre-line text-sm text-stone-300">{{ $playerNote->body }}</p>
                        @endif

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <a
                                href="{{ route('campaigns.player-notes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'playerNote' => $playerNote]) }}"
                                class="rounded-md border border-stone-600/80 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                            >
                                Öffnen
                            </a>
                            @can('update', $playerNote)
                                <a
                                    href="{{ route('campaigns.player-notes.edit', ['world' => $campaign->world, 'campaign' => $campaign, 'playerNote' => $playerNote]) }}"
                                    class="rounded-md border border-amber-500/70 bg-amber-500/15 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/30"
                                >
                                    Bearbeiten
                                </a>
                            @endcan
                        </div>
                    </article>
                @endforeach
            </section>

            <div>
                {{ $playerNotes->links() }}
            </div>
        @endif
    </section>
@endsection
