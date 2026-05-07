@php
    $sectionId = $sectionId ?? 'magic-action-tool';
    $title = $title ?? 'Magieaktion (Spielleitung)';
    $description = $description ?? 'Spieler schreiben Absichten im Thread. Die Spielleitung wertet hier eine einzelne Magieaktion aus.';
    $hint = $hint ?? 'V1: Einzelaktion, keine Magie in Kampfphasen und keine Spieler-Queue.';
    $formAction = $formAction ?? route('campaigns.scenes.magic.actions.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]);
    $submitLabel = $submitLabel ?? 'Magieaktion auswerten';
    $conflictActors = $conflictActors ?? collect();

    $actorNpcDetailsOpen = $errors->hasAny(['actor_ae_current', 'actor_ae_max']);
    $targetNpcDetailsOpen = $errors->hasAny(['target_le_current', 'target_le_max', 'target_ae_current', 'target_ae_max']);
@endphp

<section id="{{ $sectionId }}" class="ui-card border-sky-700/50 bg-sky-950/20 p-6 sm:p-8" data-reading-mode-chrome>
    <h2 class="font-heading text-2xl text-sky-100">{{ $title }}</h2>
    <p class="mt-2 text-sm text-sky-200/90">
        {{ $description }}
    </p>
    @if (is_string($hint) && trim($hint) !== '')
        <p class="mt-2 text-xs uppercase tracking-[0.08em] text-sky-300/90">
            {{ $hint }}
        </p>
    @endif

    <form method="POST" action="{{ $formAction }}" class="mt-5 space-y-5">
        @csrf

        <fieldset class="ui-card-soft border-stone-700/70 bg-black/20 p-4">
            <legend class="px-2 text-xs font-semibold uppercase tracking-[0.12em] text-sky-200">1. Beteiligte</legend>
            <div class="mt-3 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="md:col-span-2 xl:col-span-2">
                    <label for="magic_actor_conflict_actor_id" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Zaubernder aus Szenenbeteiligten</label>
                    <select id="magic_actor_conflict_actor_id" name="actor_conflict_actor_id" class="w-full px-4 py-2.5 text-sm text-stone-100">
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
                    <p class="mt-1 text-xs text-stone-500">Wenn gewählt, wird der Zauberwert nach Möglichkeit aus dem Beteiligten übernommen.</p>
                </div>

                <div class="md:col-span-2 xl:col-span-2">
                    <label for="magic_target_conflict_actor_id" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Ziel aus Szenenbeteiligten</label>
                    <select id="magic_target_conflict_actor_id" name="target_conflict_actor_id" class="w-full px-4 py-2.5 text-sm text-stone-100">
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
                    <p class="mt-1 text-xs text-stone-500">Wenn gewählt, wird Magieabwehr nach Möglichkeit aus dem Beteiligten übernommen.</p>
                </div>

                <div>
                    <label for="magic_actor_type" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Zaubernder-Typ</label>
                    <select id="magic_actor_type" name="actor_type" class="w-full px-4 py-2.5 text-sm text-stone-100">
                        <option value="character" @selected((string) old('actor_type', 'character') === 'character')>Charakter</option>
                        <option value="npc" @selected((string) old('actor_type') === 'npc')>NPC</option>
                    </select>
                    @error('actor_type')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="magic_actor_character_id" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Zaubernder-Charakter</label>
                    <select id="magic_actor_character_id" name="actor_character_id" class="w-full px-4 py-2.5 text-sm text-stone-100">
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
                    <label for="magic_actor_name" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Zaubernder-NPC Name</label>
                    <input
                        id="magic_actor_name"
                        type="text"
                        name="actor_name"
                        value="{{ old('actor_name') }}"
                        maxlength="120"
                        placeholder="z. B. Tempelhexe"
                        class="w-full px-4 py-2.5 text-sm text-stone-100 placeholder:text-stone-500"
                    >
                    @error('actor_name')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="magic_target_type" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Ziel-Typ</label>
                    <select id="magic_target_type" name="target_type" class="w-full px-4 py-2.5 text-sm text-stone-100">
                        <option value="character" @selected((string) old('target_type', 'character') === 'character')>Charakter</option>
                        <option value="npc" @selected((string) old('target_type') === 'npc')>NPC</option>
                    </select>
                    @error('target_type')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="magic_target_character_id" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Ziel-Charakter</label>
                    <select id="magic_target_character_id" name="target_character_id" class="w-full px-4 py-2.5 text-sm text-stone-100">
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
                    <label for="magic_target_name" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Ziel-NPC Name</label>
                    <input
                        id="magic_target_name"
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
                        <summary class="cursor-pointer text-xs font-semibold uppercase tracking-[0.08em] text-stone-300">NPC-Werte optional (Zaubernder)</summary>
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <div>
                                <label for="magic_actor_ae_current" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">NPC AE aktuell</label>
                                <input
                                    id="magic_actor_ae_current"
                                    type="number"
                                    name="actor_ae_current"
                                    value="{{ old('actor_ae_current') }}"
                                    min="0"
                                    max="999"
                                    class="w-full px-4 py-2.5 text-sm text-stone-100"
                                >
                                @error('actor_ae_current')
                                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="magic_actor_ae_max" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">NPC AE max</label>
                                <input
                                    id="magic_actor_ae_max"
                                    type="number"
                                    name="actor_ae_max"
                                    value="{{ old('actor_ae_max') }}"
                                    min="0"
                                    max="999"
                                    class="w-full px-4 py-2.5 text-sm text-stone-100"
                                >
                                @error('actor_ae_max')
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
                                <label for="magic_target_le_current" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">NPC LE aktuell</label>
                                <input
                                    id="magic_target_le_current"
                                    type="number"
                                    name="target_le_current"
                                    value="{{ old('target_le_current') }}"
                                    min="0"
                                    max="999"
                                    class="w-full px-4 py-2.5 text-sm text-stone-100"
                                >
                                @error('target_le_current')
                                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="magic_target_le_max" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">NPC LE max</label>
                                <input
                                    id="magic_target_le_max"
                                    type="number"
                                    name="target_le_max"
                                    value="{{ old('target_le_max') }}"
                                    min="0"
                                    max="999"
                                    class="w-full px-4 py-2.5 text-sm text-stone-100"
                                >
                                @error('target_le_max')
                                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="magic_target_ae_current" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">NPC AE aktuell</label>
                                <input
                                    id="magic_target_ae_current"
                                    type="number"
                                    name="target_ae_current"
                                    value="{{ old('target_ae_current') }}"
                                    min="0"
                                    max="999"
                                    class="w-full px-4 py-2.5 text-sm text-stone-100"
                                >
                                @error('target_ae_current')
                                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="magic_target_ae_max" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">NPC AE max</label>
                                <input
                                    id="magic_target_ae_max"
                                    type="number"
                                    name="target_ae_max"
                                    value="{{ old('target_ae_max') }}"
                                    min="0"
                                    max="999"
                                    class="w-full px-4 py-2.5 text-sm text-stone-100"
                                >
                                @error('target_ae_max')
                                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </details>
                </div>
            </div>
        </fieldset>

        <fieldset class="ui-card-soft border-stone-700/70 bg-black/20 p-4">
            <legend class="px-2 text-xs font-semibold uppercase tracking-[0.12em] text-sky-200">2. Zauber</legend>
            <div class="mt-3 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label for="magic_spell_name" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Zaubername</label>
                    <input
                        id="magic_spell_name"
                        type="text"
                        name="spell_name"
                        value="{{ old('spell_name') }}"
                        maxlength="120"
                        required
                        placeholder="z. B. Flammenstoß"
                        class="w-full px-4 py-2.5 text-sm text-stone-100 placeholder:text-stone-500"
                    >
                    @error('spell_name')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="magic_spell_target_value" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Zauberwert</label>
                    <input
                        id="magic_spell_target_value"
                        type="number"
                        name="spell_target_value"
                        value="{{ old('spell_target_value') }}"
                        min="0"
                        max="100"
                        required
                        class="w-full px-4 py-2.5 text-sm text-stone-100"
                    >
                    @error('spell_target_value')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="magic_ae_cost" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">AE-Kosten</label>
                    <input
                        id="magic_ae_cost"
                        type="number"
                        name="ae_cost"
                        value="{{ old('ae_cost', 0) }}"
                        min="0"
                        max="999"
                        required
                        class="w-full px-4 py-2.5 text-sm text-stone-100"
                    >
                    @error('ae_cost')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid gap-3 sm:grid-cols-2 sm:col-span-2 xl:col-span-1">
                    <div>
                        <label for="magic_spell_roll_mode" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Wurfmodus</label>
                        <select id="magic_spell_roll_mode" name="spell_roll_mode" class="w-full px-4 py-2.5 text-sm text-stone-100">
                            <option value="normal" @selected((string) old('spell_roll_mode', 'normal') === 'normal')>Normal</option>
                            <option value="advantage" @selected((string) old('spell_roll_mode') === 'advantage')>Vorteil</option>
                            <option value="disadvantage" @selected((string) old('spell_roll_mode') === 'disadvantage')>Nachteil</option>
                        </select>
                        @error('spell_roll_mode')
                            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="magic_spell_modifier" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Modifikator</label>
                        <input
                            id="magic_spell_modifier"
                            type="number"
                            name="spell_modifier"
                            value="{{ old('spell_modifier', 0) }}"
                            min="-100"
                            max="100"
                            class="w-full px-4 py-2.5 text-sm text-stone-100"
                        >
                        @error('spell_modifier')
                            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        </fieldset>

        <fieldset class="ui-card-soft border-stone-700/70 bg-black/20 p-4">
            <legend class="px-2 text-xs font-semibold uppercase tracking-[0.12em] text-sky-200">3. Magieabwehr</legend>
            <div class="mt-3 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label for="magic_defense_label" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Magieabwehr (optional)</label>
                    <input
                        id="magic_defense_label"
                        type="text"
                        name="defense_label"
                        value="{{ old('defense_label') }}"
                        maxlength="80"
                        placeholder="z. B. Bannkreis"
                        class="w-full px-4 py-2.5 text-sm text-stone-100 placeholder:text-stone-500"
                    >
                    @error('defense_label')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="magic_defense_target_value" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Magieabwehr-Wert (optional)</label>
                    <input
                        id="magic_defense_target_value"
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
                        <label for="magic_defense_roll_mode" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Wurfmodus</label>
                        <select id="magic_defense_roll_mode" name="defense_roll_mode" class="w-full px-4 py-2.5 text-sm text-stone-100">
                            <option value="normal" @selected((string) old('defense_roll_mode', 'normal') === 'normal')>Normal</option>
                            <option value="advantage" @selected((string) old('defense_roll_mode') === 'advantage')>Vorteil</option>
                            <option value="disadvantage" @selected((string) old('defense_roll_mode') === 'disadvantage')>Nachteil</option>
                        </select>
                        @error('defense_roll_mode')
                            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="magic_defense_modifier" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Modifikator</label>
                        <input
                            id="magic_defense_modifier"
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
            <legend class="px-2 text-xs font-semibold uppercase tracking-[0.12em] text-sky-200">4. Wirkung</legend>
            <div class="mt-3 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label for="magic_effect_type" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Effektart</label>
                    <select id="magic_effect_type" name="effect_type" class="w-full px-4 py-2.5 text-sm text-stone-100" required>
                        <option value="le_damage" @selected((string) old('effect_type', 'le_damage') === 'le_damage')>LE-Schaden</option>
                        <option value="le_heal" @selected((string) old('effect_type') === 'le_heal')>LE-Heilung</option>
                        <option value="ae_damage" @selected((string) old('effect_type') === 'ae_damage')>AE-Verlust</option>
                        <option value="attribute_delta" @selected((string) old('effect_type') === 'attribute_delta')>Attribut-Modifikator</option>
                        <option value="narrative" @selected((string) old('effect_type') === 'narrative')>Erzählerisch</option>
                    </select>
                    @error('effect_type')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                    <ul class="mt-2 space-y-1 text-xs text-stone-400">
                        <li>LE-Schaden: Ziel verliert Lebensenergie.</li>
                        <li>LE-Heilung: Ziel erhält Lebensenergie zurück.</li>
                        <li>AE-Verlust: Ziel verliert Astralenergie.</li>
                        <li>Attribut-Modifikator: aktueller Attributwert wird verändert; Maximalwert bleibt gleich.</li>
                        <li>Erzählerisch: keine automatische Wertänderung.</li>
                    </ul>
                </div>

                <div>
                    <label for="magic_effect_amount" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Effektbetrag</label>
                    <input
                        id="magic_effect_amount"
                        type="number"
                        name="effect_amount"
                        value="{{ old('effect_amount', 0) }}"
                        min="-999"
                        max="999"
                        class="w-full px-4 py-2.5 text-sm text-stone-100"
                    >
                    @error('effect_amount')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="magic_target_attribute_key" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Zielattribut (bei Attribut-Effekt)</label>
                    <select id="magic_target_attribute_key" name="target_attribute_key" class="w-full px-4 py-2.5 text-sm text-stone-100">
                        <option value="">Attribut wählen</option>
                        <option value="mu" @selected((string) old('target_attribute_key') === 'mu')>MU - Mut</option>
                        <option value="kl" @selected((string) old('target_attribute_key') === 'kl')>KL - Klugheit</option>
                        <option value="in" @selected((string) old('target_attribute_key') === 'in')>IN - Intuition</option>
                        <option value="ch" @selected((string) old('target_attribute_key') === 'ch')>CH - Charisma</option>
                        <option value="ff" @selected((string) old('target_attribute_key') === 'ff')>FF - Fingerfertigkeit</option>
                        <option value="ge" @selected((string) old('target_attribute_key') === 'ge')>GE - Gewandtheit</option>
                        <option value="ko" @selected((string) old('target_attribute_key') === 'ko')>KO - Konstitution</option>
                        <option value="kk" @selected((string) old('target_attribute_key') === 'kk')>KK - Körperkraft</option>
                    </select>
                    @error('target_attribute_key')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </fieldset>

        <fieldset class="ui-card-soft border-stone-700/70 bg-black/20 p-4">
            <legend class="px-2 text-xs font-semibold uppercase tracking-[0.12em] text-sky-200">5. Notizen / Absicht</legend>
            <div class="mt-3 space-y-4">
                <div>
                    <label for="magic_intent_text" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Absicht (optional)</label>
                    <textarea
                        id="magic_intent_text"
                        name="intent_text"
                        rows="2"
                        maxlength="500"
                        placeholder="Kurzbeschreibung der beabsichtigten Wirkung."
                        class="w-full px-4 py-2.5 text-sm text-stone-100 placeholder:text-stone-500"
                    >{{ old('intent_text') }}</textarea>
                    @error('intent_text')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="magic_resolution_note" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Auflösungsnotiz (optional)</label>
                    <textarea
                        id="magic_resolution_note"
                        name="resolution_note"
                        rows="2"
                        maxlength="1000"
                        placeholder="Interne Notiz der Spielleitung zur Auswertung."
                        class="w-full px-4 py-2.5 text-sm text-stone-100 placeholder:text-stone-500"
                    >{{ old('resolution_note') }}</textarea>
                    @error('resolution_note')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </fieldset>

        <div>
            <button type="submit" class="ui-btn ui-btn-accent">{{ $submitLabel }}</button>
        </div>
    </form>
</section>
