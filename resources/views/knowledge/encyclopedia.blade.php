@extends('layouts.auth')

@section('title', 'Enzyklopädie · Wissenszentrum')

@section('content')
    <section class="mx-auto w-full max-w-6xl space-y-6">
        <header class="ui-card relative overflow-hidden p-6 sm:p-8">
            <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_20%_10%,rgba(168,85,39,0.26),transparent_42%),radial-gradient(circle_at_75%_30%,rgba(127,29,29,0.32),transparent_40%),linear-gradient(to_bottom,rgba(17,17,17,0.96),rgba(8,8,8,0.98))]"></div>

            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.14em] text-amber-400/80">Wissenszentrum</p>
                    <h1 class="mt-2 font-heading text-3xl text-stone-100 sm:text-4xl">Enzyklopädie · {{ $world->name }}</h1>
                    <p class="mt-4 max-w-4xl text-base leading-relaxed text-[#cccccc] sm:text-lg">
                        Öffentliches Nachschlagewerk für die ausgewählte Welt.
                        Lore zuerst, Spielfluss immer im Blick.
                    </p>
                </div>

                @if ($canManage)
                    <a
                        href="{{ route('knowledge.admin.kategorien.index', ['world' => $world]) }}"
                        class="ui-btn ui-btn-accent"
                    >
                        Enzyklopädie verwalten
                    </a>
                @endif
            </div>
        </header>

        @include('knowledge._nav')

        <form
            method="GET"
            action="{{ route('knowledge.encyclopedia', ['world' => $world]) }}"
            class="ui-card p-4 sm:p-6"
        >
            <div class="grid gap-3 sm:grid-cols-[2fr_auto_auto] sm:items-end">
                <div>
                    <label for="q" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Suche</label>
                    <input
                        id="q"
                        type="search"
                        name="q"
                        value="{{ $search }}"
                        maxlength="120"
                        class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                        placeholder="Titel, Kurztext oder Inhalt durchsuchen"
                    >
                </div>

                <div class="sm:hidden">
                    <label for="k" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Kategorie</label>
                    <select
                        id="k"
                        name="k"
                        class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    >
                        <option value="" @selected($selectedCategorySlug === '')>Alle Kategorien</option>
                        @foreach ($availableCategories as $availableCategory)
                            <option value="{{ $availableCategory->slug }}" @selected($selectedCategorySlug === $availableCategory->slug)>{{ $availableCategory->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-wrap gap-2 sm:justify-end">
                    <button
                        type="submit"
                        class="ui-btn ui-btn-accent"
                    >
                        Filtern
                    </button>
                    <a
                        href="{{ route('knowledge.encyclopedia', ['world' => $world]) }}"
                        class="ui-btn"
                    >
                        Reset
                    </a>
                </div>
            </div>
        </form>

        <div class="grid gap-6 lg:grid-cols-[17rem_minmax(0,1fr)] lg:items-start">
            <aside class="ui-card hidden p-4 lg:block">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-stone-400">Kategorien</p>
                <div class="mt-3 space-y-2">
                    <a
                        href="{{ route('knowledge.encyclopedia', array_filter(['world' => $world, 'q' => $search !== '' ? $search : null])) }}"
                        class="{{ $selectedCategorySlug === '' ? 'border-amber-500/70 bg-amber-500/15 text-amber-100' : 'border-stone-700/80 bg-black/35 text-stone-200 hover:border-stone-500' }} w-full rounded-md border px-3 py-2 text-left text-xs font-semibold uppercase tracking-widest transition"
                        @if ($selectedCategorySlug === '') aria-current="page" @endif
                    >
                        Alle Kategorien
                    </a>

                    @foreach ($availableCategories as $availableCategory)
                        @php($isSelectedCategory = $selectedCategorySlug === $availableCategory->slug)
                        <a
                            href="{{ route('knowledge.encyclopedia', array_filter(['world' => $world, 'q' => $search !== '' ? $search : null, 'k' => $availableCategory->slug])) }}"
                            class="{{ $isSelectedCategory ? 'border-amber-500/70 bg-amber-500/15 text-amber-100' : 'border-stone-700/80 bg-black/35 text-stone-200 hover:border-stone-500' }} w-full rounded-md border px-3 py-2 text-left text-xs font-semibold uppercase tracking-widest transition"
                            @if ($isSelectedCategory) aria-current="page" @endif
                        >
                            {{ $availableCategory->name }}
                        </a>
                    @endforeach
                </div>
            </aside>

            <section class="space-y-5">
                <div class="ui-card-soft px-4 py-3 text-xs uppercase tracking-[0.12em] text-stone-400">
                    @php($entryCount = $categories->sum(fn ($category) => $category->entries->count()))
                    <span>{{ $entryCount }} Einträge sichtbar</span>
                    @if ($selectedCategorySlug !== '')
                        <span class="mx-2 text-stone-600">|</span>
                        <span>Kategorie: {{ $selectedCategorySlug }}</span>
                    @endif
                    @if ($search !== '')
                        <span class="mx-2 text-stone-600">|</span>
                        <span>Suche: "{{ $search }}"</span>
                    @endif
                </div>

                @if ($categories->isEmpty())
                    <article class="ui-card p-6 text-sm text-stone-300 sm:p-8">
                        Keine passenden Enzyklopädie-Einträge gefunden.
                    </article>
                @else
                    <div class="space-y-6">
                        @foreach ($categories as $category)
                            <article class="space-y-3">
                                <header>
                                    <h2 class="font-heading text-2xl text-stone-100">{{ $category->name }}</h2>
                                    @if ($category->summary)
                                        <p class="mt-2 text-sm text-stone-300">{{ $category->summary }}</p>
                                    @endif
                                </header>

                                <div class="grid gap-4 md:grid-cols-2">
                                    @forelse ($category->entries as $entry)
                                        <section class="ui-card-soft group border-amber-950 bg-zinc-900 p-5 shadow-2xl transition-all hover:scale-[1.02] hover:shadow-red-950/50">
                                            <div class="flex flex-wrap items-start justify-between gap-3">
                                                <h3 class="font-heading text-xl text-stone-100">{{ $entry->title }}</h3>
                                                <span class="rounded-full border border-stone-700/80 bg-black/45 px-2 py-1 text-[0.65rem] font-semibold uppercase tracking-[0.08em] text-stone-300">
                                                    {{ $category->name }}
                                                </span>
                                            </div>

                                            @if ($entry->published_at)
                                                <p class="mt-2 text-xs uppercase tracking-widest text-stone-500">
                                                    Stand {{ $entry->published_at->translatedFormat('d.m.Y') }}
                                                </p>
                                            @endif

                                            @if ($entry->excerpt)
                                                <p class="mt-3 text-sm leading-relaxed text-amber-100/90">{{ $entry->excerpt }}</p>
                                            @endif

                                            <div class="mt-4 text-right">
                                                <a
                                                    href="{{ route('knowledge.encyclopedia.entry', ['world' => $world, 'categorySlug' => $category->slug, 'entrySlug' => $entry->slug]) }}"
                                                    class="ui-btn ui-btn-danger"
                                                >
                                                    Mehr lesen
                                                </a>
                                            </div>
                                        </section>
                                    @empty
                                        <article class="ui-card-soft border-stone-700/80 bg-black/35 p-5 text-sm text-stone-300 md:col-span-2">
                                            Noch keine veröffentlichten Einträge in dieser Kategorie.
                                        </article>
                                    @endforelse
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </section>
@endsection
