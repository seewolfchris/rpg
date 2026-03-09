@extends('layouts.auth')

@section('title', $entry->title.' · Enzyklopädie')

@section('content')
    <section class="mx-auto w-full max-w-5xl space-y-6">
        <header class="relative overflow-hidden rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_20%_10%,rgba(168,85,39,0.26),transparent_42%),radial-gradient(circle_at_75%_30%,rgba(127,29,29,0.32),transparent_40%),linear-gradient(to_bottom,rgba(17,17,17,0.96),rgba(8,8,8,0.98))]"></div>

            <p class="text-xs uppercase tracking-[0.14em] text-amber-400/80">Wissenszentrum · Enzyklopädie</p>
            <h1 class="mt-2 font-heading text-3xl text-stone-100 sm:text-4xl">{{ $entry->title }}</h1>

            <div class="mt-4 flex flex-wrap items-center gap-2 text-xs uppercase tracking-widest text-stone-400">
                <span class="rounded-full border border-stone-700/80 bg-black/45 px-2 py-1">{{ $entry->category->name }}</span>
                @if ($entry->published_at)
                    <span class="rounded-full border border-stone-700/80 bg-black/45 px-2 py-1">Stand {{ $entry->published_at->translatedFormat('d.m.Y') }}</span>
                @endif
            </div>

            @if ($entry->excerpt)
                <p class="mt-4 max-w-3xl text-sm leading-relaxed text-amber-100/90 sm:text-base">{{ $entry->excerpt }}</p>
            @endif

            <div class="mt-5 flex flex-wrap gap-3">
                <a
                    href="{{ route('knowledge.encyclopedia', ['k' => $entry->category->slug]) }}"
                    class="rounded-md border border-stone-600/80 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                >
                    Zur Kategorie
                </a>
                <a
                    href="{{ route('knowledge.encyclopedia') }}"
                    class="rounded-md border border-amber-500/70 bg-amber-500/20 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/30"
                >
                    Alle Einträge
                </a>
                @if ($canManage)
                    <a
                        href="{{ route('knowledge.admin.kategorien.eintraege.edit', [$entry->category, $entry]) }}"
                        class="rounded-md border border-red-500/60 bg-red-900/20 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-red-100 transition hover:bg-red-900/35"
                    >
                        Admin-Bearbeitung
                    </a>
                @endif
            </div>
        </header>

        @include('knowledge._nav')

        @if ($crossLinks !== [])
            <section class="rounded-2xl border border-stone-800 bg-black/35 p-5 shadow-xl shadow-black/25">
                <h2 class="font-heading text-xl text-stone-100">Querverlinkungen</h2>
                <p class="mt-1 text-sm text-stone-300">Direkte Verweise aus diesem Eintrag in andere Wissensartikel.</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($crossLinks as $crossLink)
                        <a
                            href="{{ $crossLink['url'] }}"
                            class="inline-flex rounded-md border border-stone-700/80 bg-black/35 px-3 py-2 text-xs font-semibold uppercase tracking-[0.08em] text-stone-200 transition hover:border-amber-500/70 hover:text-amber-100"
                        >
                            {{ $crossLink['label'] }}
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if (is_array($entry->game_relevance) && $entry->game_relevance !== [])
            <section class="rounded-2xl border border-red-900/60 bg-red-950/20 p-5 shadow-xl shadow-black/30">
                <h2 class="font-heading text-xl text-red-100">Spielrelevanz</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @if (!empty($entry->game_relevance['le_hint']))
                        <article class="rounded-lg border border-red-900/60 bg-black/35 p-3">
                            <p class="text-xs font-semibold uppercase tracking-widest text-red-200">LE</p>
                            <p class="mt-1 text-sm leading-relaxed text-stone-200">{{ $entry->game_relevance['le_hint'] }}</p>
                        </article>
                    @endif

                    @if (!empty($entry->game_relevance['rs_hint']))
                        <article class="rounded-lg border border-red-900/60 bg-black/35 p-3">
                            <p class="text-xs font-semibold uppercase tracking-widest text-red-200">RS</p>
                            <p class="mt-1 text-sm leading-relaxed text-stone-200">{{ $entry->game_relevance['rs_hint'] }}</p>
                        </article>
                    @endif

                    @if (!empty($entry->game_relevance['ae_hint']))
                        <article class="rounded-lg border border-red-900/60 bg-black/35 p-3">
                            <p class="text-xs font-semibold uppercase tracking-widest text-red-200">AE</p>
                            <p class="mt-1 text-sm leading-relaxed text-stone-200">{{ $entry->game_relevance['ae_hint'] }}</p>
                        </article>
                    @endif

                    @if (!empty($entry->game_relevance['probe_hint']))
                        <article class="rounded-lg border border-red-900/60 bg-black/35 p-3">
                            <p class="text-xs font-semibold uppercase tracking-widest text-red-200">GM-Proben</p>
                            <p class="mt-1 text-sm leading-relaxed text-stone-200">{{ $entry->game_relevance['probe_hint'] }}</p>
                        </article>
                    @endif

                    @if (!empty($entry->game_relevance['real_world_hint']))
                        <article class="rounded-lg border border-red-900/60 bg-black/35 p-3 sm:col-span-2">
                            <p class="text-xs font-semibold uppercase tracking-widest text-red-200">Real-World-Hinweis</p>
                            <p class="mt-1 text-sm leading-relaxed text-stone-200">{{ $entry->game_relevance['real_world_hint'] }}</p>
                        </article>
                    @endif
                </div>
            </section>
        @endif

        <section class="rounded-2xl border border-stone-800 bg-black/35 p-5 shadow-xl shadow-black/25">
            <h2 class="font-heading text-xl text-stone-100">Bild-Prompt-Vorschläge</h2>
            <p class="mt-1 text-sm text-stone-300">
                Für Midjourney, SDXL oder ähnliche Tools. Prompts als Ausgangspunkt nutzen und bei Bedarf feinjustieren.
            </p>
            <div class="mt-4 space-y-3">
                @foreach ($imagePrompts as $prompt)
                    <article class="rounded-lg border border-stone-700/80 bg-black/35 p-3">
                        <p class="font-mono text-xs leading-relaxed text-stone-200">{{ $prompt }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <article class="rounded-2xl border border-stone-800 bg-zinc-900 p-6 shadow-2xl">
            <div class="knowledge-content text-[#cccccc]">
                {{ $renderedContent }}
            </div>
        </article>

        @if ($relatedEntries->isNotEmpty())
            <section class="rounded-2xl border border-stone-800 bg-black/35 p-5 shadow-xl shadow-black/25">
                <h2 class="font-heading text-xl text-stone-100">Weitere Einträge in {{ $entry->category->name }}</h2>
                <ul class="mt-4 grid gap-2 sm:grid-cols-2">
                    @foreach ($relatedEntries as $relatedEntry)
                        <li>
                            <a
                                href="{{ route('knowledge.encyclopedia.entry', [$entry->category->slug, $relatedEntry->slug]) }}"
                                class="block rounded-md border border-stone-700/80 bg-black/35 px-3 py-2 text-sm text-stone-200 transition hover:border-red-800/70 hover:bg-red-900/20"
                            >
                                {{ $relatedEntry->title }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </section>
@endsection
