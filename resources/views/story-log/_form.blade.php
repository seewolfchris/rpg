@php
    /** @var \App\Models\StoryLogEntry $storyLogEntry */
    $sceneOptions = $sceneOptions ?? collect();
@endphp

<div class="space-y-5">
    <div>
        <label for="title" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Titel</label>
        <input
            id="title"
            type="text"
            name="title"
            value="{{ old('title', $storyLogEntry->title ?? '') }}"
            required
            maxlength="180"
            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            placeholder="z. B. Kapitel 3: Der Schwur am Nordtor"
        >
        @error('title')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="body" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Eintragstext</label>
        <textarea
            id="body"
            name="body"
            rows="10"
            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-3 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            placeholder="Zusammenfassung, wichtige Wendepunkte und Konsequenzen ..."
        >{{ old('body', $storyLogEntry->body ?? '') }}</textarea>
        @error('body')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="scene_id" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Szene (optional)</label>
            <select
                id="scene_id"
                name="scene_id"
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            >
                <option value="">Kampagnenweit</option>
                @foreach ($sceneOptions as $sceneOption)
                    <option value="{{ $sceneOption->id }}" @selected((string) old('scene_id', $storyLogEntry->scene_id ?? '') === (string) $sceneOption->id)>
                        #{{ $sceneOption->position }} · {{ $sceneOption->title }}
                    </option>
                @endforeach
            </select>
            @error('scene_id')
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
                max="999999"
                value="{{ old('sort_order', $storyLogEntry->sort_order ?? '') }}"
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                placeholder="0"
            >
            @error('sort_order')
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
            href="{{ route('campaigns.story-log.index', ['world' => $campaign->world, 'campaign' => $campaign]) }}"
            class="rounded-md border border-stone-600/80 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
        >
            Abbrechen
        </a>
    </div>
</div>
