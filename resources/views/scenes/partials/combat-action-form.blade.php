<section id="combat-action-tool" class="ui-card border-amber-800/40 bg-amber-950/15 p-6 sm:p-8" data-reading-mode-chrome>
    <h2 class="font-heading text-2xl text-amber-100">Kampfaktion (Spielleitung)</h2>
    <p class="mt-2 text-sm text-amber-200/90">
        Spieler schreiben Absichten im Thread. Die Spielleitung wertet hier eine einzelne Kampfaktion aus.
    </p>
    <p class="mt-2 text-xs uppercase tracking-[0.08em] text-amber-300/90">
        V1: Einzelaktion, keine Kampfphasen und keine Spieler-Queue.
    </p>

    <form method="POST" action="{{ route('campaigns.scenes.combat.actions.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]) }}" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @csrf

        <div>
            <label for="combat_actor_type" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Angreifer-Typ</label>
            <select id="combat_actor_type" name="actor_type" class="w-full px-4 py-2.5 text-sm text-stone-100">
                <option value="character" @selected((string) old('actor_type', 'character') === 'character')>Charakter</option>
                <option value="npc" @selected((string) old('actor_type') === 'npc')>NPC</option>
            </select>
            @error('actor_type')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="combat_actor_character_id" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Angreifer-Charakter</label>
            <select id="combat_actor_character_id" name="actor_character_id" class="w-full px-4 py-2.5 text-sm text-stone-100">
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
            <label for="combat_actor_name" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Angreifer-NPC Name</label>
            <input
                id="combat_actor_name"
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

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="combat_actor_le_current" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">NPC LE aktuell</label>
                <input
                    id="combat_actor_le_current"
                    type="number"
                    name="actor_le_current"
                    value="{{ old('actor_le_current') }}"
                    min="0"
                    class="w-full px-4 py-2.5 text-sm text-stone-100"
                >
            </div>
            <div>
                <label for="combat_actor_le_max" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">NPC LE max</label>
                <input
                    id="combat_actor_le_max"
                    type="number"
                    name="actor_le_max"
                    value="{{ old('actor_le_max') }}"
                    min="0"
                    class="w-full px-4 py-2.5 text-sm text-stone-100"
                >
            </div>
        </div>

        <div>
            <label for="combat_target_type" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Ziel-Typ</label>
            <select id="combat_target_type" name="target_type" class="w-full px-4 py-2.5 text-sm text-stone-100">
                <option value="character" @selected((string) old('target_type', 'character') === 'character')>Charakter</option>
                <option value="npc" @selected((string) old('target_type') === 'npc')>NPC</option>
            </select>
            @error('target_type')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="combat_target_character_id" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Ziel-Charakter</label>
            <select id="combat_target_character_id" name="target_character_id" class="w-full px-4 py-2.5 text-sm text-stone-100">
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
            <label for="combat_target_name" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Ziel-NPC Name</label>
            <input
                id="combat_target_name"
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

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="combat_target_le_current" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">NPC LE aktuell</label>
                <input
                    id="combat_target_le_current"
                    type="number"
                    name="target_le_current"
                    value="{{ old('target_le_current') }}"
                    min="0"
                    class="w-full px-4 py-2.5 text-sm text-stone-100"
                >
            </div>
            <div>
                <label for="combat_target_le_max" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">NPC LE max</label>
                <input
                    id="combat_target_le_max"
                    type="number"
                    name="target_le_max"
                    value="{{ old('target_le_max') }}"
                    min="0"
                    class="w-full px-4 py-2.5 text-sm text-stone-100"
                >
            </div>
        </div>

        <div>
            <label for="combat_weapon_name" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Waffe (optional)</label>
            <input
                id="combat_weapon_name"
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
            <label for="combat_attack_target_value" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Angriffswert</label>
            <input
                id="combat_attack_target_value"
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

        <div>
            <label for="combat_attack_roll_mode" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Angriffsmodus</label>
            <select id="combat_attack_roll_mode" name="attack_roll_mode" class="w-full px-4 py-2.5 text-sm text-stone-100">
                <option value="normal" @selected((string) old('attack_roll_mode', 'normal') === 'normal')>normal</option>
                <option value="advantage" @selected((string) old('attack_roll_mode') === 'advantage')>advantage</option>
                <option value="disadvantage" @selected((string) old('attack_roll_mode') === 'disadvantage')>disadvantage</option>
            </select>
            @error('attack_roll_mode')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="combat_attack_modifier" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Angriffsmodifikator</label>
            <input
                id="combat_attack_modifier"
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

        <div>
            <label for="combat_defense_label" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Verteidigung (optional)</label>
            <input
                id="combat_defense_label"
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
            <label for="combat_defense_target_value" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Verteidigungswert (optional)</label>
            <input
                id="combat_defense_target_value"
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

        <div>
            <label for="combat_defense_roll_mode" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Verteidigungsmodus</label>
            <select id="combat_defense_roll_mode" name="defense_roll_mode" class="w-full px-4 py-2.5 text-sm text-stone-100">
                <option value="normal" @selected((string) old('defense_roll_mode', 'normal') === 'normal')>normal</option>
                <option value="advantage" @selected((string) old('defense_roll_mode') === 'advantage')>advantage</option>
                <option value="disadvantage" @selected((string) old('defense_roll_mode') === 'disadvantage')>disadvantage</option>
            </select>
            @error('defense_roll_mode')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="combat_defense_modifier" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Verteidigungsmodifikator</label>
            <input
                id="combat_defense_modifier"
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

        <div>
            <label for="combat_damage" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Schaden</label>
            <input
                id="combat_damage"
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
            <label for="combat_armor_protection" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">RS (optional)</label>
            <input
                id="combat_armor_protection"
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

        <div class="md:col-span-2 xl:col-span-4">
            <label for="combat_intent_text" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Absicht (optional)</label>
            <textarea
                id="combat_intent_text"
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

        <div class="md:col-span-2 xl:col-span-4">
            <label for="combat_resolution_note" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Auflösungsnotiz (optional)</label>
            <textarea
                id="combat_resolution_note"
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

        <div class="md:col-span-2 xl:col-span-4 flex flex-wrap items-center gap-3">
            <button type="submit" class="ui-btn ui-btn-accent">
                Kampfaktion auswerten
            </button>
            <p class="text-xs text-stone-400">
                Spieler nutzen weiterhin normale IC-Posts. Dieses Formular ist nur für Spielleitung und Co-Spielleitung.
            </p>
        </div>
    </form>
</section>
