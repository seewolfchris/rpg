@php
    $sceneMoodOptions = (array) config('scenes.moods', []);
    $selectedSceneMood = (string) old('mood', $scene->mood ?? (string) config('scenes.default_mood', 'neutral'));
    $previousSceneOptions = collect($previousSceneOptions ?? []);
    $cancelUrl = is_string($cancelUrl ?? null) && $cancelUrl !== ''
        ? $cancelUrl
        : (isset($scene) ? route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]) : route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]));
    $editingScene = isset($scene) && $scene instanceof \App\Models\Scene;
    $existingSceneContentImagesRaw = $editingScene
        ? ($scene->relationLoaded('media')
            ? $scene->media->where('collection_name', \App\Models\Scene::CONTENT_IMAGES_COLLECTION)->values()
            : $scene->media()->where('collection_name', \App\Models\Scene::CONTENT_IMAGES_COLLECTION)->get())
        : collect();
    $sceneContentMediaResolution = app(\App\Support\InlineImageSlotResolver::class)->resolve($existingSceneContentImagesRaw);
    $existingSceneContentImages = collect($sceneContentMediaResolution->mediaBySlot())
        ->sortKeys()
        ->values()
        ->concat(
            $sceneContentMediaResolution->orderedMedia()
                ->reject(static fn ($mediaItem): bool => array_key_exists((int) $mediaItem->id, $sceneContentMediaResolution->slotByMediaId()))
                ->values()
        )
        ->values();
    $sceneContentSlotByMediaId = $sceneContentMediaResolution->slotByMediaId();
    $selectedSceneContentRemovalIds = collect((array) old('remove_content_media_ids', []))
        ->map(static fn ($id) => is_numeric($id) ? (int) $id : 0)
        ->filter(static fn (int $id): bool => $id > 0)
        ->all();
@endphp

