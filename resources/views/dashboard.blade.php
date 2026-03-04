@extends('layouts.auth')

@section('title', 'Dashboard | Chroniken der Asche')

@section('content')
    <section class="mx-auto w-full max-w-4xl space-y-6">
        <div class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Sichere Zuflucht</p>
            <h1 class="font-heading break-words text-2xl text-stone-100 sm:text-3xl">Willkommen, {{ auth()->user()->name }}</h1>
            <p class="font-body mt-3 text-lg text-stone-300">
                Dein Konto ist aktiv. Verwalte Charaktere, baue Kampagnen und sammle Ruhmpunkte fuer freigegebene Posts.
            </p>
            <p class="mt-4 inline-flex rounded-md border border-amber-500/50 bg-amber-500/10 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.1em] text-amber-100">
                Ruhmpunkte: {{ auth()->user()->points }}
            </p>
            @if (auth()->user()->isGmOrAdmin())
                <p class="mt-3 inline-flex rounded-md border border-red-700/60 bg-red-900/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.1em] text-red-200">
                    Pending Moderation: {{ $pendingModerationCount }}
                </p>
            @endif
        </div>

        @php($tutorialTotal = max(count($tutorialSteps), 1))
        @php($tutorialProgress = (int) round(($tutorialCompletedCount / $tutorialTotal) * 100))
        <section class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.14em] text-amber-400/80">In-App Tutorial</p>
                    <h2 class="mt-1 font-heading text-2xl text-stone-100">Erste Schritte</h2>
                    <p class="mt-2 text-sm text-stone-300">
                        Fortschritt: {{ $tutorialCompletedCount }} / {{ $tutorialTotal }} abgeschlossen
                    </p>
                </div>
                <a
                    href="{{ route('knowledge.index') }}"
                    class="inline-flex rounded-md border border-stone-600/80 px-3 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                >
                    Wissenszentrum
                </a>
            </div>
            <div class="mt-4 h-2 w-full rounded-full bg-stone-800">
                <div
                    class="h-2 rounded-full bg-gradient-to-r from-amber-400 to-amber-600 transition-all duration-300"
                    style="width: {{ max(0, min($tutorialProgress, 100)) }}%;"
                ></div>
            </div>

            <ol class="mt-5 space-y-3">
                @foreach ($tutorialSteps as $step)
                    <li class="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-stone-800 bg-neutral-900/60 px-4 py-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="{{ $step['done'] ? 'border-emerald-500/80 bg-emerald-500/20 text-emerald-200' : 'border-stone-600/80 bg-stone-800/70 text-stone-300' }} inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border text-xs font-semibold uppercase">
                                {{ $step['done'] ? 'ok' : $loop->iteration }}
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-stone-100">{{ $step['title'] }}</p>
                                <p class="mt-1 text-xs leading-relaxed text-stone-400">{{ $step['description'] }}</p>
                            </div>
                        </div>
                        <a
                            href="{{ $step['url'] }}"
                            class="inline-flex shrink-0 rounded-md border border-amber-500/60 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.1em] text-amber-100 transition hover:bg-amber-500/20"
                        >
                            {{ $step['cta'] }}
                        </a>
                    </li>
                @endforeach
            </ol>
        </section>

        <div class="grid gap-4 md:grid-cols-5">
            <article class="rounded-xl border border-stone-800 bg-neutral-900/65 p-4">
                <h2 class="font-heading text-lg text-stone-100">Charaktere</h2>
                <p class="mt-2 text-sm text-stone-300">Mehrere Figuren pro User inkl. Stats, Bio und Portraet.</p>
                <a
                    href="{{ route('characters.index') }}"
                    class="mt-4 inline-flex rounded-md border border-amber-500/70 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.1em] text-amber-100 transition hover:bg-amber-500/20"
                >
                    Verwalten
                </a>
            </article>
            <article class="rounded-xl border border-stone-800 bg-neutral-900/65 p-4">
                <h2 class="font-heading text-lg text-stone-100">Kampagnen</h2>
                <p class="mt-2 text-sm text-stone-300">Asynchrone IC/OOC-Szenen mit Edit-History.</p>
                <a
                    href="{{ route('campaigns.index') }}"
                    class="mt-4 inline-flex rounded-md border border-amber-500/70 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.1em] text-amber-100 transition hover:bg-amber-500/20"
                >
                    Oeffnen
                </a>
            </article>
            <article class="rounded-xl border border-stone-800 bg-neutral-900/65 p-4">
                <h2 class="font-heading text-lg text-stone-100">Wuerfel</h2>
                <p class="mt-2 text-sm text-stone-300">d100-Proben mit transparentem Ergebnis-Log.</p>
            </article>
            <article class="rounded-xl border border-stone-800 bg-neutral-900/65 p-4">
                <h2 class="font-heading text-lg text-stone-100">Rangliste</h2>
                <p class="mt-2 text-sm text-stone-300">Sieh deinen Rang und die aktivsten Chronisten.</p>
                <a
                    href="{{ route('leaderboard.index') }}"
                    class="mt-4 inline-flex rounded-md border border-amber-500/70 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.1em] text-amber-100 transition hover:bg-amber-500/20"
                >
                    Oeffnen
                </a>
            </article>
            <article class="rounded-xl border border-amber-700/40 bg-amber-900/10 p-4">
                <h2 class="font-heading text-lg text-amber-100">Ungelesene Szenen</h2>
                <p class="mt-2 text-sm text-amber-200">{{ $unreadSceneCount }} mit neuen Beitraegen.</p>
                <p class="mt-1 text-xs uppercase tracking-[0.08em] text-amber-300">Bookmarks: {{ $bookmarkCount }}</p>
                <a
                    href="{{ route('scene-subscriptions.index') }}"
                    class="mt-4 inline-flex rounded-md border border-amber-500/70 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.1em] text-amber-100 transition hover:bg-amber-500/20"
                >
                    Zur Abo-Uebersicht
                </a>
                <a
                    href="{{ route('bookmarks.index') }}"
                    class="mt-2 inline-flex rounded-md border border-emerald-600/70 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.1em] text-emerald-200 transition hover:bg-emerald-900/20"
                >
                    Zu Bookmarks
                </a>
            </article>
        </div>

        <section class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <h2 class="font-heading text-2xl text-stone-100">Top-Chronisten</h2>
            @if ($topPlayers->isEmpty())
                <p class="mt-3 text-sm text-stone-400">Noch keine Punkte gesammelt.</p>
            @else
                <ol class="mt-4 space-y-2">
                    @foreach ($topPlayers as $rank => $topPlayer)
                        <li class="flex items-center justify-between rounded-lg border border-stone-800 bg-neutral-900/60 px-4 py-2">
                            <p class="text-sm text-stone-200">
                                <span class="font-semibold text-amber-200">#{{ $rank + 1 }}</span>
                                {{ $topPlayer->name }}
                                @if ($topPlayer->id === auth()->id())
                                    <span class="ml-2 text-xs uppercase tracking-[0.08em] text-amber-300">du</span>
                                @endif
                            </p>
                            <p class="text-sm font-semibold text-amber-200">{{ $topPlayer->points }} Punkte</p>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </section>
@endsection
