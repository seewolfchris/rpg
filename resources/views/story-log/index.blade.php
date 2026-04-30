@extends('layouts.auth')

@section('title', $campaign->title.' | Chronik')

@section('content')
    <section class="mx-auto w-full max-w-6xl space-y-6">
        <div class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Kampagnen-Navigation</p>
                    <h1 class="font-heading break-words text-2xl text-stone-100 sm:text-3xl">Chronik</h1>
                    <p class="mt-2 text-sm text-stone-300">Wichtige Ereignisse, Kapitel und Zusammenfassungen dieser Kampagne.</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @can('create', [\App\Models\StoryLogEntry::class, $campaign])
                        <a
                            href="{{ route('campaigns.story-log.create', ['world' => $campaign->world, 'campaign' => $campaign]) }}"
                            class="rounded-md border border-amber-400/70 bg-amber-500/20 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-400/30"
                        >
                            Eintrag erstellen
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

        @if ($storyLogEntries->isEmpty())
            <section class="rounded-2xl border border-stone-800 bg-black/45 p-6 text-sm text-stone-400 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
                @can('create', [\App\Models\StoryLogEntry::class, $campaign])
                    <p>Noch keine Chronik-Einträge vorhanden.</p>
                    <a
                        href="{{ route('campaigns.story-log.create', ['world' => $campaign->world, 'campaign' => $campaign]) }}"
                        class="mt-3 inline-flex rounded-md border border-amber-400/70 bg-amber-500/20 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-400/30"
                    >
                        Eintrag erstellen
                    </a>
                @else
                    <p>Noch keine sichtbaren Chronik-Einträge.</p>
                    <p class="mt-2 text-xs text-stone-500">Sobald die Spielleitung Ereignisse freigibt, erscheinen sie hier.</p>
                @endcan
            </section>
        @else
            <section class="space-y-3">
                @foreach ($storyLogEntries as $storyLogEntry)
                    <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="font-heading break-words text-lg text-stone-100">{{ $storyLogEntry->title }}</h2>
                            @if ($storyLogEntry->isRevealed())
                                <span class="rounded border border-emerald-600/70 bg-emerald-900/20 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-emerald-300">
                                    Freigegeben
                                </span>
                            @elseif ($canManage)
                                <span class="rounded border border-amber-600/70 bg-amber-900/20 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-amber-300">
                                    Verborgen für Spieler
                                </span>
                            @endif
                        </div>

                        <p class="mt-1 text-xs uppercase tracking-[0.08em] text-stone-500">
                            @if ($storyLogEntry->scene)
                                Szene: {{ $storyLogEntry->scene->title }}
                            @else
                                Kampagnenweit
                            @endif
                            @if ($storyLogEntry->sort_order !== null)
                                • Sortierung {{ $storyLogEntry->sort_order }}
                            @endif
                        </p>

                        @if ($storyLogEntry->body)
                            <p class="mt-2 line-clamp-4 whitespace-pre-line text-sm text-stone-300">{{ $storyLogEntry->body }}</p>
                        @endif

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <a
                                href="{{ route('campaigns.story-log.show', ['world' => $campaign->world, 'campaign' => $campaign, 'storyLogEntry' => $storyLogEntry]) }}"
                                class="rounded-md border border-stone-600/80 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                            >
                                Öffnen
                            </a>
                            @can('update', $storyLogEntry)
                                <a
                                    href="{{ route('campaigns.story-log.edit', ['world' => $campaign->world, 'campaign' => $campaign, 'storyLogEntry' => $storyLogEntry]) }}"
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
                {{ $storyLogEntries->links() }}
            </div>
        @endif
    </section>
@endsection
