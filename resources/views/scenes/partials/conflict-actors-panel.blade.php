<section id="conflict-actors-tool" class="ui-card border-amber-800/40 bg-amber-950/15 p-6 sm:p-8" data-reading-mode-chrome>
    <h2 class="font-heading text-2xl text-amber-100">Beteiligte (Spielleitung)</h2>
    <p class="mt-2 text-sm text-amber-200/90">
        Pflege hier die Konfliktbeteiligten dieser Szene. Diese Liste ist die Grundlage für spätere Auto-Fill-Aktionen.
    </p>
    <p class="mt-1 text-xs text-amber-300/90">
        Diese Beteiligten können in Kampf- und Magieformularen ausgewählt werden.
    </p>

    @error('conflict_actor')
        <p class="mt-3 text-sm text-red-300">{{ $message }}</p>
    @enderror

    <div class="mt-5 space-y-3">
        @forelse ($conflictActors as $conflictActor)
            @php
                /** @var \App\Models\Character|null $linkedCharacter */
                $linkedCharacter = $conflictActor->relationLoaded('character') ? $conflictActor->character : null;
                $displayName = $conflictActor->displayName();
                $leCurrent = $linkedCharacter?->le_current ?? $conflictActor->le_current;
                $leMax = $linkedCharacter?->le_max ?? $conflictActor->le_max;
                $aeCurrent = $linkedCharacter?->ae_current ?? $conflictActor->ae_current;
                $aeMax = $linkedCharacter?->ae_max ?? $conflictActor->ae_max;
            @endphp
            <article class="ui-card-soft border-stone-700/70 bg-black/25 p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-amber-100">{{ $displayName }}</h3>
                        <p class="mt-1 text-xs uppercase tracking-[0.08em] text-stone-400">
                            {{ $conflictActor->isCharacter() ? 'Charakter' : 'NPC' }}
                            @if ($conflictActor->sort_order !== null)
                                • Sortierung {{ $conflictActor->sort_order }}
                            @endif
                        </p>
                        <p class="mt-2 text-sm text-stone-300">
                            @if (is_int($leCurrent) && is_int($leMax))
                                LE {{ $leCurrent }} / {{ $leMax }}
                            @endif
                            @if (is_int($aeCurrent) && is_int($aeMax))
                                @if (is_int($leCurrent) && is_int($leMax)) • @endif
                                AE {{ $aeCurrent }} / {{ $aeMax }}
                            @endif
                            @if ($conflictActor->attack_value !== null)
                                • ANG {{ $conflictActor->attack_value }}
                            @endif
                            @if ($conflictActor->defense_value !== null)
                                • PAR {{ $conflictActor->defense_value }}
                            @endif
                            @if ($conflictActor->armor_protection !== null)
                                • RS {{ $conflictActor->armor_protection }}
                            @endif
                            @if ($conflictActor->damage_value !== null)
                                • Schaden {{ $conflictActor->damage_value }}
                            @endif
                            @if ($conflictActor->spell_value !== null)
                                • Zauber {{ $conflictActor->spell_value }}
                            @endif
                        </p>
                        @if ($conflictActor->notes)
                            <p class="mt-2 text-sm text-stone-400">{{ $conflictActor->notes }}</p>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('campaigns.scenes.conflict-actors.destroy', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene, 'sceneConflictActor' => $conflictActor]) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="ui-btn !px-2.5 !py-1.5 !text-[0.68rem]">Entfernen</button>
                    </form>
                </div>

                @if ($conflictActor->isNpc())
                    <form method="POST" action="{{ route('campaigns.scenes.conflict-actors.update', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene, 'sceneConflictActor' => $conflictActor]) }}" class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        @csrf
                        @method('PATCH')

                        <div>
                            <label for="conflict_actor_{{ $conflictActor->id }}_name" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Name</label>
                            <input id="conflict_actor_{{ $conflictActor->id }}_name" type="text" name="name" value="{{ old('name', $conflictActor->name) }}" maxlength="120" class="w-full px-3 py-2 text-sm text-stone-100">
                        </div>
                        <div>
                            <label for="conflict_actor_{{ $conflictActor->id }}_le_current" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">LE aktuell</label>
                            <input id="conflict_actor_{{ $conflictActor->id }}_le_current" type="number" name="le_current" value="{{ old('le_current', $conflictActor->le_current) }}" min="0" max="999" class="w-full px-3 py-2 text-sm text-stone-100">
                        </div>
                        <div>
                            <label for="conflict_actor_{{ $conflictActor->id }}_le_max" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">LE max</label>
                            <input id="conflict_actor_{{ $conflictActor->id }}_le_max" type="number" name="le_max" value="{{ old('le_max', $conflictActor->le_max) }}" min="0" max="999" class="w-full px-3 py-2 text-sm text-stone-100">
                        </div>
                        <div>
                            <label for="conflict_actor_{{ $conflictActor->id }}_ae_current" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">AE aktuell</label>
                            <input id="conflict_actor_{{ $conflictActor->id }}_ae_current" type="number" name="ae_current" value="{{ old('ae_current', $conflictActor->ae_current) }}" min="0" max="999" class="w-full px-3 py-2 text-sm text-stone-100">
                        </div>
                        <div>
                            <label for="conflict_actor_{{ $conflictActor->id }}_ae_max" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">AE max</label>
                            <input id="conflict_actor_{{ $conflictActor->id }}_ae_max" type="number" name="ae_max" value="{{ old('ae_max', $conflictActor->ae_max) }}" min="0" max="999" class="w-full px-3 py-2 text-sm text-stone-100">
                        </div>
                        <div>
                            <label for="conflict_actor_{{ $conflictActor->id }}_attack_value" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Angriff</label>
                            <input id="conflict_actor_{{ $conflictActor->id }}_attack_value" type="number" name="attack_value" value="{{ old('attack_value', $conflictActor->attack_value) }}" min="0" max="100" class="w-full px-3 py-2 text-sm text-stone-100">
                        </div>
                        <div>
                            <label for="conflict_actor_{{ $conflictActor->id }}_defense_value" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Verteidigung</label>
                            <input id="conflict_actor_{{ $conflictActor->id }}_defense_value" type="number" name="defense_value" value="{{ old('defense_value', $conflictActor->defense_value) }}" min="0" max="100" class="w-full px-3 py-2 text-sm text-stone-100">
                        </div>
                        <div>
                            <label for="conflict_actor_{{ $conflictActor->id }}_armor_protection" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">RS</label>
                            <input id="conflict_actor_{{ $conflictActor->id }}_armor_protection" type="number" name="armor_protection" value="{{ old('armor_protection', $conflictActor->armor_protection) }}" min="0" max="999" class="w-full px-3 py-2 text-sm text-stone-100">
                        </div>
                        <div>
                            <label for="conflict_actor_{{ $conflictActor->id }}_damage_value" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Schaden</label>
                            <input id="conflict_actor_{{ $conflictActor->id }}_damage_value" type="number" name="damage_value" value="{{ old('damage_value', $conflictActor->damage_value) }}" min="0" max="999" class="w-full px-3 py-2 text-sm text-stone-100">
                        </div>
                        <div>
                            <label for="conflict_actor_{{ $conflictActor->id }}_spell_value" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Zauberwert</label>
                            <input id="conflict_actor_{{ $conflictActor->id }}_spell_value" type="number" name="spell_value" value="{{ old('spell_value', $conflictActor->spell_value) }}" min="0" max="100" class="w-full px-3 py-2 text-sm text-stone-100">
                        </div>
                        <div>
                            <label for="conflict_actor_{{ $conflictActor->id }}_sort_order" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Sortierung</label>
                            <input id="conflict_actor_{{ $conflictActor->id }}_sort_order" type="number" name="sort_order" value="{{ old('sort_order', $conflictActor->sort_order) }}" min="1" max="1000000" class="w-full px-3 py-2 text-sm text-stone-100">
                        </div>
                        <div class="md:col-span-2 xl:col-span-4">
                            <label for="conflict_actor_{{ $conflictActor->id }}_notes" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Notizen</label>
                            <textarea id="conflict_actor_{{ $conflictActor->id }}_notes" name="notes" rows="2" maxlength="1000" class="w-full px-3 py-2 text-sm text-stone-100">{{ old('notes', $conflictActor->notes) }}</textarea>
                        </div>
                        <div class="md:col-span-2 xl:col-span-4">
                            <button type="submit" class="ui-btn ui-btn-accent !px-3 !py-2 !text-[0.68rem]">NPC-Beteiligten speichern</button>
                        </div>
                    </form>
                @else
                    <p class="mt-3 text-xs text-stone-500">Charakter-Beteiligte nutzen live die aktuellen Character-Werte als Quelle.</p>
                @endif
            </article>
        @empty
            <p class="ui-card-soft border-stone-700/70 bg-black/25 px-4 py-3 text-sm text-stone-400">
                Noch keine Beteiligten hinterlegt.
            </p>
        @endforelse
    </div>

    <div class="mt-6 grid gap-4 xl:grid-cols-2">
        <form method="POST" action="{{ route('campaigns.scenes.conflict-actors.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]) }}" class="ui-card-soft border-stone-700/70 bg-black/25 p-4">
            @csrf
            <input type="hidden" name="actor_type" value="character">
            <h3 class="font-semibold text-amber-100">Charakter hinzufügen</h3>
            <p class="mt-1 text-xs text-stone-400">Fügt einen aktiven Kampagnenteilnehmer als Beteiligten zur Szene hinzu.</p>
            <div class="mt-3 grid gap-3">
                <div>
                    <label for="conflict_add_character_character_id" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Charakter</label>
                    <select id="conflict_add_character_character_id" name="character_id" class="w-full px-3 py-2 text-sm text-stone-100">
                        <option value="">Charakter wählen</option>
                        @foreach ($probeCharacters as $probeCharacter)
                            <option value="{{ $probeCharacter->id }}" @selected((string) old('character_id') === (string) $probeCharacter->id)>
                                {{ $probeCharacter->name }}
                                @if ($probeCharacter->relationLoaded('user') && $probeCharacter->user)
                                    ({{ $probeCharacter->user->name }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @error('character_id')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="conflict_add_character_sort_order" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Sortierung (optional)</label>
                    <input id="conflict_add_character_sort_order" type="number" name="sort_order" value="{{ old('sort_order') }}" min="1" max="1000000" class="w-full px-3 py-2 text-sm text-stone-100">
                    @error('sort_order')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <button type="submit" class="ui-btn ui-btn-accent !px-3 !py-2 !text-[0.68rem]">Charakter-Beteiligten hinzufügen</button>
                </div>
            </div>
        </form>

        <form method="POST" action="{{ route('campaigns.scenes.conflict-actors.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]) }}" class="ui-card-soft border-stone-700/70 bg-black/25 p-4">
            @csrf
            <input type="hidden" name="actor_type" value="npc">
            <h3 class="font-semibold text-amber-100">NPC hinzufügen</h3>
            <p class="mt-1 text-xs text-stone-400">Erstellt einen szenenbezogenen NPC-Beteiligten ohne globales NPC-Modell.</p>
            <div class="mt-3 grid gap-3 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label for="conflict_add_npc_name" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Name</label>
                    <input id="conflict_add_npc_name" type="text" name="name" value="{{ old('name') }}" maxlength="120" class="w-full px-3 py-2 text-sm text-stone-100">
                    @error('name')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="conflict_add_npc_le_current" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">LE aktuell</label>
                    <input id="conflict_add_npc_le_current" type="number" name="le_current" value="{{ old('le_current') }}" min="0" max="999" class="w-full px-3 py-2 text-sm text-stone-100">
                </div>
                <div>
                    <label for="conflict_add_npc_le_max" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">LE max</label>
                    <input id="conflict_add_npc_le_max" type="number" name="le_max" value="{{ old('le_max') }}" min="0" max="999" class="w-full px-3 py-2 text-sm text-stone-100">
                </div>
                <div>
                    <label for="conflict_add_npc_ae_current" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">AE aktuell</label>
                    <input id="conflict_add_npc_ae_current" type="number" name="ae_current" value="{{ old('ae_current') }}" min="0" max="999" class="w-full px-3 py-2 text-sm text-stone-100">
                </div>
                <div>
                    <label for="conflict_add_npc_ae_max" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">AE max</label>
                    <input id="conflict_add_npc_ae_max" type="number" name="ae_max" value="{{ old('ae_max') }}" min="0" max="999" class="w-full px-3 py-2 text-sm text-stone-100">
                </div>
                <div>
                    <label for="conflict_add_npc_attack" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Angriff</label>
                    <input id="conflict_add_npc_attack" type="number" name="attack_value" value="{{ old('attack_value') }}" min="0" max="100" class="w-full px-3 py-2 text-sm text-stone-100">
                </div>
                <div>
                    <label for="conflict_add_npc_defense" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Verteidigung</label>
                    <input id="conflict_add_npc_defense" type="number" name="defense_value" value="{{ old('defense_value') }}" min="0" max="100" class="w-full px-3 py-2 text-sm text-stone-100">
                </div>
                <div>
                    <label for="conflict_add_npc_armor" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">RS</label>
                    <input id="conflict_add_npc_armor" type="number" name="armor_protection" value="{{ old('armor_protection') }}" min="0" max="999" class="w-full px-3 py-2 text-sm text-stone-100">
                </div>
                <div>
                    <label for="conflict_add_npc_damage" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Schaden</label>
                    <input id="conflict_add_npc_damage" type="number" name="damage_value" value="{{ old('damage_value') }}" min="0" max="999" class="w-full px-3 py-2 text-sm text-stone-100">
                </div>
                <div>
                    <label for="conflict_add_npc_spell" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Zauberwert</label>
                    <input id="conflict_add_npc_spell" type="number" name="spell_value" value="{{ old('spell_value') }}" min="0" max="100" class="w-full px-3 py-2 text-sm text-stone-100">
                </div>
                <div>
                    <label for="conflict_add_npc_sort_order" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Sortierung (optional)</label>
                    <input id="conflict_add_npc_sort_order" type="number" name="sort_order" value="{{ old('sort_order') }}" min="1" max="1000000" class="w-full px-3 py-2 text-sm text-stone-100">
                </div>
                <div class="md:col-span-2">
                    <label for="conflict_add_npc_notes" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Notizen</label>
                    <textarea id="conflict_add_npc_notes" name="notes" rows="2" maxlength="1000" class="w-full px-3 py-2 text-sm text-stone-100">{{ old('notes') }}</textarea>
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="ui-btn ui-btn-accent !px-3 !py-2 !text-[0.68rem]">NPC-Beteiligten hinzufügen</button>
                </div>
            </div>
        </form>
    </div>
</section>
