@php
    $postMeta = is_array($post->meta ?? null) ? $post->meta : [];
    $currentType = old('post_type', $post->post_type ?? 'ic');
    $currentCharacter = old('character_id', $post->character_id ?? '');
    $canUseGmPostMode = (bool) ($canUseGmPostMode ?? false);
    $isEditingGmNarration = $post instanceof \App\Models\Post && $post->isGmNarration();
    $currentPostMode = (string) old('post_mode', $isEditingGmNarration ? 'gm' : 'character');
    if (! $canUseGmPostMode || $currentType !== 'ic') {
        $currentPostMode = 'character';
    }
    $currentFormat = old('content_format', $post->content_format ?? 'markdown');
    $currentIcQuote = (string) old('ic_quote', (string) ($postMeta['ic_quote'] ?? ''));
    $wave3EditorPreviewEnabled = \App\Support\SensitiveFeatureGate::enabled('features.wave3.editor_preview', false);
    $initialPreviewHtml = $wave3EditorPreviewEnabled && $currentFormat === 'markdown'
        ? app(\App\Support\PostContentRenderer::class)->render((string) old('content', $post->content ?? ''), 'markdown')->toHtml()
        : '';
    $currentModeration = old('moderation_status', $post->moderation_status ?? 'pending');
    $currentModerationNote = old('moderation_note');
    $showProbeControls = (bool) ($showProbeControls ?? false);
    $probeCharacters = $probeCharacters ?? collect();
    $currentProbeEnabled = (bool) old('probe_enabled', false);
    $currentProbeCharacter = old('probe_character_id');
    $currentProbeMode = old('probe_roll_mode', 'normal');
    $probeAttributeOptions = (array) config('character_sheet.attributes', []);
    $currentProbeAttributeKey = (string) old('probe_attribute_key', array_key_first($probeAttributeOptions) ?? '');
    $currentProbeModifier = old('probe_modifier', 0);
    $currentProbeLeDelta = old('probe_le_delta', 0);
    $currentProbeAeDelta = old('probe_ae_delta', 0);
    $currentProbeExplanation = old('probe_explanation');
    $currentInventoryAwardEnabled = (bool) old('inventory_award_enabled', false);
    $currentInventoryAwardCharacter = old('inventory_award_character_id');
    $currentInventoryAwardItem = old('inventory_award_item');
    $currentInventoryAwardQuantity = old('inventory_award_quantity', 1);
    $currentInventoryAwardEquipped = (bool) old('inventory_award_equipped', false);
@endphp

<div
    class="space-y-5"
    x-data="{
        postType: '{{ $currentType }}',
        postMode: '{{ $currentPostMode }}',
        contentFormat: '{{ $currentFormat }}',
        probeEnabled: {{ $currentProbeEnabled ? 'true' : 'false' }},
        isGmMode() {
            return this.postType === 'ic' && this.postMode === 'gm';
        },
        syncPostModeState() {
            if (this.postType !== 'ic') {
                this.postMode = 'character';
            }

            if (this.isGmMode() && this.$refs.characterIdField) {
                this.$refs.characterIdField.value = '';
            }
        },
        formatHint() {
            if (this.contentFormat === 'markdown') {
                return 'Markdown aktiv: Vorschau und Format-Hotkeys sind freigeschaltet.';
            }

            if (this.contentFormat === 'bbcode') {
                return 'BBCode aktiv: Vorschau ist deaktiviert, klassische Foren-Tags bleiben nutzbar.';
            }

            return 'Klartext aktiv: roher Text ohne Markdown/BBCode-Rendering.';
        }
    }"
    x-init="$watch('postType', () => syncPostModeState()); $watch('postMode', () => syncPostModeState()); syncPostModeState()"
