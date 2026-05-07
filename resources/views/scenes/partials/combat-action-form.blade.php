@php
    $sectionId = $sectionId ?? 'combat-action-tool';
    $title = $title ?? 'Kampfaktion (Spielleitung)';
    $description = $description ?? 'Spieler schreiben Absichten im Thread. Die Spielleitung wertet hier eine einzelne Kampfaktion aus.';
    $hint = $hint ?? 'V1: Einzelaktion, keine Kampfphasen und keine Spieler-Queue.';
    $formAction = $formAction ?? route('campaigns.scenes.combat.actions.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]);
    $submitLabel = $submitLabel ?? 'Kampfaktion auswerten';
    $fieldPrefix = $fieldPrefix ?? 'combat_action';
    $conflictActors = $conflictActors ?? collect();

    $actorNpcDetailsOpen = $errors->hasAny(['actor_le_current', 'actor_le_max']);
    $targetNpcDetailsOpen = $errors->hasAny(['target_le_current', 'target_le_max']);
@endphp

<section id="{{ $sectionId }}" class="ui-card border-amber-800/40 bg-amber-950/15 p-6 sm:p-8" data-reading-mode-chrome>
    <h2 class="font-heading text-2xl text-amber-100">{{ $title }}</h2>
    <p class="mt-2 text-sm text-amber-200/90">
        {{ $description }}
    </p>
    @if (is_string($hint) && trim($hint) !== '')
        <p class="mt-2 text-xs uppercase tracking-[0.08em] text-amber-300/90">
            {{ $hint }}
        </p>
    @endif

    <form method="POST" action="{{ $formAction }}" class="mt-5 space-y-5">
        @csrf

        <fieldset class="ui-card-soft border-stone-700/70 bg-black/20 p-4">
            <legend class="px-2 text-xs font-semibold uppercase tracking-[0.12em] text-amber-200">1. Beteiligte</legend>
            <div class="mt-3 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="md:col-span-2 xl:col-span-2">
                    <label for="{{ $fieldPrefix }}_actor_conflict_actor_id" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Angreifer aus Szenenbeteiligten</label>
                    <select id="{{ $fieldPrefix }}_actor_conflict_actor_id" name="actor_conflict_actor_id" class="w-full px-4 py-2.5 text-sm text-stone-100">
                        <option value="">Manuell eingeben</option>
                        @foreach ($conflictActors as $conflictActorOption)
                            <option value="{{ $conflictActorOption->id }}" @selected((string) old('actor_conflict_actor_id') === (string) $conflictActorOption->id)>
                                {{ $conflictActorOption->displayName() }} ({{ $conflictActorOption->isCharacter() ? 'Charakter' : 'NPC' }})
                            </option>
                        @endforeach
                    </select>
                    @error('actor_conflict_actor_id')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-stone-500">Wenn gewählt, werden Angriff/Schaden nach Möglichkeit aus dem Beteiligten übernommen.</p>
                </div>

                <div class="md:col-span-2 xl:col-span-2">
                    <label for="{{ $fieldPrefix }}_target_conflict_actor_id" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Ziel aus Szenenbeteiligten</label>
                    <select id="{{ $fieldPrefix }}_target_conflict_actor_id" name="target_conflict_actor_id" class="w-full px-4 py-2.5 text-sm text-stone-100">
                        <option value="">Manuell eingeben</option>
                        @foreach ($conflictActors as $conflictActorOption)
                            <option value="{{ $conflictActorOption->id }}" @selected((string) old('target_conflict_actor_id') === (string) $conflictActorOption->id)>
                                {{ $conflictActorOption->displayName() }} ({{ $conflictActorOption->isCharacter() ? 'Charakter' : 'NPC' }})
                            </option>
                        @endforeach
                    </select>
                    @error('target_conflict_actor_id')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-stone-500">Wenn gewählt, werden Verteidigung/RS nach Möglichkeit aus dem Beteiligten übernommen.</p>
                </div>

                <div>
                    <label for="{{ $fieldPrefix }}_actor_type" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Angreifer-Typ</label>
                    <select id="{{ $fieldPrefix }}_actor_type" name="actor_type" class="w-full px-4 py-2.5 text-sm text-stone-100">
                        <option value="character" @selected((string) old('actor_type', 'character') === 'character')>Charakter</option>
                        <option value="npc" @selected((string) old('actor_type') === 'npc')>NPC</option>
                    </select>
                    @error('actor_type')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="{{ $fieldPrefix }}_actor_character_id" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Angreifer-Charakter</label>
                    <select id="{{ $fieldPrefix }}_actor_character_id" name="actor_character_id" class="w-full px-4 py-2.5 text-sm text-stone-100">
                        <option value="">Charakter wählen</option>
                        @foreach ($probeCharacters as $probeCharacter)
                            <option value="{{ $probeCharacter->id }}" @selected((string) old('actor_character_id') === (string) $probeCharacter->id)>
                                {{ $probeCharacter->name }}
                                @if ($probeCharacter->relationLoaded('user') && $probeCharacter->user)
                                    ({{ $probeCharacter->user->name }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @error('actor_character_id')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="{{ $fieldPrefix }}_actor_name" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Angreifer-NPC Name</label>
                    <input
                        id="{{ $fieldPrefix }}_actor_name"
                        type="text"
                        name="actor_name"
                        value="{{ old('actor_name') }}"
                        maxlength="120"
                        placeholder="z. B. Hafenräuber I"
                        class="w-full px-4 py-2.5 text-sm text-stone-100 placeholder:text-stone-500"
                    >
                    @error('actor_name')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="{{ $fieldPrefix }}_target_type" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Ziel-Typ</label>
                    <select id="{{ $fieldPrefix }}_target_type" name="target_type" class="w-full px-4 py-2.5 text-sm text-stone-100">
                        <option value="character" @selected((string) old('target_type', 'character') === 'character')>Charakter</option>
                        <option value="npc" @selected((string) old('target_type') === 'npc')>NPC</option>
                    </select>
                    @error('target_type')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="{{ $fieldPrefix }}_target_character_id" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Ziel-Charakter</label>
                    <select id="{{ $fieldPrefix }}_target_character_id" name="target_character_id" class="w-full px-4 py-2.5 text-sm text-stone-100">
                        <option value="">Charakter wählen</option>
                        @foreach ($probeCharacters as $probeCharacter)
                            <option value="{{ $probeCharacter->id }}" @selected((string) old('target_character_id') === (string) $probeCharacter->id)>
                                {{ $probeCharacter->name }}
                                @if ($probeCharacter->relationLoaded('user') && $probeCharacter->user)
                                    ({{ $probeCharacter->user->name }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @error('target_character_id')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="{{ $fieldPrefix }}_target_name" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Ziel-NPC Name</label>
                    <input
                        id="{{ $fieldPrefix }}_target_name"
                        type="text"
                        name="target_name"
                        value="{{ old('target_name') }}"
                        maxlength="120"
                        placeholder="z. B. Hafenräuber I"
                        class="w-full px-4 py-2.5 text-sm text-stone-100 placeholder:text-stone-500"
                    >
                    @error('target_name')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2 xl:col-span-2">
                    <details class="ui-card-soft border-stone-700/70 bg-black/25 px-3 py-2" @if($actorNpcDetailsOpen) open @endif>
                        <summary class="cursor-pointer text-xs font-semibold uppercase tracking-[0.08em] text-stone-300">NPC-Werte optional (Angreifer)</summary>
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <div>
                                <label for="{{ $fieldPrefix }}_actor_le_current" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">NPC LE aktuell</label>
                                <input
                                    id="{{ $fieldPrefix }}_actor_le_current"
                                    type="number"
                                    name="actor_le_current"
                                    value="{{ old('actor_le_current') }}"
                                    min="0"
                                    class="w-full px-4 py-2.5 text-sm text-stone-100"
                                >
                                @error('actor_le_current')
                                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="{{ $fieldPrefix }}_actor_le_max" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">NPC LE max</label>
                                <input
                                    id="{{ $fieldPrefix }}_actor_le_max"
                                    type="number"
                                    name="actor_le_max"
                                    value="{{ old('actor_le_max') }}"
                                    min="0"
                                    class="w-full px-4 py-2.5 text-sm text-stone-100"
                                >
                                @error('actor_le_max')
                                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </details>
                </div>

                <div class="md:col-span-2 xl:col-span-2">
                    <details class="ui-card-soft border-stone-700/70 bg-black/25 px-3 py-2" @if($targetNpcDetailsOpen) open @endif>
                        <summary class="cursor-pointer text-xs font-semibold uppercase tracking-[0.08em] text-stone-300">NPC-Werte optional (Ziel)</summary>
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <div>
                                <label for="{{ $fieldPrefix }}_target_le_current" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">NPC LE aktuell</label>
                                <input
                                    id="{{ $fieldPrefix }}_target_le_current"
                                    type="number"
                                    name="target_le_current"
                                    value="{{ old('target_le_current') }}"
                                    min="0"
                                    class="w-full px-4 py-2.5 text-sm text-stone-100"
                                >
                                @error('target_le_current')
                                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="{{ $fieldPrefix }}_target_le_max" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">NPC LE max</label>
                                <input
                                    id="{{ $fieldPrefix }}_target_le_max"
                                    type="number"
                                    name="target_le_max"
                                    value="{{ old('target_le_max') }}"
                                    min="0"
                                    class="w-full px-4 py-2.5 text-sm text-stone-100"
                                >
                                @error('target_le_max')
                                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </details>
                </div>
            </div>
        </fieldset>

        <fieldset class="ui-card-soft border-stone-700/70 bg-black/20 p-4">
            <legend class="px-2 text-xs font-semibold uppercase tracking-[0.12em] text-amber-200">2. Angriff</legend>
            <div class="mt-3 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label for="{{ $fieldPrefix }}_weapon_name" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Waffe (optional)</label>
                    <input
                        id="{{ $fieldPrefix }}_weapon_name"
                        type="text"
                        name="weapon_name"
                        value="{{ old('weapon_name') }}"
                        maxlength="120"
                        placeholder="z. B. Langschwert"
                        class="w-full px-4 py-2.5 text-sm text-stone-100 placeholder:text-stone-500"
                    >
                    @error('weapon_name')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="{{ $fieldPrefix }}_attack_target_value" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Angriffswert</label>
                    <input
                        id="{{ $fieldPrefix }}_attack_target_value"
                        type="number"
                        name="attack_target_value"
                        value="{{ old('attack_target_value') }}"
                        min="0"
                        max="100"
                        required
                        class="w-full px-4 py-2.5 text-sm text-stone-100"
                    >
                    @error('attack_target_value')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2 xl:col-span-2 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="{{ $fieldPrefix }}_attack_roll_mode" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Wurfmodus</label>
                        <select id="{{ $fieldPrefix }}_attack_roll_mode" name="attack_roll_mode" class="w-full px-4 py-2.5 text-sm text-stone-100">
                            <option value="normal" @selected((string) old('attack_roll_mode', 'normal') === 'normal')>Normal</option>
                            <option value="advantage" @selected((string) old('attack_roll_mode') === 'advantage')>Vorteil</option>
                            <option value="disadvantage" @selected((string) old('attack_roll_mode') === 'disadvantage')>Nachteil</option>
                        </select>
                        @error('attack_roll_mode')
                            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="{{ $fieldPrefix }}_attack_modifier" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Modifikator</label>
                        <input
                            id="{{ $fieldPrefix }}_attack_modifier"
                            type="number"
                            name="attack_modifier"
                            value="{{ old('attack_modifier', 0) }}"
                            min="-100"
                            max="100"
                            class="w-full px-4 py-2.5 text-sm text-stone-100"
                        >
                        @error('attack_modifier')
                            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        </fieldset>

        <fieldset class="ui-card-soft border-stone-700/70 bg-black/20 p-4">
            <legend class="px-2 text-xs font-semibold uppercase tracking-[0.12em] text-amber-200">3. Verteidigung</legend>
            <div class="mt-3 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label for="{{ $fieldPrefix }}_defense_label" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Verteidigung (optional)</label>
                    <input
                        id="{{ $fieldPrefix }}_defense_label"
                        type="text"
                        name="defense_label"
                        value="{{ old('defense_label') }}"
                        maxlength="80"
                        placeholder="z. B. Parade"
                        class="w-full px-4 py-2.5 text-sm text-stone-100 placeholder:text-stone-500"
                    >
                    @error('defense_label')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="{{ $fieldPrefix }}_defense_target_value" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Verteidigungswert (optional)</label>
                    <input
                        id="{{ $fieldPrefix }}_defense_target_value"
                        type="number"
                        name="defense_target_value"
                        value="{{ old('defense_target_value') }}"
                        min="0"
                        max="100"
                        class="w-full px-4 py-2.5 text-sm text-stone-100"
                    >
                    @error('defense_target_value')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2 xl:col-span-2 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="{{ $fieldPrefix }}_defense_roll_mode" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Wurfmodus</label>
                        <select id="{{ $fieldPrefix }}_defense_roll_mode" name="defense_roll_mode" class="w-full px-4 py-2.5 text-sm text-stone-100">
                            <option value="normal" @selected((string) old('defense_roll_mode', 'normal') === 'normal')>Normal</option>
                            <option value="advantage" @selected((string) old('defense_roll_mode') === 'advantage')>Vorteil</option>
                            <option value="disadvantage" @selected((string) old('defense_roll_mode') === 'disadvantage')>Nachteil</option>
                        </select>
                        @error('defense_roll_mode')
                            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="{{ $fieldPrefix }}_defense_modifier" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Modifikator</label>
                        <input
                            id="{{ $fieldPrefix }}_defense_modifier"
                            type="number"
                            name="defense_modifier"
                            value="{{ old('defense_modifier', 0) }}"
                            min="-100"
                            max="100"
                            class="w-full px-4 py-2.5 text-sm text-stone-100"
                        >
                        @error('defense_modifier')
                            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        </fieldset>

        <fieldset class="ui-card-soft border-stone-700/70 bg-black/20 p-4">
            <legend class="px-2 text-xs font-semibold uppercase tracking-[0.12em] text-amber-200">4. Schaden</legend>
            <div class="mt-3 grid gap-4 md:grid-cols-2">
                <div>
                    <label for="{{ $fieldPrefix }}_damage" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Schaden</label>
                    <input
                        id="{{ $fieldPrefix }}_damage"
                        type="number"
                        name="damage"
                        value="{{ old('damage') }}"
                        min="0"
                        max="999"
                        required
                        class="w-full px-4 py-2.5 text-sm text-stone-100"
                    >
                    @error('damage')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="{{ $fieldPrefix }}_armor_protection" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">RS (optional)</label>
                    <input
                        id="{{ $fieldPrefix }}_armor_protection"
                        type="number"
                        name="armor_protection"
                        value="{{ old('armor_protection') }}"
                        min="0"
                        max="99"
                        class="w-full px-4 py-2.5 text-sm text-stone-100"
                    >
                    @error('armor_protection')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </fieldset>

        <fieldset class="ui-card-soft border-stone-700/70 bg-black/20 p-4">
            <legend class="px-2 text-xs font-semibold uppercase tracking-[0.12em] text-amber-200">5. Notizen / Absicht</legend>
            <div class="mt-3 space-y-4">
                <div>
                    <label for="{{ $fieldPrefix }}_intent_text" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Absicht (optional)</label>
                    <textarea
                        id="{{ $fieldPrefix }}_intent_text"
                        name="intent_text"
                        rows="2"
                        maxlength="500"
                        placeholder="Kurznotiz zur Absicht aus dem Thread"
                        class="w-full px-4 py-2.5 text-sm text-stone-100 placeholder:text-stone-500"
                    >{{ old('intent_text') }}</textarea>
                    @error('intent_text')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="{{ $fieldPrefix }}_resolution_note" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Auflösungsnotiz (optional)</label>
                    <textarea
                        id="{{ $fieldPrefix }}_resolution_note"
                        name="resolution_note"
                        rows="3"
                        maxlength="1000"
                        placeholder="Interne Notiz zur Auswertung"
                        class="w-full px-4 py-2.5 text-sm text-stone-100 placeholder:text-stone-500"
                    >{{ old('resolution_note') }}</textarea>
                    @error('resolution_note')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </fieldset>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="ui-btn ui-btn-accent">{{ $submitLabel }}</button>
            <p class="text-xs text-stone-400">
                Spieler nutzen weiterhin normale IC-Posts. Dieses Formular ist nur für Spielleitung und Co-Spielleitung.
            </p>
        </div>
    </form>
</section>
