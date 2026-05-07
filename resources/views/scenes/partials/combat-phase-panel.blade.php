<section id="combat-phase-tool" class="ui-card border-amber-700/50 bg-amber-950/25 p-6 sm:p-8" data-reading-mode-chrome>
    <h2 class="font-heading text-2xl text-amber-100">Kampfphase (Spielleitung)</h2>
    <p class="mt-2 text-sm text-amber-200/90">
        Spieler schreiben Absichten im Szenenthread. Die Spielleitung sammelt hier mehrere Aktionen und wertet sie gesammelt aus.
    </p>

    @if (! $openCombatPhase)
        <div class="mt-5 flex flex-wrap items-center gap-3">
            <form method="POST" action="{{ route('campaigns.scenes.combat.phases.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]) }}">
                @csrf
                <button type="submit" class="ui-btn ui-btn-accent">Kampfphase starten</button>
            </form>
            <p class="text-xs text-stone-400">Aktuell gibt es keine offene Kampfphase in dieser Szene.</p>
        </div>
        @error('combat_phase')
            <p class="mt-3 text-sm text-red-300">{{ $message }}</p>
        @enderror
    @else
        <div class="mt-5 ui-card-soft border-amber-700/40 bg-black/25 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm font-semibold uppercase tracking-[0.08em] text-amber-200">
                    Kampfphase {{ $openCombatPhase->phase_number }} · Aktionen sammeln
                </p>
                <span class="ui-badge !border-amber-700/70 !bg-amber-900/30 !text-amber-200">
                    {{ $openCombatPhaseActions->count() }} {{ $openCombatPhaseActions->count() === 1 ? 'Aktion' : 'Aktionen' }}
                </span>
            </div>

            @if ($openCombatPhaseActions->isEmpty())
                <p class="mt-3 text-sm text-stone-400">Noch keine Aktion erfasst.</p>
            @else
                <ol class="mt-3 space-y-2">
                    @foreach ($openCombatPhaseActions as $phaseAction)
                        @php
                            $actorName = trim((string) ($phaseAction->actor_name ?? ''));
                            if ($actorName === '' && $phaseAction->relationLoaded('actorCharacter') && $phaseAction->actorCharacter) {
                                $actorName = (string) $phaseAction->actorCharacter->name;
                            }
                            $targetName = trim((string) ($phaseAction->target_name ?? ''));
                            if ($targetName === '' && $phaseAction->relationLoaded('targetCharacter') && $phaseAction->targetCharacter) {
                                $targetName = (string) $phaseAction->targetCharacter->name;
                            }
                            $weaponName = trim((string) ($phaseAction->weapon_name ?? ''));
                        @endphp
                        <li class="ui-card-soft border-stone-700/70 bg-black/20 px-3 py-2 text-sm text-stone-200">
                            <p class="font-semibold text-amber-100">
                                #{{ $phaseAction->position }} {{ $actorName !== '' ? $actorName : 'Unbekannt' }} -> {{ $targetName !== '' ? $targetName : 'Unbekannt' }}
                            </p>
                            <p class="mt-1 text-xs uppercase tracking-[0.08em] text-stone-400">
                                Angriff {{ $phaseAction->attack_target_value }}
                                @if ($weaponName !== '')
                                    • {{ $weaponName }}
                                @endif
                                • Schaden {{ $phaseAction->damage }}
                                @if ($phaseAction->armor_protection !== null)
                                    • RS {{ $phaseAction->armor_protection }}
                                @endif
                            </p>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>

        @include('scenes.partials.combat-action-form', [
            'campaign' => $campaign,
            'scene' => $scene,
            'probeCharacters' => $probeCharacters,
            'conflictActors' => $conflictActors,
            'fieldPrefix' => 'combat_phase_action',
            'sectionId' => 'combat-phase-action-tool',
            'title' => 'Aktion zur offenen Kampfphase hinzufügen',
            'description' => 'Erfasse eine einzelne Aktion, die bei der Auswertung dieser offenen Kampfphase berücksichtigt wird.',
            'hint' => '',
            'formAction' => route('campaigns.scenes.combat.phases.actions.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene, 'combatPhase' => $openCombatPhase]),
            'submitLabel' => 'Aktion zur Phase hinzufügen',
        ])

        <div class="mt-5 flex flex-wrap items-center gap-3">
            <form method="POST" action="{{ route('campaigns.scenes.combat.phases.resolve', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene, 'combatPhase' => $openCombatPhase]) }}">
                @csrf
                <button type="submit" class="ui-btn ui-btn-accent">Kampfphase auswerten</button>
            </form>
            <p class="text-xs text-stone-400">Wertet alle erfassten Aktionen in Reihenfolge aus und erstellt einen Kampfblock im Thread.</p>
        </div>
        @error('combat_phase')
            <p class="mt-3 text-sm text-red-300">{{ $message }}</p>
        @enderror
    @endif
</section>
