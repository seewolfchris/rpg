@php
    $statusOptions = [
        \App\Models\EncyclopediaEntry::STATUS_DRAFT => 'Entwurf',
        \App\Models\EncyclopediaEntry::STATUS_PUBLISHED => 'Publiziert',
        \App\Models\EncyclopediaEntry::STATUS_ARCHIVED => 'Archiviert',
    ];

    $publishedAtValue = old('published_at', isset($entry) && $entry->published_at ? $entry->published_at->format('Y-m-d\\TH:i') : '');
    $gameRelevance = is_array(old('game_relevance'))
        ? old('game_relevance')
        : (is_array($entry->game_relevance ?? null) ? $entry->game_relevance : []);
    $editingEntry = isset($entry) && $entry->exists;
    $existingEntryMedia = $editingEntry
        ? ($entry->relationLoaded('media')
            ? $entry->media->where('collection_name', \App\Models\EncyclopediaEntry::ENTRY_MEDIA_COLLECTION)->values()
            : $entry->media()->where('collection_name', \App\Models\EncyclopediaEntry::ENTRY_MEDIA_COLLECTION)->get())
        : collect();
    $selectedRemoveMediaIds = collect((array) old('remove_media_ids', []))
        ->map(static fn ($id) => is_numeric($id) ? (int) $id : 0)
        ->filter(static fn (int $id): bool => $id > 0)
        ->all();
    $formatBytes = static function (mixed $bytes): string {
        $size = is_numeric($bytes) ? (int) $bytes : -1;
        if ($size < 0) {
            return 'Größe unbekannt';
        }

        if ($size < 1024) {
            return $size.' B';
        }

        if ($size < (1024 * 1024)) {
            return number_format($size / 1024, 1, ',', '.').' KB';
        }

        return number_format($size / (1024 * 1024), 2, ',', '.').' MB';
    };
@endphp

