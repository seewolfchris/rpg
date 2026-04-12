@extends('layouts.auth')

@section('title', 'Enzyklopädie-Vorschlag erstellen')

@section('content')
    <section class="mx-auto w-full max-w-4xl space-y-6">
        @include('knowledge._nav')

        <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Wissenszentrum · Vorschlag</p>
            <h1 class="font-heading text-3xl text-stone-100">Eintrag vorschlagen</h1>
            <p class="mt-2 text-stone-300">Dein Vorschlag startet als ausstehend und wird vor Veröffentlichung geprüft.</p>

            <form method="POST" action="{{ route('knowledge.encyclopedia.proposals.store', ['world' => $world]) }}" class="mt-8 space-y-5">
                @csrf
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
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((int) old('encyclopedia_category_id', 0) === (int) $category->id)>
                                {{ $category->name }}
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
                        value="{{ old('title') }}"
                        required
                        maxlength="150"
                        class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                        placeholder="z. B. Die Kupferbastion"
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
                        value="{{ old('slug') }}"
                        maxlength="170"
                        class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                        placeholder="wird automatisch aus dem Titel generiert"
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
                        placeholder="Kurzfassung für Listenansichten"
                    >{{ old('excerpt') }}</textarea>
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
                        placeholder="# Überschrift&#10;&#10;Volltext in Markdown ..."
                    >{{ old('content') }}</textarea>
                    @error('content')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <button
                        type="submit"
                        @disabled($categories->isEmpty())
                        class="rounded-md border border-amber-400/70 bg-amber-500/20 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-amber-100 transition hover:bg-amber-400/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Vorschlag speichern
                    </button>

                    <a
                        href="{{ route('knowledge.encyclopedia', ['world' => $world]) }}"
                        class="rounded-md border border-stone-600/80 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                    >
                        Abbrechen
                    </a>
                </div>
            </form>
        </article>
    </section>
@endsection
