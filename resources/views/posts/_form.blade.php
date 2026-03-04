@php
    $currentType = old('post_type', $post->post_type ?? 'ic');
    $currentCharacter = old('character_id', $post->character_id ?? '');
    $currentFormat = old('content_format', $post->content_format ?? 'markdown');
    $currentModeration = old('moderation_status', $post->moderation_status ?? 'pending');
    $currentModerationNote = old('moderation_note');
    $showProbeControls = (bool) ($showProbeControls ?? false);
    $probeCharacters = $probeCharacters ?? collect();
    $currentProbeEnabled = (bool) old('probe_enabled', false);
    $currentProbeCharacter = old('probe_character_id');
    $currentProbeMode = old('probe_roll_mode', 'normal');
    $currentProbeModifier = old('probe_modifier', 0);
    $currentProbeExplanation = old('probe_explanation');
@endphp

<div class="space-y-5">
    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <label for="post_type" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Beitragstyp</label>
            <select
                id="post_type"
                name="post_type"
                required
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            >
                <option value="ic" @selected($currentType === 'ic')>IC</option>
                <option value="ooc" @selected($currentType === 'ooc')>OOC</option>
            </select>
            <p class="mt-2 text-xs leading-relaxed text-stone-500">
                IC-Standard: Ich-Perspektive (1. Person), als schreibt dein Held selbst.
            </p>
            @error('post_type')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="character_id" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Charakter (für IC)</label>
            <select
                id="character_id"
                name="character_id"
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            >
                <option value="">Kein Charakter</option>
                @foreach ($characters as $characterOption)
                    <option value="{{ $characterOption->id }}" @selected((string) $currentCharacter === (string) $characterOption->id)>
                        {{ $characterOption->name }}
                    </option>
                @endforeach
            </select>
            @error('character_id')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="content_format" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Format</label>
            <select
                id="content_format"
                name="content_format"
                required
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            >
                <option value="markdown" @selected($currentFormat === 'markdown')>Markdown</option>
                <option value="bbcode" @selected($currentFormat === 'bbcode')>BBCode</option>
                <option value="plain" @selected($currentFormat === 'plain')>Plain Text</option>
            </select>
            @error('content_format')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div>
        <label for="content" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Inhalt</label>
        <textarea
            id="content"
            name="content"
            rows="8"
            required
            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-3 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            placeholder="Schreibe deinen Beitrag ..."
        >{{ old('content', $post->content ?? '') }}</textarea>
        <p class="mt-2 text-xs text-stone-500">Spoiler-Tag in allen Formaten: [spoiler]Geheimer Inhalt[/spoiler]</p>
        @error('content')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    @if ($showProbeControls)
        <section class="rounded-lg border border-amber-700/40 bg-amber-900/10 p-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="font-heading text-lg text-amber-100">GM-Probe fuer diesen Beitrag</h3>
                    <p class="mt-1 text-xs leading-relaxed text-amber-200/80">
                        Eine Probe wird beim Speichern direkt ausgefuehrt und als Ergebnisblock im Beitrag angezeigt.
                    </p>
                </div>
                <label class="inline-flex items-center gap-2 text-xs uppercase tracking-[0.1em] text-amber-200">
                    <input
                        type="checkbox"
                        name="probe_enabled"
                        value="1"
                        @checked($currentProbeEnabled)
                        class="h-4 w-4 rounded border-amber-600/70 bg-neutral-900 text-amber-500 focus:ring-amber-500/60"
                    >
                    Probe aktivieren
                </label>
            </div>
            @error('probe_enabled')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror

            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="lg:col-span-2">
                    <label for="probe_explanation" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Erklaerung / Anlass</label>
                    <input
                        id="probe_explanation"
                        type="text"
                        name="probe_explanation"
                        value="{{ $currentProbeExplanation }}"
                        maxlength="180"
                        placeholder="z. B. Klettern am Ascheturm unter Zeitdruck"
                        class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    >
                    @error('probe_explanation')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="probe_character_id" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Ziel-Held</label>
                    <select
                        id="probe_character_id"
                        name="probe_character_id"
                        class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-sm text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    >
                        <option value="">Held waehlen</option>
                        @foreach ($probeCharacters as $probeCharacter)
                            <option value="{{ $probeCharacter->id }}" @selected((string) $currentProbeCharacter === (string) $probeCharacter->id)>
                                {{ $probeCharacter->name }}
                                @if ($probeCharacter->relationLoaded('user') && $probeCharacter->user)
                                    ({{ $probeCharacter->user->name }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @error('probe_character_id')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="probe_roll_mode" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Modus</label>
                    <select
                        id="probe_roll_mode"
                        name="probe_roll_mode"
                        class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-sm text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    >
                        <option value="normal" @selected($currentProbeMode === 'normal')>Normal</option>
                        <option value="advantage" @selected($currentProbeMode === 'advantage')>Vorteil</option>
                        <option value="disadvantage" @selected($currentProbeMode === 'disadvantage')>Nachteil</option>
                    </select>
                    @error('probe_roll_mode')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-4 max-w-xs">
                <label for="probe_modifier" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Modifikator (+/-)</label>
                <input
                    id="probe_modifier"
                    type="number"
                    name="probe_modifier"
                    value="{{ $currentProbeModifier }}"
                    min="-40"
                    max="40"
                    step="1"
                    class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-sm text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                >
                @error('probe_modifier')
                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                @enderror
            </div>
        </section>
    @endif

    @if ($showModerationControls)
        <div>
            <label for="moderation_status" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Moderationsstatus</label>
            <select
                id="moderation_status"
                name="moderation_status"
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            >
                <option value="pending" @selected($currentModeration === 'pending')>Pending</option>
                <option value="approved" @selected($currentModeration === 'approved')>Approved</option>
                <option value="rejected" @selected($currentModeration === 'rejected')>Rejected</option>
            </select>
            @error('moderation_status')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="moderation_note" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Moderationshinweis (optional)</label>
            <textarea
                id="moderation_note"
                name="moderation_note"
                rows="3"
                maxlength="500"
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                placeholder="Grund fuer Freigabe/Ablehnung ..."
            >{{ $currentModerationNote }}</textarea>
            @error('moderation_note')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-3 pt-2">
        <button
            type="submit"
            class="rounded-md border border-amber-400/70 bg-amber-500/20 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-amber-100 transition hover:bg-amber-400/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300"
        >
            {{ $submitLabel }}
        </button>

        @if (isset($post))
            <a
                href="{{ route('campaigns.scenes.show', [$post->scene->campaign, $post->scene]) }}"
                class="rounded-md border border-stone-600/80 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
            >
                Abbrechen
            </a>
        @endif
    </div>
</div>
