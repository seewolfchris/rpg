@php
    $statusOptions = (array) config('characters.statuses', []);
    $canManageCharacter = auth()->user()?->can('update', $character) ?? false;
    $canInlineEdit = $canManageCharacter;
    $hasVisibleNarrativeMeta = $character->concept
        || $character->world_connection
        || ($canManageCharacter && ($character->gm_secret || $character->gm_note));
@endphp

<section id="character-inline-editor" class="character-narrative-dossier space-y-4 rounded-lg border border-stone-700/70 bg-black/30 p-4" x-data="{ editing: false }">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <h2 class="font-heading text-2xl text-stone-100">Narrative Kerndaten</h2>
        @if ($canInlineEdit)
            <button
                type="button"
                class="ui-btn"
                x-show="!editing"
                @click="editing = true"
            >
                Inline bearbeiten
            </button>
        @endif
    </div>

    <div x-show="!editing" x-cloak>
        @if ($character->epithet)
            <p class="text-sm text-amber-300"><span class="font-semibold text-amber-100">Beiname:</span> {{ $character->epithet }}</p>
        @endif

        <p class="mt-2 text-sm text-stone-200"><span class="font-semibold text-stone-100">Status:</span> {{ data_get($statusOptions, $character->status.'.label', ucfirst((string) $character->status)) }}</p>

        <section class="mt-4">
            <h3 class="text-xs font-semibold uppercase tracking-[0.08em] text-stone-400">Biografie</h3>
            <div class="character-manuscript-text mt-2 whitespace-pre-line leading-relaxed text-stone-300">{{ $character->bio }}</div>
        </section>

        @if ($hasVisibleNarrativeMeta)
            <section class="mt-4 space-y-2">
                @if ($character->concept)
                    <p class="text-sm text-stone-200"><span class="font-semibold text-stone-100">Konzept:</span> {{ $character->concept }}</p>
                @endif
                @if ($character->world_connection)
                    <p class="text-sm text-stone-200"><span class="font-semibold text-stone-100">Weltbezug:</span> {{ $character->world_connection }}</p>
                @endif
                @if ($canManageCharacter && $character->gm_secret)
                    <p class="text-sm text-red-200"><span class="font-semibold text-red-100">Geheimnis (GM):</span> {{ $character->gm_secret }}</p>
                @endif
                @if ($canManageCharacter && $character->gm_note)
                    <p class="text-sm text-stone-200"><span class="font-semibold text-stone-100">GM-Notiz:</span> {{ $character->gm_note }}</p>
                @endif
            </section>
        @endif
    </div>

    @if ($canInlineEdit)
        <form
            x-show="editing"
            x-cloak
            method="POST"
            action="{{ route('characters.inline-update', $character) }}"
            class="space-y-4"
            hx-patch="{{ route('characters.inline-update', $character) }}"
            hx-target="#character-inline-editor"
            hx-swap="outerHTML"
        >
            @csrf
            @method('PATCH')

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="inline_epithet" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Beiname</label>
                    <input
                        id="inline_epithet"
                        type="text"
                        name="epithet"
                        maxlength="120"
                        value="{{ old('epithet', (string) $character->epithet) }}"
                        class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    >
                </div>
                <div>
                    <label for="inline_status" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Status</label>
                    <select
                        id="inline_status"
                        name="status"
                        class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-sm text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    >
                        @foreach ($statusOptions as $statusKey => $statusMeta)
                            <option value="{{ $statusKey }}" @selected(old('status', (string) $character->status) === (string) $statusKey)>
                                {{ $statusMeta['label'] ?? ucfirst((string) $statusKey) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label for="inline_bio" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Biografie</label>
                <textarea
                    id="inline_bio"
                    name="bio"
                    rows="6"
                    maxlength="12000"
                    class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-3 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                >{{ old('bio', (string) $character->bio) }}</textarea>
            </div>

            <div>
                <label for="inline_concept" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Konzept</label>
                <textarea
                    id="inline_concept"
                    name="concept"
                    rows="2"
                    maxlength="180"
                    class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-3 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                >{{ old('concept', (string) $character->concept) }}</textarea>
            </div>

            <div>
                <label for="inline_world_connection" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Weltbezug</label>
                <textarea
                    id="inline_world_connection"
                    name="world_connection"
                    rows="3"
                    maxlength="2000"
                    class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-3 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                >{{ old('world_connection', (string) $character->world_connection) }}</textarea>
            </div>

            <div>
                <label for="inline_gm_secret" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Geheimnis (GM)</label>
                <textarea
                    id="inline_gm_secret"
                    name="gm_secret"
                    rows="3"
                    maxlength="3000"
                    class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-3 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                >{{ old('gm_secret', (string) $character->gm_secret) }}</textarea>
            </div>

            <div>
                <label for="inline_gm_note" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">GM-Notiz</label>
                <textarea
                    id="inline_gm_note"
                    name="gm_note"
                    rows="3"
                    maxlength="2000"
                    class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-3 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                >{{ old('gm_note', (string) $character->gm_note) }}</textarea>
            </div>

            @if ($errors->any())
                <div class="rounded border border-red-700/70 bg-red-950/25 p-3 text-sm text-red-200">
                    @foreach ($errors->all() as $message)
                        <p>{{ $message }}</p>
                    @endforeach
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="ui-btn ui-btn-accent">Inline speichern</button>
                <button
                    type="button"
                    class="ui-btn"
                    @click="editing = false"
                >
                    Abbrechen
                </button>
            </div>
        </form>
    @endif
</section>