>
    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <label for="post_type" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Beitragstyp</label>
            <select
                id="post_type"
                name="post_type"
                x-model="postType"
                required
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            >
                <option value="ic" @selected($currentType === 'ic')>IC</option>
                <option value="ooc" @selected($currentType === 'ooc')>OOC</option>
            </select>
            <p
                class="mt-2 text-xs leading-relaxed text-stone-500"
                x-text="postType === 'ic'
                    ? 'IC-Übergang: Szene-Fokus mit Figurensprache und klaren Handlungsimpulsen.'
                    : 'OOC-Übergang: kurze Meta-Abstimmung außerhalb der In-Character-Erzählung.'"
            ></p>
            @error('post_type')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>

        @if ($canUseGmPostMode)
            <div x-show="postType === 'ic'" x-cloak>
                <label for="post_mode" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">IC-Modus</label>
                <select
                    id="post_mode"
                    name="post_mode"
                    x-model="postMode"
                    :disabled="postType !== 'ic'"
                    class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                >
                    <option value="character" @selected($currentPostMode === 'character')>Als Charakter posten</option>
                    <option value="gm" @selected($currentPostMode === 'gm')>Als Spielleitung posten</option>
                </select>
                <p class="mt-2 text-xs text-stone-500" x-text="postMode === 'gm'
                    ? 'Spielleitungsmodus: Beitrag wird als Erzählerstimme ohne Charakter gespeichert.'
                    : 'Charaktermodus: Beitrag wird mit ausgewähltem Charakter gespeichert.'"></p>
                @error('post_mode')
                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                @enderror
            </div>
        @else
            <input type="hidden" name="post_mode" value="character">
        @endif

        <div>
            <label for="character_id" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Charakter (für IC)</label>
            <select
                id="character_id"
                name="character_id"
                x-ref="characterIdField"
                :disabled="postType !== 'ic' || isGmMode()"
                x-bind:class="postType !== 'ic' || isGmMode() ? 'opacity-60' : ''"
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            >
                <option value="">Kein Charakter</option>
                @foreach ($characters as $characterOption)
                    <option value="{{ $characterOption->id }}" @selected((string) $currentCharacter === (string) $characterOption->id)>
                        {{ $characterOption->name }}
                    </option>
                @endforeach
            </select>
            @if ($canUseGmPostMode)
                <p class="mt-2 text-xs text-stone-500" x-show="isGmMode()" x-cloak>
                    Im Spielleitungsmodus wird kein Charakter für den Beitrag gesetzt.
                </p>
            @endif
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
                data-post-content-format
                x-model="contentFormat"
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            >
                <option value="markdown" @selected($currentFormat === 'markdown')>Markdown</option>
                <option value="bbcode" @selected($currentFormat === 'bbcode')>BBCode</option>
                <option value="plain" @selected($currentFormat === 'plain')>Klartext</option>
            </select>
            <p class="mt-2 text-xs text-stone-500" x-text="formatHint()"></p>
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
            data-post-content-input
            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-3 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            placeholder="Schreibe deinen Beitrag ..."
        >{{ old('content', $post->content ?? '') }}</textarea>
        <p class="mt-2 text-xs text-stone-500">Spoiler-Tag in allen Formaten: [spoiler]Geheimer Inhalt[/spoiler]</p>
        @error('content')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <div x-show="postType === 'ic'" x-cloak>
        <label for="ic_quote" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Optionales IC-Zitat</label>
        <input
            id="ic_quote"
            type="text"
            name="ic_quote"
            :disabled="postType !== 'ic'"
            maxlength="180"
            value="{{ $currentIcQuote }}"
            placeholder="Kurze prägende Zeile deines Charakters ..."
            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
        >
        <p class="mt-2 text-xs text-stone-500">Nur für IC-Beiträge. Wird als hervorgehobener Einstiegsquote über dem Beitrag angezeigt.</p>
        @error('ic_quote')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    @if ($wave3EditorPreviewEnabled)
        <section data-post-preview class="rounded-lg border border-stone-700/80 bg-black/30 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Markdown-Live-Preview</p>
            <p data-post-preview-status class="mt-2 text-xs text-stone-400">
                {{ $currentFormat === 'markdown' ? 'Live-Vorschau aktiv.' : 'Live-Vorschau ist nur bei Markdown aktiv.' }}
            </p>
            <div data-post-preview-output class="mt-3 break-words leading-relaxed text-stone-200 [&_a]:text-amber-300 [&_a]:underline [&_blockquote]:border-l [&_blockquote]:border-stone-700 [&_blockquote]:pl-4 [&_code]:rounded [&_code]:bg-black/50 [&_code]:px-1 [&_pre]:overflow-x-auto [&_pre]:rounded [&_pre]:border [&_pre]:border-stone-800 [&_pre]:bg-black/50 [&_pre]:p-3">
                @if ($initialPreviewHtml !== '')
                    {!! $initialPreviewHtml !!}
                @else
                    <p class="text-stone-500">Noch keine Vorschau verfügbar.</p>
                @endif
            </div>
        </section>
    @endif

    @if ($showProbeControls)
        <section class="rounded-lg border border-amber-700/40 bg-amber-900/10 p-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="font-heading text-lg text-amber-100">GM-Probe für diesen Beitrag</h3>
                    <p class="mt-1 text-xs leading-relaxed text-amber-200/80">
                        Eine Probe wird beim Speichern direkt ausgeführt und als Ergebnisblock im Beitrag angezeigt.
                    </p>
                </div>
                <label class="inline-flex items-center gap-2 text-xs uppercase tracking-widest text-amber-200">
                    <input
                        type="checkbox"
                        name="probe_enabled"
                        value="1"
                        x-model="probeEnabled"
                        @checked($currentProbeEnabled)
                        class="h-4 w-4 rounded border-amber-600/70 bg-neutral-900 text-amber-500 focus:ring-amber-500/60"
                    >
                    Probe aktivieren
                </label>
            </div>
            @error('probe_enabled')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror

            <div x-show="probeEnabled" x-cloak>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
                <div class="lg:col-span-2">
                    <label for="probe_explanation" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Erklärung / Anlass</label>
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
                        <option value="">Held wählen</option>
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

                <div>
                    <label for="probe_attribute_key" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Probe auf</label>
                    <select
                        id="probe_attribute_key"
                        name="probe_attribute_key"
                        class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-sm text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    >
                        @foreach ($probeAttributeOptions as $attributeKey => $meta)
                            <option value="{{ $attributeKey }}" @selected($currentProbeAttributeKey === $attributeKey)>
                                {{ $meta['label'] ?? strtoupper((string) $attributeKey) }}
                            </option>
                        @endforeach
                    </select>
                    @error('probe_attribute_key')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-4 grid gap-4 lg:grid-cols-3">
                <div>
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

                <div>
                    <label for="probe_le_delta" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">LE-Auswirkung (+/-)</label>
                    <input
                        id="probe_le_delta"
                        type="number"
                        name="probe_le_delta"
                        value="{{ $currentProbeLeDelta }}"
                        min="-200"
                        max="200"
                        step="1"
                        class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-sm text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    >
                    <p class="mt-1 text-xs text-stone-500">Beispiel: <span class="font-semibold text-stone-300">-10</span> für Schaden.</p>
                    <p class="mt-1 text-xs text-stone-500">Bei negativem LE-Wert wird ausgerüsteter RS des Ziel-Helds automatisch abgezogen.</p>
                    @error('probe_le_delta')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="probe_ae_delta" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">AE-Auswirkung (+/-)</label>
                    <input
                        id="probe_ae_delta"
                        type="number"
                        name="probe_ae_delta"
                        value="{{ $currentProbeAeDelta }}"
                        min="-200"
                        max="200"
                        step="1"
                        class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-sm text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    >
                    <p class="mt-1 text-xs text-stone-500">Beispiel: <span class="font-semibold text-stone-300">+5</span> für Regeneration.</p>
                    @error('probe_ae_delta')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            <p class="mt-3 text-xs uppercase tracking-[0.08em] text-stone-400">
                Probe-Ergebnis wird automatisch berechnet: (Wurf + Modifikator) <= Eigenschaftswert.
            </p>

            <div class="mt-5 rounded-md border border-stone-700/80 bg-black/25 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h4 class="text-sm font-semibold uppercase tracking-widest text-amber-200">Inventar-Fund im gleichen Post</h4>
                        <p class="mt-1 text-xs text-stone-400">Optional: Fügt dem Ziel-Held direkt einen Gegenstand im Charakterbogen hinzu.</p>
                    </div>
                    <label class="inline-flex items-center gap-2 text-xs uppercase tracking-widest text-amber-200">
                        <input
                            type="checkbox"
                            name="inventory_award_enabled"
                            value="1"
                            @checked($currentInventoryAwardEnabled)
                            class="h-4 w-4 rounded border-amber-600/70 bg-neutral-900 text-amber-500 focus:ring-amber-500/60"
                        >
                        Aktivieren
                    </label>
                </div>
                @error('inventory_award_enabled')
                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                @enderror

                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label for="inventory_award_character_id" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Ziel-Held</label>
                        <select
                            id="inventory_award_character_id"
                            name="inventory_award_character_id"
                            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-sm text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                        >
                            <option value="">Held wählen</option>
                            @foreach ($probeCharacters as $probeCharacter)
                                <option value="{{ $probeCharacter->id }}" @selected((string) $currentInventoryAwardCharacter === (string) $probeCharacter->id)>
                                    {{ $probeCharacter->name }}
                                    @if ($probeCharacter->relationLoaded('user') && $probeCharacter->user)
                                        ({{ $probeCharacter->user->name }})
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @error('inventory_award_character_id')
                            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="lg:col-span-2">
                        <label for="inventory_award_item" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Gefundener Gegenstand</label>
                        <input
                            id="inventory_award_item"
                            type="text"
                            name="inventory_award_item"
                            value="{{ $currentInventoryAwardItem }}"
                            maxlength="180"
                            placeholder="z. B. Seil 10m lang"
                            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                        >
                        @error('inventory_award_item')
                            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="inventory_award_quantity" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Menge</label>
                        <input
                            id="inventory_award_quantity"
                            type="number"
                            name="inventory_award_quantity"
                            value="{{ $currentInventoryAwardQuantity }}"
                            min="1"
                            max="999"
                            step="1"
                            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-sm text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                        >
                        @error('inventory_award_quantity')
                            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="sm:col-span-2 lg:col-span-4">
                        <label class="inline-flex items-center gap-2 text-xs uppercase tracking-widest text-stone-300">
                            <input
                                type="checkbox"
                                name="inventory_award_equipped"
                                value="1"
                                @checked($currentInventoryAwardEquipped)
                                class="h-4 w-4 rounded border-stone-500 bg-neutral-900 text-amber-500 focus:ring-amber-500/60"
                            >
                            Als ausgerüstet eintragen
                        </label>
                        @error('inventory_award_equipped')
                            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
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
                <option value="pending" @selected($currentModeration === 'pending')>Ausstehend</option>
                <option value="approved" @selected($currentModeration === 'approved')>Freigegeben</option>
                <option value="rejected" @selected($currentModeration === 'rejected')>Abgelehnt</option>
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
                placeholder="Grund für Freigabe/Ablehnung ..."
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
                href="{{ route('campaigns.scenes.show', ['world' => $post->scene->campaign->world, 'campaign' => $post->scene->campaign, 'scene' => $post->scene]) }}"
                class="rounded-md border border-stone-600/80 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
            >
                Abbrechen
            </a>
        @endif
    </div>
</div>