<div class="space-y-5">
    <x-form-error-summary data-knowledge-admin-error-summary />

    <div>
        <label for="title" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Titel</label>
        <input
            id="title"
            type="text"
            name="title"
            value="{{ old('title', $entry->title ?? '') }}"
            required
            maxlength="150"
            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            placeholder="z. B. Aschelande"
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
            value="{{ old('slug', $entry->slug ?? '') }}"
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
            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-3 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            placeholder="Kurzfassung für die Kartenansicht"
        >{{ old('excerpt', $entry->excerpt ?? '') }}</textarea>
        @error('excerpt')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="content" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Inhalt</label>
        <textarea
            id="content"
            name="content"
            rows="12"
            required
            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-3 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            placeholder="Volltext des Weltkanons"
        >{{ old('content', $entry->content ?? '') }}</textarea>
        @error('content')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <section class="rounded-xl border border-stone-700/70 bg-black/30 p-4">
        <h3 class="font-heading text-lg text-stone-100">Spielrelevanz (optional)</h3>
        <p class="mt-1 text-xs text-stone-400">
            Strukturierte Hinweise für LE/RS/AE/Proben sowie Real-World-Anfänger.
        </p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="game_relevance_le" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">LE-Hinweis</label>
                <textarea
                    id="game_relevance_le"
                    name="game_relevance_le"
                    rows="3"
                    maxlength="1000"
                    class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-3 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    placeholder="z. B. Frontkämpfer sollten früh LE-Verluste einkalkulieren."
                >{{ old('game_relevance_le', data_get($gameRelevance, 'le_hint', '')) }}</textarea>
                @error('game_relevance_le')
                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="game_relevance_rs" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">RS-Hinweis</label>
                <textarea
                    id="game_relevance_rs"
                    name="game_relevance_rs"
                    rows="3"
                    maxlength="1000"
                    class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-3 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    placeholder="z. B. Leichte Rüstung reicht oft nicht gegen schwere Treffer."
                >{{ old('game_relevance_rs', data_get($gameRelevance, 'rs_hint', '')) }}</textarea>
                @error('game_relevance_rs')
                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="game_relevance_ae" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">AE-Hinweis</label>
                <textarea
                    id="game_relevance_ae"
                    name="game_relevance_ae"
                    rows="3"
                    maxlength="1000"
                    class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-3 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    placeholder="z. B. Astralenergie nur bei magiebegabten Figuren."
                >{{ old('game_relevance_ae', data_get($gameRelevance, 'ae_hint', '')) }}</textarea>
                @error('game_relevance_ae')
                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="game_relevance_probe" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Proben-Hinweis</label>
                <textarea
                    id="game_relevance_probe"
                    name="game_relevance_probe"
                    rows="3"
                    maxlength="1000"
                    class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-3 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    placeholder="z. B. Typische Eigenschaften für SL-Proben."
                >{{ old('game_relevance_probe', data_get($gameRelevance, 'probe_hint', '')) }}</textarea>
                @error('game_relevance_probe')
                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-2">
                <label for="game_relevance_real_world" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Real-World-Hinweis</label>
                <textarea
                    id="game_relevance_real_world"
                    name="game_relevance_real_world"
                    rows="3"
                    maxlength="1000"
                    class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-3 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    placeholder="z. B. Welche Einschränkungen für Real-World-Anfänger gelten."
                >{{ old('game_relevance_real_world', data_get($gameRelevance, 'real_world_hint', '')) }}</textarea>
                @error('game_relevance_real_world')
                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </section>

    <section class="rounded-xl border border-stone-700/70 bg-black/30 p-4">
        <h3 class="font-heading text-lg text-stone-100">Bilder, Karten &amp; Pläne</h3>
        <p class="mt-1 text-xs text-stone-400">
            Optional: Bilder, Karten, Stadtpläne oder Illustrationen. JPG, PNG oder WebP. Mehrere Dateien möglich, jeweils bis 4 MB.
        </p>

        <div class="mt-4">
            <label for="media_files" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Dateien hochladen</label>
            <input
                id="media_files"
                type="file"
                name="media_files[]"
                multiple
                accept="image/jpeg,image/png,image/webp"
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-sm text-stone-100 file:mr-4 file:rounded-md file:border-0 file:bg-amber-500/20 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:uppercase file:tracking-[0.08em] file:text-amber-100 hover:file:bg-amber-500/35 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            >
            @error('media_files')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
            @error('media_files.*')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>

        @if ($editingEntry && $existingEntryMedia->isNotEmpty())
            <div class="mt-5">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Bestehende Medien</p>
                <p class="mt-1 text-xs text-stone-500">Bestehende Dateien bleiben erhalten, sofern sie nicht explizit markiert werden.</p>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($existingEntryMedia as $entryMedia)
                        <label class="rounded-lg border border-stone-700/80 bg-black/35 p-3">
                            <img
                                src="{{ $entryMedia->getUrl() }}"
                                alt="Vorschau {{ $entry->title }} {{ $entryMedia->file_name }}"
                                loading="lazy"
                                class="h-36 w-full rounded-md object-cover"
                            >
                            <p class="mt-3 truncate text-xs font-semibold text-stone-200" title="{{ $entryMedia->file_name }}">{{ $entryMedia->file_name }}</p>
                            <p class="mt-1 text-xs text-stone-400">{{ $formatBytes($entryMedia->size ?? null) }}</p>
                            <span class="mt-3 inline-flex items-center gap-2 text-xs text-stone-300">
                                <input
                                    type="checkbox"
                                    name="remove_media_ids[]"
                                    value="{{ $entryMedia->id }}"
                                    @checked(in_array((int) $entryMedia->id, $selectedRemoveMediaIds, true))
                                    class="h-4 w-4 rounded border-stone-500 bg-neutral-900 text-amber-500 focus:ring-amber-500/60"
                                >
                                Entfernen
                            </span>
                        </label>
                    @endforeach
                </div>

                @error('remove_media_ids')
                    <p class="mt-3 text-sm text-red-300">{{ $message }}</p>
                @enderror
                @error('remove_media_ids.*')
                    <p class="mt-3 text-sm text-red-300">{{ $message }}</p>
                @enderror
            </div>
        @endif
    </section>

    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <label for="status" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Status</label>
            <select
                id="status"
                name="status"
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            >
                @foreach ($statusOptions as $statusValue => $statusLabel)
                    <option value="{{ $statusValue }}" @selected(old('status', $entry->status ?? \App\Models\EncyclopediaEntry::STATUS_DRAFT) === $statusValue)>
                        {{ $statusLabel }}
                    </option>
                @endforeach
            </select>
            @error('status')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="position" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Position</label>
            <input
                id="position"
                type="number"
                name="position"
                min="0"
                max="1000000"
                value="{{ old('position', $entry->position ?? 0) }}"
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            >
            @error('position')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="published_at" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Publiziert am</label>
            <input
                id="published_at"
                type="datetime-local"
                name="published_at"
                value="{{ $publishedAtValue }}"
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            >
            @error('published_at')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3 pt-2">
        <button
            type="submit"
            class="rounded-md border border-amber-400/70 bg-amber-500/20 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-amber-100 transition hover:bg-amber-400/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300"
        >
            {{ $submitLabel }}
        </button>

        <a
            href="{{ route('knowledge.admin.kategorien.edit', ['world' => $world, 'encyclopediaCategory' => $category]) }}"
            class="rounded-md border border-stone-600/80 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
        >
            Abbrechen
        </a>
    </div>
</div>
