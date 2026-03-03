@extends('layouts.auth')

@section('title', 'Enzyklopaedie · Wissenszentrum')

@section('content')
    <section class="mx-auto w-full max-w-6xl space-y-6">
        <header class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.14em] text-amber-400/80">Wissenszentrum</p>
                    <h1 class="mt-2 font-heading text-3xl text-stone-100 sm:text-4xl">Enzyklopaedie von Vhal'Tor</h1>
                    <p class="mt-4 max-w-4xl text-base leading-relaxed text-stone-300 sm:text-lg">
                        Dieser Band sammelt den aktuellen Weltkanon. Nutze ihn fuer konsistente Figuren, Orte und Machtverhaeltnisse.
                    </p>
                </div>

                @if ($canManage)
                    <a
                        href="{{ route('knowledge.admin.kategorien.index') }}"
                        class="rounded-md border border-amber-500/70 bg-amber-500/20 px-4 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-amber-100 transition hover:bg-amber-500/30"
                    >
                        Enzyklopaedie verwalten
                    </a>
                @endif
            </div>
        </header>

        @include('knowledge._nav')

        <section class="rounded-2xl border border-stone-800 bg-black/35 p-4 shadow-xl shadow-black/25 sm:p-6">
            <form method="GET" action="{{ route('knowledge.encyclopedia') }}" class="grid gap-3 sm:grid-cols-[2fr_1fr_auto] sm:items-end">
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

                <div>
                    <label for="k" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Kategorie</label>
                    <select
                        id="k"
                        name="k"
                        class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    >
                        <option value="">Alle Kategorien</option>
                        @foreach ($availableCategories as $availableCategory)
                            <option value="{{ $availableCategory->slug }}" @selected($selectedCategorySlug === $availableCategory->slug)>
                                {{ $availableCategory->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        type="submit"
                        class="rounded-md border border-amber-500/70 bg-amber-500/20 px-4 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-amber-100 transition hover:bg-amber-500/30"
                    >
                        Filtern
                    </button>
                    @if ($search !== '' || $selectedCategorySlug !== '')
                        <a
                            href="{{ route('knowledge.encyclopedia') }}"
                            class="rounded-md border border-stone-600/80 px-4 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                        >
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </section>

        @if ($categories->isEmpty())
            <section class="rounded-2xl border border-stone-800 bg-black/35 p-6 text-sm text-stone-300 sm:p-8">
                Keine passenden Enzyklopaedie-Eintraege gefunden.
            </section>
        @else
            <div class="space-y-5">
                @foreach ($categories as $category)
                    <article class="rounded-2xl border border-stone-800 bg-black/40 p-5 shadow-xl shadow-black/30 sm:p-6">
                        <header class="border-b border-stone-800/80 pb-4">
                            <h2 class="font-heading text-2xl text-stone-100">{{ $category->name }}</h2>
                            @if ($category->summary)
                                <p class="mt-2 text-sm text-stone-300">{{ $category->summary }}</p>
                            @endif
                        </header>

                        <div class="mt-4 space-y-3">
                            @foreach ($category->entries as $entry)
                                <section id="{{ $category->slug }}-{{ $entry->slug }}" class="rounded-xl border border-stone-700/80 bg-neutral-900/65 p-4 sm:p-5">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <h3 class="font-heading text-xl text-stone-100">{{ $entry->title }}</h3>
                                        @if ($entry->published_at)
                                            <span class="text-xs uppercase tracking-[0.08em] text-stone-400">
                                                {{ $entry->published_at->translatedFormat('d.m.Y') }}
                                            </span>
                                        @endif
                                    </div>

                                    @if ($entry->excerpt)
                                        <p class="mt-3 text-sm font-semibold leading-relaxed text-amber-200/90">{{ $entry->excerpt }}</p>
                                    @endif

                                    <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-stone-300">{{ $entry->content }}</p>
                                </section>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endsection
