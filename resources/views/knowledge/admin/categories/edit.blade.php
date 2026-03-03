@extends('layouts.auth')

@section('title', 'Kategorie bearbeiten · Enzyklopaedie Admin')

@section('content')
    <section class="mx-auto w-full max-w-6xl space-y-6">
        <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Wissenszentrum · Admin</p>
            <h1 class="font-heading text-3xl text-stone-100">Kategorie bearbeiten</h1>
            <p class="mt-2 text-stone-300">{{ $category->name }} konfigurieren und Inhalte verwalten.</p>

            <form method="POST" action="{{ route('knowledge.admin.kategorien.update', $category) }}" class="mt-8">
                @csrf
                @method('PUT')
                @include('knowledge.admin.categories._form', [
                    'submitLabel' => 'Aenderungen speichern',
                    'category' => $category,
                ])
            </form>
        </article>

        <article class="rounded-2xl border border-stone-800 bg-black/40 p-6 shadow-xl shadow-black/30 sm:p-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-heading text-2xl text-stone-100">Eintraege in {{ $category->name }}</h2>
                    <p class="mt-1 text-sm text-stone-300">Sortierung ueber Position, Sichtbarkeit ueber Status steuern.</p>
                </div>
                <a
                    href="{{ route('knowledge.admin.kategorien.eintraege.create', $category) }}"
                    class="rounded-md border border-amber-500/70 bg-amber-500/20 px-4 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-amber-100 transition hover:bg-amber-500/30"
                >
                    Eintrag erstellen
                </a>
            </div>

            <div class="mt-5 space-y-3">
                @forelse ($entries as $entry)
                    <article class="rounded-lg border border-stone-700/80 bg-neutral-900/70 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-heading text-lg text-stone-100">{{ $entry->title }}</h3>
                                    <span class="inline-flex rounded-full border px-2 py-1 text-[0.65rem] font-semibold uppercase tracking-[0.08em] {{ $entry->status === \App\Models\EncyclopediaEntry::STATUS_PUBLISHED ? 'border-emerald-500/50 bg-emerald-500/15 text-emerald-200' : ($entry->status === \App\Models\EncyclopediaEntry::STATUS_DRAFT ? 'border-amber-500/50 bg-amber-500/15 text-amber-200' : 'border-stone-600/80 bg-stone-700/20 text-stone-300') }}">
                                        {{ $entry->status }}
                                    </span>
                                    <span class="rounded-full border border-stone-600/80 bg-black/50 px-2 py-1 text-[0.65rem] font-semibold uppercase tracking-[0.08em] text-stone-300">
                                        Pos {{ $entry->position }}
                                    </span>
                                </div>
                                @if ($entry->excerpt)
                                    <p class="mt-2 text-sm text-stone-300">{{ $entry->excerpt }}</p>
                                @endif
                                <p class="mt-2 font-mono text-xs text-stone-400">Slug: {{ $entry->slug }}</p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <a
                                    href="{{ route('knowledge.admin.kategorien.eintraege.edit', [$category, $entry]) }}"
                                    class="rounded-md border border-stone-600/80 px-3 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                                >
                                    Bearbeiten
                                </a>
                                <form method="POST" action="{{ route('knowledge.admin.kategorien.eintraege.destroy', [$category, $entry]) }}" onsubmit="return confirm('Eintrag wirklich loeschen?');">
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="rounded-md border border-red-500/70 bg-red-500/10 px-3 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-red-200 transition hover:bg-red-500/20"
                                    >
                                        Loeschen
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                @empty
                    <p class="rounded-lg border border-stone-700/80 bg-neutral-900/70 p-4 text-sm text-stone-300">
                        Noch keine Eintraege in dieser Kategorie.
                    </p>
                @endforelse
            </div>
        </article>
    </section>
@endsection
