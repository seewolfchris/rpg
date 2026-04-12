@extends('layouts.auth')

@section('title', 'Enzyklopädie-Moderation')

@section('content')
    <section class="mx-auto w-full max-w-6xl space-y-6">
        @include('knowledge._nav')

        <header class="ui-card p-6 sm:p-8">
            <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Wissenszentrum · Moderation</p>
            <h1 class="font-heading text-3xl text-stone-100">Vorschlags-Queue</h1>
            <p class="mt-2 text-sm text-stone-300">Ausstehende Vorschläge prüfen und freigeben oder ablehnen.</p>
        </header>

        <section class="ui-card p-6 sm:p-8">
            @if ($entries->isEmpty())
                <p class="text-sm text-stone-400">Keine ausstehenden Vorschläge.</p>
            @else
                <div class="space-y-4">
                    @foreach ($entries as $entry)
                        <article class="ui-card-soft p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <h2 class="font-heading text-2xl text-stone-100">{{ $entry->title }}</h2>
                                    <p class="mt-2 text-xs uppercase tracking-[0.08em] text-stone-500">
                                        Kategorie: {{ $entry->category->name }} ·
                                        Autor: {{ $entry->creator?->name ?? 'Unbekannt' }} ·
                                        Zuletzt geändert: {{ $entry->updated_at?->translatedFormat('d.m.Y H:i') }}
                                    </p>

                                    @if ($entry->excerpt)
                                        <p class="mt-3 text-sm text-stone-300">{{ $entry->excerpt }}</p>
                                    @endif

                                    <div class="ui-card-soft mt-4 whitespace-pre-line p-4 text-sm leading-relaxed text-stone-300">
                                        {{ \Illuminate\Support\Str::limit($entry->content, 900) }}
                                    </div>
                                </div>

                                <span class="rounded-full border border-amber-600/60 bg-amber-700/20 px-2 py-1 text-[0.65rem] font-semibold uppercase tracking-[0.08em] text-amber-200">
                                    {{ $entry->status }}
                                </span>
                            </div>

                            <div class="mt-4 flex flex-wrap items-center gap-2">
                                <form method="POST" action="{{ route('knowledge.encyclopedia.moderation.approve', ['world' => $world, 'encyclopediaEntry' => $entry]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button
                                        type="submit"
                                        class="ui-btn ui-btn-success"
                                    >
                                        Freigeben
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('knowledge.encyclopedia.moderation.reject', ['world' => $world, 'encyclopediaEntry' => $entry]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button
                                        type="submit"
                                        class="ui-btn ui-btn-danger"
                                    >
                                        Ablehnen
                                    </button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $entries->links() }}
                </div>
            @endif
        </section>
    </section>
@endsection
