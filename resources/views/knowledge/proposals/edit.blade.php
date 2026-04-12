@extends('layouts.auth')

@section('title', 'Enzyklopädie-Vorschlag bearbeiten')

@section('content')
    <section class="mx-auto w-full max-w-4xl space-y-6">
        @include('knowledge._nav')

        <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Wissenszentrum · Vorschlag</p>
                    <h1 class="font-heading text-3xl text-stone-100">Vorschlag bearbeiten</h1>
                    <p class="mt-2 text-stone-300">Nach dem Speichern wird der Vorschlag erneut als ausstehend eingereicht.</p>
                </div>
                <span class="rounded-full border border-amber-500/50 bg-amber-500/15 px-2 py-1 text-[0.65rem] font-semibold uppercase tracking-[0.08em] text-amber-200">
                    Status: {{ $entry->status }}
                </span>
            </div>

            <form method="POST" action="{{ route('knowledge.encyclopedia.proposals.update', ['world' => $world, 'encyclopediaEntry' => $entry]) }}" class="mt-8 space-y-5">
                @csrf
                @method('PUT')
                <x-form-error-summary />

                <div>
                    <label for="encyclopedia_category_id" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Kategorie</label>
                    <select
                        id="encyclopedia_category_id"
                        name="encyclopedia_category_id"
                        required
                        class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    >
                        <option value="">Kategorie wählen</option>
                        @foreach ($categories as $proposalCategory)
                            <option
                                value="{{ $proposalCategory->id }}"
                                @selected((int) old('encyclopedia_category_id', $entry->encyclopedia_category_id) === (int) $proposalCategory->id)
                            >
                                {{ $proposalCategory->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('encyclopedia_category_id')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="title" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Titel</label>
                    <input
                        id="title"
                        type="text"
                        name="title"
                        value="{{ old('title', $entry->title) }}"
                        required
                        maxlength="150"
                        class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    >
                    @error('title')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="slug" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Slug</label>
                    <input
                        id="slug"
                        type="text"
                        name="slug"
                        value="{{ old('slug', $entry->slug) }}"
                        maxlength="170"
                        class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    >
                    @error('slug')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="excerpt" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Kurztext</label>
                    <textarea
                        id="excerpt"
                        name="excerpt"
                        rows="3"
                        maxlength="4000"
                        class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-3 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    >{{ old('excerpt', $entry->excerpt) }}</textarea>
                    @error('excerpt')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="content" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Inhalt (Markdown)</label>
                    <textarea
                        id="content"
                        name="content"
                        rows="14"
                        required
                        maxlength="50000"
                        class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-3 font-mono text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    >{{ old('content', $entry->content) }}</textarea>
                    @error('content')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <button
                        type="submit"
                        class="rounded-md border border-amber-400/70 bg-amber-500/20 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-amber-100 transition hover:bg-amber-400/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300"
                    >
                        Änderungen speichern
                    </button>

                    <a
                        href="{{ route('knowledge.encyclopedia', ['world' => $world]) }}"
                        class="rounded-md border border-stone-600/80 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                    >
                        Zur Enzyklopädie
                    </a>
                </div>
            </form>
        </article>
    </section>
@endsection
