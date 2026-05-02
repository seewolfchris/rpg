@php
    /** @var \App\Models\Handout $handout */
    $sceneOptions = $sceneOptions ?? collect();
    $hasExistingFile = $handout->exists && $handout->getFirstMedia(\App\Models\Handout::HANDOUT_FILE_COLLECTION) !== null;
    $cancelUrl = is_string($cancelUrl ?? null) && $cancelUrl !== ''
        ? $cancelUrl
        : route('campaigns.handouts.index', ['world' => $campaign->world, 'campaign' => $campaign]);
@endphp

<div class="space-y-5">
    <div>
        <label for="title" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Titel</label>
        <input
            id="title"
            type="text"
            name="title"
            value="{{ old('title', $handout->title ?? '') }}"
            required
            maxlength="150"
            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            placeholder="z. B. Karte des Nordpasses"
        >
        @error('title')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="description" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Beschreibung</label>
        <textarea
            id="description"
            name="description"
            rows="5"
            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-3 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            placeholder="Kontext oder Hinweise zum Handout ..."
        >{{ old('description', $handout->description ?? '') }}</textarea>
        @error('description')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <label for="scene_id" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Szene (optional)</label>
            <select
                id="scene_id"
                name="scene_id"
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            >
                <option value="">Keine Szenenbindung</option>
                @foreach ($sceneOptions as $sceneOption)
                    <option value="{{ $sceneOption->id }}" @selected((string) old('scene_id', $handout->scene_id ?? '') === (string) $sceneOption->id)>
                        #{{ $sceneOption->position }} · {{ $sceneOption->title }}
                    </option>
                @endforeach
            </select>
            @error('scene_id')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="version_label" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Version (optional)</label>
            <input
                id="version_label"
                type="text"
                name="version_label"
                value="{{ old('version_label', $handout->version_label ?? '') }}"
                maxlength="80"
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                placeholder="z. B. v1.2"
            >
            @error('version_label')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="sort_order" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Sortierung (optional)</label>
            <input
                id="sort_order"
                type="number"
                name="sort_order"
                min="0"
                max="100000"
                value="{{ old('sort_order', $handout->sort_order ?? '') }}"
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                placeholder="0"
            >
            @error('sort_order')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div>
        <label for="handout_file" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">
            Datei {{ $handout->exists ? '(optional ersetzen)' : '(erforderlich)' }}
        </label>
        <input
            id="handout_file"
            type="file"
            name="handout_file"
            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
            @if (! $handout->exists) required @endif
            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-sm text-stone-100 outline-none transition file:mr-4 file:rounded-md file:border-0 file:bg-amber-500/20 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:uppercase file:tracking-[0.08em] file:text-amber-100 hover:file:bg-amber-500/35 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
        >
        @error('handout_file')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror

        @if ($hasExistingFile)
            <div class="mt-3 space-y-2">
                <p class="text-xs uppercase tracking-[0.08em] text-stone-500">Aktuelle Datei</p>
                <img
                    src="{{ route('campaigns.handouts.file', ['world' => $campaign->world, 'campaign' => $campaign, 'handout' => $handout]) }}"
                    alt="Handout {{ $handout->title }}"
                    loading="lazy"
                    class="max-h-56 w-full rounded-md border border-stone-700/80 bg-black/30 object-contain"
                >
            </div>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-3 pt-2">
        <button
            type="submit"
            class="rounded-md border border-amber-400/70 bg-amber-500/20 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-amber-100 transition hover:bg-amber-400/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300"
        >
            {{ $submitLabel }}
        </button>

        <a
            href="{{ $cancelUrl }}"
            class="rounded-md border border-stone-600/80 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
        >
            Abbrechen
        </a>
    </div>
</div>
