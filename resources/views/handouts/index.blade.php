@extends('layouts.auth')

@section('title', $campaign->title.' | Handouts')

@section('content')
    <section class="mx-auto w-full max-w-6xl space-y-6">
        <div class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Kampagnen-Referenzen</p>
                    <h1 class="font-heading break-words text-2xl text-stone-100 sm:text-3xl">Handouts</h1>
                    <p class="mt-2 text-sm text-stone-300">Kampagne: {{ $campaign->title }}</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @can('create', [\App\Models\Handout::class, $campaign])
                        <a
                            href="{{ route('campaigns.handouts.create', ['world' => $campaign->world, 'campaign' => $campaign]) }}"
                            class="rounded-md border border-amber-400/70 bg-amber-500/20 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-400/30"
                        >
                            Handout anlegen
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

        @if ($handouts->isEmpty())
            <section class="rounded-2xl border border-stone-800 bg-black/45 p-6 text-sm text-stone-400 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
                @can('create', [\App\Models\Handout::class, $campaign])
                    <p>Noch keine Handouts vorhanden.</p>
                    <a
                        href="{{ route('campaigns.handouts.create', ['world' => $campaign->world, 'campaign' => $campaign]) }}"
                        class="mt-3 inline-flex rounded-md border border-amber-400/70 bg-amber-500/20 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-400/30"
                    >
                        Handout anlegen
                    </a>
                @else
                    <p>Noch keine sichtbaren Handouts.</p>
                    <p class="mt-2 text-xs text-stone-500">Sobald die Spielleitung Karten, Briefe oder Hinweise freigibt, erscheinen sie hier.</p>
                @endcan
            </section>
        @else
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($handouts as $handout)
                    @php($isRevealed = $handout->revealed_at !== null)
                    @php($hasFile = $handout->relationLoaded('media')
                        ? $handout->media->where('collection_name', \App\Models\Handout::HANDOUT_FILE_COLLECTION)->isNotEmpty()
                        : $handout->getFirstMedia(\App\Models\Handout::HANDOUT_FILE_COLLECTION) !== null)
                    <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">
                        @if ($hasFile)
                            <img
                                src="{{ route('campaigns.handouts.file', ['world' => $campaign->world, 'campaign' => $campaign, 'handout' => $handout]) }}"
                                alt="Handout {{ $handout->title }}"
                                loading="lazy"
                                class="h-44 w-full rounded-lg border border-stone-700/80 bg-black/35 object-cover"
                            >
                        @endif

                        <div class="mt-3 flex items-start justify-between gap-2">
                            <h2 class="font-heading text-lg text-stone-100">{{ $handout->title }}</h2>
                            <span class="rounded border px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] {{ $isRevealed ? 'border-emerald-600/70 bg-emerald-900/20 text-emerald-300' : 'border-amber-600/70 bg-amber-900/20 text-amber-300' }}">
                                {{ $isRevealed ? 'Freigegeben' : 'Verborgen' }}
                            </span>
                        </div>

                        @if ($handout->scene)
                            <p class="mt-1 text-xs uppercase tracking-[0.08em] text-stone-500">Szene: {{ $handout->scene->title }}</p>
                        @endif

                        @if ($handout->description)
                            <p class="mt-2 line-clamp-4 text-sm text-stone-300">{{ $handout->description }}</p>
                        @endif

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <a
                                href="{{ route('campaigns.handouts.show', ['world' => $campaign->world, 'campaign' => $campaign, 'handout' => $handout]) }}"
                                class="rounded-md border border-stone-600/80 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                            >
                                Öffnen
                            </a>

                            @can('update', $handout)
                                <a
                                    href="{{ route('campaigns.handouts.edit', ['world' => $campaign->world, 'campaign' => $campaign, 'handout' => $handout]) }}"
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
                {{ $handouts->links() }}
            </div>
        @endif
    </section>
@endsection
