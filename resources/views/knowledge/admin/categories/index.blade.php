@extends('layouts.auth')

@section('title', 'Enzyklopädie verwalten · Kategorien')

@section('content')
    <section class="mx-auto w-full max-w-6xl space-y-6">
        <header class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <p class="text-xs uppercase tracking-[0.14em] text-amber-400/80">Wissenszentrum · Admin</p>
            <h1 class="mt-2 font-heading text-3xl text-stone-100 sm:text-4xl">Enzyklopädie-Kategorien</h1>
            <p class="mt-3 max-w-3xl text-sm leading-relaxed text-stone-300 sm:text-base">
                Verwalte Kategorien, Sichtbarkeit und Sortierung des Weltkanons.
            </p>

            <div class="mt-5 flex flex-wrap gap-3">
                <a
                    href="{{ route('knowledge.encyclopedia') }}"
                    class="rounded-md border border-stone-600/80 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                >
                    Zur Enzyklopädie
                </a>
                <a
                    href="{{ route('knowledge.admin.kategorien.create') }}"
                    class="rounded-md border border-amber-500/70 bg-amber-500/20 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/30"
                >
                    Kategorie erstellen
                </a>
            </div>
        </header>

        <section class="rounded-2xl border border-stone-800 bg-black/40 p-4 shadow-xl shadow-black/30 sm:p-6">
            @if ($categories->isEmpty())
                <p class="rounded-lg border border-stone-700/80 bg-neutral-900/70 p-4 text-sm text-stone-300">
                    Noch keine Kategorien vorhanden.
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full border-collapse text-left text-sm">
                        <thead>
                            <tr class="border-b border-stone-700/80 text-xs uppercase tracking-widest text-stone-400">
                                <th class="px-3 py-3">Kategorie</th>
                                <th class="px-3 py-3">Slug</th>
                                <th class="px-3 py-3">Sichtbar</th>
                                <th class="px-3 py-3">Einträge</th>
                                <th class="px-3 py-3">Position</th>
                                <th class="px-3 py-3 text-right">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($categories as $category)
                                <tr class="border-b border-stone-800/90 align-top text-stone-200">
                                    <td class="px-3 py-3">
                                        <p class="font-semibold text-stone-100">{{ $category->name }}</p>
                                        @if ($category->summary)
                                            <p class="mt-1 max-w-md text-xs text-stone-400">{{ $category->summary }}</p>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 font-mono text-xs text-stone-300">{{ $category->slug }}</td>
                                    <td class="px-3 py-3">
                                        <span class="inline-flex rounded-full border px-2 py-1 text-[0.65rem] font-semibold uppercase tracking-[0.08em] {{ $category->is_public ? 'border-emerald-500/50 bg-emerald-500/15 text-emerald-200' : 'border-stone-600/80 bg-stone-700/20 text-stone-300' }}">
                                            {{ $category->is_public ? 'Ja' : 'Nein' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-xs text-stone-300">
                                        <span class="font-semibold text-stone-100">{{ $category->entries_count }}</span>
                                        gesamt,
                                        <span class="font-semibold text-amber-200">{{ $category->published_entries_count }}</span>
                                        publiziert
                                    </td>
                                    <td class="px-3 py-3 text-stone-300">{{ $category->position }}</td>
                                    <td class="px-3 py-3">
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <a
                                                href="{{ route('knowledge.admin.kategorien.edit', $category) }}"
                                                class="rounded-md border border-stone-600/80 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                                            >
                                                Bearbeiten
                                            </a>
                                            <form method="POST" action="{{ route('knowledge.admin.kategorien.destroy', $category) }}" onsubmit="return confirm('Kategorie wirklich löschen? Alle Einträge werden entfernt.');">
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="rounded-md border border-red-500/70 bg-red-500/10 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-red-200 transition hover:bg-red-500/20"
                                                >
                                                    Löschen
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-5">
                    {{ $categories->links() }}
                </div>
            @endif
        </section>
    </section>
@endsection
