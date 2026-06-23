@extends('layouts.auth')

@section('title', 'Übersicht | C76-RPG')

@section('content')
    <section class="ui-page-wide space-y-6">
        <div class="ui-card p-6 sm:p-8">
            <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Sichere Zuflucht</p>
            <h1 class="font-heading break-words text-2xl text-stone-100 sm:text-3xl">Willkommen, {{ auth()->user()->name }}</h1>
            <p class="font-body mt-3 text-lg text-stone-300">
                Dein Konto ist aktiv. Verwalte Charaktere, baue Kampagnen und sammle Ruhmpunkte für freigegebene Beiträge.
            </p>
            <p class="ui-badge mt-4 !rounded-md !border-amber-500/50 !bg-amber-500/10 !px-3 !py-1.5 !text-amber-100">
                Ruhmpunkte: {{ auth()->user()->points }}
            </p>
            @if ($canAccessModerationQueue)
                <p class="ui-badge mt-3 !rounded-md !border-red-700/60 !bg-red-900/20 !px-3 !py-1.5 !text-red-200">
                    Ausstehende Moderation: {{ $pendingModerationCount }}
                </p>
            @endif
        </div>

        @include('dashboard.partials.next-step')

        <section class="ui-card p-6 sm:p-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.14em] text-amber-400/80">Weltenkontext</p>
                    <h2 class="mt-1 font-heading text-2xl text-stone-100">Aktive Welt</h2>
                    <p class="mt-2 text-sm text-stone-300">{{ $selectedWorld?->name ?? 'Keine Welt ausgewählt' }}</p>
                    @if ($selectedWorld)
                        <x-world-marker
                            class="mt-3"
                            :world-name="$selectedWorld->name"
                            :marker-label="(string) data_get($activeWorldTheme ?? [], 'marker_label', '')"
                            :marker-symbol="(string) data_get($activeWorldTheme ?? [], 'marker_symbol', '')"
                            :marker-bg="(string) data_get($activeWorldTheme ?? [], 'marker_bg', '')"
                            :marker-fg="(string) data_get($activeWorldTheme ?? [], 'marker_fg', '')"
                            :marker-border="(string) data_get($activeWorldTheme ?? [], 'marker_border', '')"
                        />
                    @endif
                </div>
                <x-ui.action :href="route('worlds.index')" variant="accent">Welten wechseln</x-ui.action>
            </div>
        </section>

        @php($tutorialTotal = max(count($tutorialSteps), 1))
        @php($tutorialProgress = (int) round(($tutorialCompletedCount / $tutorialTotal) * 100))
        <details
            class="dashboard-disclosure ui-card overflow-hidden"
            data-dashboard-section="tutorial"
            @if ($tutorialCompletedCount < $tutorialTotal) open @endif
        >
            <summary class="flex min-h-14 flex-wrap items-center gap-3 px-6 py-5 sm:px-8">
                <div>
                    <p class="text-xs uppercase tracking-[0.14em] text-amber-400/80">Tutorial im Spiel</p>
                    <h2 class="mt-1 font-heading text-2xl text-stone-100">Erste Schritte</h2>
                    <p class="mt-2 text-sm text-stone-300">
                        Fortschritt: {{ $tutorialCompletedCount }} / {{ $tutorialTotal }} abgeschlossen
                    </p>
                </div>
            </summary>

            <div class="border-t border-stone-800 px-6 pb-6 pt-5 sm:px-8 sm:pb-8">
                <div class="flex justify-end">
                    <x-ui.action :href="route('knowledge.index', ['world' => $selectedWorld])">
                        Wissenszentrum
                    </x-ui.action>
                </div>
                <div class="mt-4 h-2 w-full rounded-full bg-stone-800">
                    <div
                        class="h-2 rounded-full bg-gradient-to-r from-amber-400 to-amber-600 transition-all duration-300"
                        style="width: {{ max(0, min($tutorialProgress, 100)) }}%;"
                    ></div>
                </div>

                <ol class="mt-5 space-y-3">
                    @foreach ($tutorialSteps as $step)
                        <li class="ui-card-soft flex flex-wrap items-start justify-between gap-3 px-4 py-3">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="{{ $step['done'] ? 'border-emerald-500/80 bg-emerald-500/20 text-emerald-200' : 'border-stone-600/80 bg-stone-800/70 text-stone-300' }} inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border text-xs font-semibold uppercase">
                                    {{ $step['done'] ? 'ok' : $loop->iteration }}
                                </span>
                                <div>
                                    <p class="text-sm font-semibold text-stone-100">{{ $step['title'] }}</p>
                                    <p class="mt-1 text-xs leading-relaxed text-stone-400">{{ $step['description'] }}</p>
                                </div>
                            </div>
                            <x-ui.action :href="$step['url']" variant="accent" class="shrink-0">
                                {{ $step['cta'] }}
                            </x-ui.action>
                        </li>
                    @endforeach
                </ol>
            </div>
        </details>

        <details class="dashboard-disclosure ui-card overflow-hidden" data-dashboard-section="quick-access">
            <summary class="flex min-h-14 items-center gap-3 px-6 py-5 sm:px-8">
                <div>
                    <p class="text-xs uppercase tracking-[0.14em] text-amber-400/80">Navigation</p>
                    <h2 class="mt-1 font-heading text-2xl text-stone-100">Weitere Bereiche</h2>
                </div>
            </summary>
            <div class="grid gap-4 border-t border-stone-800 p-6 md:grid-cols-5 sm:p-8">
                <article class="ui-card-soft p-4">
                    <h3 class="font-heading text-lg text-stone-100">Charaktere</h3>
                    <p class="mt-2 text-sm text-stone-300">Mehrere Figuren pro Nutzer inkl. Eigenschaften, Biografie und Porträt.</p>
                    <x-ui.action :href="route('characters.index')" variant="accent" class="mt-4">Verwalten</x-ui.action>
                </article>
                <article class="ui-card-soft p-4">
                    <h3 class="font-heading text-lg text-stone-100">Kampagnen</h3>
                    <p class="mt-2 text-sm text-stone-300">Asynchrone IC/OOC-Szenen mit Änderungshistorie.</p>
                    <x-ui.action :href="route('campaigns.index', ['world' => $selectedWorld])" variant="accent" class="mt-4">Öffnen</x-ui.action>
                </article>
                <article class="ui-card-soft p-4">
                    <h3 class="font-heading text-lg text-stone-100">SL-Proben</h3>
                    <p class="mt-2 text-sm text-stone-300">d100-Proben laufen nur über SL-Beiträge: Anlass, Ziel-Held, Modifikator und Ergebnisblock inklusive.</p>
                </article>
                <article class="ui-card-soft p-4">
                    <h3 class="font-heading text-lg text-stone-100">Rangliste</h3>
                    <p class="mt-2 text-sm text-stone-300">Sieh deinen Rang und die aktivsten Chronisten.</p>
                    <x-ui.action :href="route('leaderboard.index')" variant="accent" class="mt-4">Öffnen</x-ui.action>
                </article>
                <article class="ui-card-soft p-4 !border-amber-700/40 !bg-amber-900/10">
                    <h3 class="font-heading text-lg text-amber-100">Ungelesene Szenen</h3>
                    <p class="mt-2 text-sm text-amber-200">{{ $unreadSceneCount }} mit neuen Beiträgen.</p>
                    <p class="mt-1 text-xs uppercase tracking-[0.08em] text-amber-300">Lesezeichen: {{ $bookmarkCount }}</p>
                    <x-ui.action :href="route('scene-subscriptions.index', ['world' => $selectedWorld])" variant="accent" class="mt-4">
                        Zur Abo-Übersicht
                    </x-ui.action>
                    <x-ui.action :href="route('bookmarks.index', ['world' => $selectedWorld])" variant="success" class="mt-2">
                        Zu Lesezeichen
                    </x-ui.action>
                </article>
            </div>
        </details>

        <details class="dashboard-disclosure ui-card overflow-hidden" data-dashboard-section="leaderboard">
            <summary class="flex min-h-14 items-center gap-3 px-6 py-5 sm:px-8">
                <div>
                    <p class="text-xs uppercase tracking-[0.14em] text-amber-400/80">Rangliste</p>
                    <h2 class="mt-1 font-heading text-2xl text-stone-100">Top-Chronisten</h2>
                </div>
            </summary>
            <div class="border-t border-stone-800 p-6 sm:p-8">
                @if ($topPlayers->isEmpty())
                    <p class="text-sm text-stone-400">Noch keine Punkte gesammelt.</p>
                @else
                    <ol class="space-y-2">
                        @foreach ($topPlayers as $rank => $topPlayer)
                            <li class="ui-card-soft flex items-center justify-between px-4 py-2">
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
            </div>
        </details>
    </section>
@endsection
