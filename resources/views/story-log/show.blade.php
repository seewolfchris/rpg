@extends('layouts.auth')

@section('title', $storyLogEntry->title.' | Chronik')

@section('content')
    <section class="mx-auto w-full max-w-5xl space-y-6">
        <div class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <a href="{{ route('campaigns.story-log.index', ['world' => $campaign->world, 'campaign' => $campaign]) }}" class="text-xs uppercase tracking-widest text-amber-300 hover:text-amber-200">
                Zur Chronik
            </a>

            <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Chronik-Eintrag</p>
                    <h1 class="font-heading break-words text-2xl text-stone-100 sm:text-3xl">{{ $storyLogEntry->title }}</h1>
                    <p class="mt-2 text-xs uppercase tracking-[0.08em] text-stone-500">
                        @if ($storyLogEntry->scene)
                            Szene: {{ $storyLogEntry->scene->title }}
                        @else
                            Kampagnenweit
                        @endif
                        @if ($storyLogEntry->sort_order !== null)
                            • Sortierung {{ $storyLogEntry->sort_order }}
                        @endif
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @if ($storyLogEntry->isRevealed())
                        <span class="rounded border border-emerald-600/70 bg-emerald-900/20 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-emerald-300">
                            Freigegeben
                        </span>
                    @else
                        <span class="rounded border border-amber-600/70 bg-amber-900/20 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-amber-300">
                            @can('update', $storyLogEntry)
                                Verborgen für Spieler
                            @else
                                Verborgen
                            @endcan
                        </span>
                    @endif
                </div>
            </div>

            @if ($storyLogEntry->body)
                <article class="mt-6 rounded-xl border border-stone-800 bg-neutral-900/50 p-5">
                    <div class="whitespace-pre-line leading-relaxed text-stone-300">{{ $storyLogEntry->body }}</div>
                </article>
            @endif

            @can('update', $storyLogEntry)
                <div class="mt-6 flex flex-wrap items-center gap-2">
                    <a
                        href="{{ route('campaigns.story-log.edit', ['world' => $campaign->world, 'campaign' => $campaign, 'storyLogEntry' => $storyLogEntry]) }}"
                        class="rounded-md border border-amber-500/70 bg-amber-500/15 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/30"
                    >
                        Bearbeiten
                    </a>

                    @can('reveal', $storyLogEntry)
                        @if (! $storyLogEntry->isRevealed())
                            <form method="POST" action="{{ route('campaigns.story-log.reveal', ['world' => $campaign->world, 'campaign' => $campaign, 'storyLogEntry' => $storyLogEntry]) }}">
                                @csrf
                                @method('PATCH')
                                <button
                                    type="submit"
                                    class="rounded-md border border-emerald-600/70 bg-emerald-900/20 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-emerald-200 transition hover:bg-emerald-900/35"
                                >
                                    Freigeben
                                </button>
                            </form>
                        @endif
                    @endcan

                    @can('unreveal', $storyLogEntry)
                        @if ($storyLogEntry->isRevealed())
                            <form method="POST" action="{{ route('campaigns.story-log.unreveal', ['world' => $campaign->world, 'campaign' => $campaign, 'storyLogEntry' => $storyLogEntry]) }}">
                                @csrf
                                @method('PATCH')
                                <button
                                    type="submit"
                                    class="rounded-md border border-amber-600/70 bg-amber-900/20 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-amber-200 transition hover:bg-amber-900/35"
                                >
                                    Verbergen
                                </button>
                            </form>
                        @endif
                    @endcan

                    @can('delete', $storyLogEntry)
                        <form method="POST" action="{{ route('campaigns.story-log.destroy', ['world' => $campaign->world, 'campaign' => $campaign, 'storyLogEntry' => $storyLogEntry]) }}" data-confirm="Chronik-Eintrag wirklich löschen?">
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