<div class="space-y-5">
    <div>
        <label for="title" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Titel</label>
        <input
            id="title"
            type="text"
            name="title"
            value="{{ old('title', $scene->title ?? '') }}"
            required
            maxlength="150"
            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            placeholder="z. B. Der verlassene Schrein"
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
            value="{{ old('slug', $scene->slug ?? '') }}"
            required
            maxlength="170"
            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            placeholder="der-verlassene-schrein"
        >
        @error('slug')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="previous_scene_id" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Diese Szene folgt auf</label>
        <select
            id="previous_scene_id"
            name="previous_scene_id"
            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
        >
            <option value="">Kein direkter Vorgänger</option>
            @foreach ($previousSceneOptions as $previousSceneOption)
                <option value="{{ $previousSceneOption->id }}" @selected((string) old('previous_scene_id', $scene->previous_scene_id ?? '') === (string) $previousSceneOption->id)>
                    #{{ $previousSceneOption->position }} · {{ $previousSceneOption->title }}
                </option>
            @endforeach
        </select>
        @error('previous_scene_id')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="summary" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Kurzbeschreibung</label>
        <textarea
            id="summary"
            name="summary"
            rows="3"
            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-3 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            placeholder="Kurzer Szenen-Teaser ..."
        >{{ old('summary', $scene->summary ?? '') }}</textarea>
        @error('summary')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="description" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Beschreibung</label>
        <textarea
            id="description"
            name="description"
            rows="8"
            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-3 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            placeholder="Szenerie, Ausgangslage, Stakes ..."
        >{{ old('description', $scene->description ?? '') }}</textarea>
        @error('description')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="header_image" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Szenen-Titelbild (optional)</label>
        <input
            id="header_image"
            type="file"
            name="header_image"
            accept=".jpg,.jpeg,.png,.webp,.avif,image/jpeg,image/png,image/webp,image/avif"
            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-sm text-stone-100 outline-none transition file:mr-4 file:rounded-md file:border-0 file:bg-amber-500/20 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:uppercase file:tracking-[0.08em] file:text-amber-100 hover:file:bg-amber-500/35 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
        >
        <p class="mt-2 text-xs leading-relaxed text-stone-500">
            Das Titelbild erscheint im Kopf der Szene und ist unabhängig von Bildern in der Szenenbeschreibung.
        </p>
        @if (! empty($scene?->header_image_path))
            <div class="mt-3 space-y-2">
                <img
                    src="{{ asset('storage/'.$scene->header_image_path) }}"
                    alt="Aktuelles Szenen-Titelbild"
                    class="max-h-44 w-full rounded-md object-cover"
                >
                <label class="inline-flex items-center gap-2 text-xs uppercase tracking-[0.08em] text-stone-300">
                    <input
                        type="checkbox"
                        name="remove_header_image"
                        value="1"
                        @checked((bool) old('remove_header_image', false))
                        class="h-4 w-4 rounded border-stone-500 bg-neutral-900 text-amber-500 focus:ring-amber-500/60"
                    >
                    Titelbild entfernen
                </label>
            </div>
        @endif
        @error('header_image')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
        @error('remove_header_image')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <section
        data-inline-image-controls
        data-inline-image-target="#description"
        data-inline-image-max-slots="4"
        class="rounded-lg border border-stone-700/80 bg-black/30 p-4"
    >
        <div>
            <label for="content_images" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Bilder in der Szenenbeschreibung (optional)</label>
            <input
                id="content_images"
                type="file"
                name="content_images[]"
                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                multiple
                data-inline-image-file-input
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-sm text-stone-100 outline-none transition file:mr-4 file:rounded-md file:border-0 file:bg-amber-500/20 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:uppercase file:tracking-[0.08em] file:text-amber-100 hover:file:bg-amber-500/35 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            >
            <p class="mt-2 text-xs leading-relaxed text-stone-500">
                Maximal 4 gespeicherte Bilder, JPG/PNG/WEBP, jeweils bis 4 MB. Marker [bild:1] bis [bild:4] setzen Bilder in die Beschreibung; nicht verwendete Bilder erscheinen als Galerie.
            </p>
            @error('content_images')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
            @error('content_images.*')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div data-inline-image-preview-list class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3"></div>
        <p data-inline-image-status class="mt-3 text-xs text-stone-500"></p>

        @if ($editingScene && $existingSceneContentImages->isNotEmpty())
            <div class="mt-5">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Bestehende Bilder in der Szenenbeschreibung</p>
                <p class="mt-2 text-xs text-stone-500">
                    Bilder bleiben unverändert, solange sie nicht explizit zur Entfernung markiert werden.
                </p>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($existingSceneContentImages as $contentImage)
                        @php($displaySlot = $sceneContentSlotByMediaId[(int) $contentImage->id] ?? null)
                        <div
                            data-inline-image-existing
                            data-inline-image-existing-slot="{{ $displaySlot }}"
                            class="rounded-lg border border-stone-700/80 bg-black/35 p-3"
                        >
                            <img
                                src="{{ $contentImage->getUrl() }}"
                                alt="Bild in der Szenenbeschreibung"
                                loading="lazy"
                                class="h-40 w-full rounded-md object-cover"
                            >
                            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-stone-300">
                                <span data-inline-image-slot-label class="rounded border border-stone-600/80 px-2 py-1 text-stone-200">
                                    @if ($displaySlot !== null)
                                        Slot {{ $displaySlot }}
                                    @else
                                        Galerie
                                    @endif
                                </span>
                                @if ($displaySlot !== null)
                                    <button
                                        type="button"
                                        data-inline-image-insert-marker
                                        data-inline-image-slot="{{ $displaySlot }}"
                                        class="rounded-md border border-amber-500/70 px-2 py-1 font-semibold text-amber-100 transition hover:bg-amber-500/15 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300"
                                    >
                                        Marker [bild:{{ $displaySlot }}]
                                    </button>
                                @endif
                            </div>
                            <label class="mt-3 inline-flex items-center gap-2 text-xs text-stone-300">
                                <input
                                    type="checkbox"
                                    name="remove_content_media_ids[]"
                                    value="{{ $contentImage->id }}"
                                    data-inline-image-remove
                                    @checked(in_array((int) $contentImage->id, $selectedSceneContentRemovalIds, true))
                                    class="h-4 w-4 rounded border-stone-500 bg-neutral-900 text-amber-500 focus:ring-amber-500/60"
                                >
                                Bild entfernen
                            </label>
                        </div>
                    @endforeach
                </div>

                @error('remove_content_media_ids')
                    <p class="mt-3 text-sm text-red-300">{{ $message }}</p>
                @enderror
                @error('remove_content_media_ids.*')
                    <p class="mt-3 text-sm text-red-300">{{ $message }}</p>
                @enderror
            </div>
        @endif
    </section>

    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <label for="status" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Status</label>
            @php($sceneStatus = old('status', $scene->status ?? 'open'))
            <select
                id="status"
                name="status"
                required
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            >
                <option value="open" @selected($sceneStatus === 'open')>Offen</option>
                <option value="closed" @selected($sceneStatus === 'closed')>Geschlossen</option>
                <option value="archived" @selected($sceneStatus === 'archived')>Archiviert</option>
            </select>
            @error('status')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="mood" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Stimmung</label>
            <select
                id="mood"
                name="mood"
                required
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            >
                @foreach ($sceneMoodOptions as $moodKey => $moodMeta)
                    <option value="{{ $moodKey }}" @selected($selectedSceneMood === $moodKey)>
                        {{ $moodMeta['label'] ?? ucfirst((string) $moodKey) }}
                    </option>
                @endforeach
            </select>
            @error('mood')
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
                max="100000"
                value="{{ old('position', $scene->position ?? 0) }}"
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            >
            @error('position')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-end">
            <label class="flex items-center gap-2 text-sm text-stone-300">
                <input
                    type="checkbox"
                    name="allow_ooc"
                    value="1"
                    @checked(old('allow_ooc', $scene->allow_ooc ?? true))
                    class="h-4 w-4 rounded border-stone-500 bg-neutral-900 text-amber-500 focus:ring-amber-500/60"
                >
                OOC erlaubt
            </label>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="opens_at" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Öffnet am</label>
            <input
                id="opens_at"
                type="datetime-local"
                name="opens_at"
                value="{{ old('opens_at', isset($scene?->opens_at) ? $scene->opens_at->format('Y-m-d\TH:i') : '') }}"
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            >
            @error('opens_at')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="closes_at" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Schliesst am</label>
            <input
                id="closes_at"
                type="datetime-local"
                name="closes_at"
                value="{{ old('closes_at', isset($scene?->closes_at) ? $scene->closes_at->format('Y-m-d\TH:i') : '') }}"
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            >
            @error('closes_at')
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
            href="{{ $cancelUrl }}"
            class="rounded-md border border-stone-600/80 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
        >
            Abbrechen
        </a>
    </div>
</div>
