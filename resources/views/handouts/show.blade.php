@extends('layouts.auth')

@section('title', $handout->title.' | Handout')

@section('content')
    <section class="mx-auto w-full max-w-5xl space-y-6">
        <div class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <a href="{{ route('campaigns.handouts.index', ['world' => $campaign->world, 'campaign' => $campaign]) }}" class="text-xs uppercase tracking-widest text-amber-300 hover:text-amber-200">
                Zur Handout-Liste
            </a>

            <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Handout</p>
                    <h1 class="font-heading break-words text-2xl text-stone-100 sm:text-3xl">{{ $handout->title }}</h1>
                    <p class="mt-2 text-xs uppercase tracking-[0.08em] text-stone-500">
                        Erstellt von {{ $handout->creator?->name ?? 'Unbekannt' }}
                        @if ($handout->scene)
                            • Szene: {{ $handout->scene->title }}
                        @endif
                        @if ($handout->version_label)
                            • Version: {{ $handout->version_label }}
                        @endif
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded border px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] {{ $handout->isRevealed() ? 'border-emerald-600/70 bg-emerald-900/20 text-emerald-300' : 'border-amber-600/70 bg-amber-900/20 text-amber-300' }}">
                        {{ $handout->isRevealed() ? 'Freigegeben' : 'Verborgen' }}
                    </span>
                    @if ($handout->sort_order !== null)
                        <span class="rounded border border-stone-600/80 bg-black/40 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-stone-300">
                            Sortierung {{ $handout->sort_order }}
                        </span>
                    @endif
                </div>
            </div>

            @if ($handout->description)
                <article class="mt-6 rounded-xl border border-stone-800 bg-neutral-900/50 p-5">
                    <h2 class="font-heading text-xl text-stone-100">Beschreibung</h2>
                    <div class="mt-3 whitespace-pre-line leading-relaxed text-stone-300">{{ $handout->description }}</div>
                </article>
            @endif

            <div class="mt-6 space-y-3">
                <img
                    src="{{ route('campaigns.handouts.file', ['world' => $campaign->world, 'campaign' => $campaign, 'handout' => $handout]) }}"
                    alt="Handout {{ $handout->title }}"
                    class="w-full rounded-xl border border-stone-700/80 bg-black/30 object-contain"
                >

                <a
                    href="{{ route('campaigns.handouts.file', ['world' => $campaign->world, 'campaign' => $campaign, 'handout' => $handout]) }}"
                    class="inline-flex rounded-md border border-stone-600/80 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                >
                    Datei anzeigen
                </a>
            </div>

            @can('update', $handout)
                <div class="mt-6 flex flex-wrap items-center gap-2">
                    <a
                        href="{{ route('campaigns.handouts.edit', ['world' => $campaign->world, 'campaign' => $campaign, 'handout' => $handout]) }}"
                        class="rounded-md border border-amber-500/70 bg-amber-500/15 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/30"
                    >
                        Bearbeiten
                    </a>

                    @can('reveal', $handout)
                        @if (! $handout->isRevealed())
                            <form method="POST" action="{{ route('campaigns.handouts.reveal', ['world' => $campaign->world, 'campaign' => $campaign, 'handout' => $handout]) }}">
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

                    @can('unreveal', $handout)
                        @if ($handout->isRevealed())
                            <form method="POST" action="{{ route('campaigns.handouts.unreveal', ['world' => $campaign->world, 'campaign' => $campaign, 'handout' => $handout]) }}">
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

                    @can('delete', $handout)
                        <form method="POST" action="{{ route('campaigns.handouts.destroy', ['world' => $campaign->world, 'campaign' => $campaign, 'handout' => $handout]) }}" data-confirm="Handout wirklich löschen?">
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
