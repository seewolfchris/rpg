@extends('layouts.auth')

@section('title', $character->name.' | Charakter')

@section('content')
    @php
        $sheet = (array) config('character_sheet', []);
        $attributeMeta = (array) data_get($sheet, 'attributes', []);
        $statusConfig = (array) config('characters.statuses', []);
        $statusKey = (string) ($character->status ?: config('characters.default_status', 'active'));
        $statusMeta = (array) data_get($statusConfig, $statusKey, data_get($statusConfig, 'active', []));
        $statusLabel = (string) ($statusMeta['label'] ?? ucfirst($statusKey));
        $statusBadgeClass = (string) ($statusMeta['badge_class'] ?? 'border-stone-600/80 bg-stone-900/35 text-stone-200');
        $effectiveAttributes = (array) ($character->effective_attributes ?? []);
        $legacyMap = (array) data_get($sheet, 'legacy_column_map', []);
        $originLabel = (string) data_get($sheet, 'origins.'.$character->origin, $character->origin ?: '-');
        $speciesLabel = (string) data_get($sheet, 'species.'.$character->species.'.label', $character->species ?: '-');
        $callingLabel = (string) data_get($sheet, 'callings.'.$character->calling.'.label', $character->calling ?: '-');
        $inventoryEntries = collect(is_array($character->inventory) ? $character->inventory : [])
            ->map(static function ($entry): array {
                if (is_string($entry)) {
                    return [
                        'name' => trim($entry),
                        'quantity' => 1,
                        'equipped' => false,
                    ];
                }

                if (! is_array($entry)) {
                    return [
                        'name' => '',
                        'quantity' => 1,
                        'equipped' => false,
                    ];
                }

                return [
                    'name' => trim((string) ($entry['name'] ?? $entry['item'] ?? '')),
                    'quantity' => max(1, min(999, (int) ($entry['quantity'] ?? $entry['qty'] ?? 1))),
                    'equipped' => (bool) ($entry['equipped'] ?? false),
                ];
            })
            ->filter(static fn (array $entry): bool => $entry['name'] !== '')
            ->values();
        $armorEntries = collect($character->normalizedArmors());
        $equippedArmorEntries = $armorEntries->where('equipped', true);
        $effectiveArmorEntries = $equippedArmorEntries->isNotEmpty() ? $equippedArmorEntries->values() : $armorEntries;
        $totalArmorProtection = $effectiveArmorEntries
            ->sum(static fn (array $armor): int => max(0, (int) ($armor['protection'] ?? 0)));
        $inventoryLogs = isset($inventoryLogs) ? collect($inventoryLogs) : collect();
        $progressionState = is_array($progressionState ?? null) ? $progressionState : [];
        $progressionEvents = isset($progressionEvents) ? collect($progressionEvents) : collect();
        $currentLevel = max(1, (int) ($progressionState['level'] ?? 1));
        $xpTotal = max(0, (int) ($progressionState['xp_total'] ?? 0));
        $xpNextLevelThreshold = max(0, (int) ($progressionState['xp_next_level_threshold'] ?? 0));
        $xpToNextLevel = max(0, (int) ($progressionState['xp_to_next_level'] ?? 0));
        $progressPercent = (float) ($progressionState['progress_percent'] ?? 0);
        $attributePointsUnspent = max(0, (int) ($progressionState['attribute_points_unspent'] ?? 0));
        $canSpendAttributePoints = auth()->id() === (int) $character->user_id || auth()->user()->isGmOrAdmin();
        $errorKeys = collect($errors->getMessages())->keys();
        $hasAllocationErrors = $errorKeys->contains(
            static fn (string $key): bool => str_starts_with($key, 'attribute_allocations')
        );
        $resolveBaseAttribute = function ($characterModel, string $attributeKey) use ($legacyMap): int {
            $value = $characterModel->{$attributeKey};
            if ($value !== null) {
                return (int) $value;
            }

            $legacyColumn = array_search($attributeKey, $legacyMap, true);
            if (! is_string($legacyColumn)) {
                return 40;
            }

            $legacyValue = $characterModel->{$legacyColumn};
            if ($legacyValue === null) {
                return 40;
            }

            $percent = (int) $legacyValue <= 20
                ? (int) round(((int) $legacyValue) * 5)
                : (int) $legacyValue;

            return max(30, min(60, $percent));
        };
    @endphp

    <section class="character-living-document mx-auto w-full max-w-6xl space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Charakterbogen</p>
                <h1 class="font-heading break-words text-2xl text-stone-100 sm:text-3xl">{{ $character->name }}</h1>
                @if ($character->epithet)
                    <p class="mt-1 break-words text-lg text-amber-300/90">{{ $character->epithet }}</p>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a
                    href="{{ route('characters.edit', $character) }}"
                    class="ui-btn inline-flex"
                >
                    Bearbeiten
                </a>
                <form method="POST" action="{{ route('characters.destroy', $character) }}" data-confirm="Diesen Charakter wirklich löschen?">
                    @csrf
                    @method('DELETE')
                    <button
                        type="submit"
                        class="ui-btn ui-btn-danger inline-flex"
                    >
                        Löschen
                    </button>
                </form>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[20rem_1fr]">
            <aside class="character-paper-panel ui-card-soft space-y-4 rounded-xl border border-stone-800/85 bg-neutral-900/70 p-4">
                <img
                    src="{{ $character->avatarUrl() }}"
                    alt="Porträt von {{ $character->name }}"
                    class="h-72 w-full rounded-lg object-cover"
                >

                <p class="inline-flex w-full items-center justify-center rounded border px-3 py-2 text-xs font-semibold uppercase tracking-[0.08em] {{ $statusBadgeClass }}">
                    Status: {{ $statusLabel }}
                </p>

                <div class="grid gap-2 text-xs uppercase tracking-[0.08em] text-stone-300">
                    <p class="rounded border border-stone-700/80 bg-black/35 px-3 py-2 break-words">Herkunft: {{ $originLabel }}</p>
                    <p class="rounded border border-stone-700/80 bg-black/35 px-3 py-2 break-words">Spezies: {{ $speciesLabel }}</p>
                    <p class="rounded border border-stone-700/80 bg-black/35 px-3 py-2 break-words">Berufung: {{ $callingLabel }}</p>
                </div>

                <div class="grid grid-cols-2 gap-2 text-sm text-stone-200">
                    <div class="rounded border border-red-700/70 bg-red-950/25 px-3 py-2">LE: {{ $character->le_current ?? 0 }} / {{ $character->le_max ?? 0 }}</div>
                    <div class="rounded border border-sky-700/70 bg-sky-950/25 px-3 py-2">AE: {{ $character->ae_current ?? 0 }} / {{ $character->ae_max ?? 0 }}</div>
                </div>

                <div class="rounded border border-emerald-700/60 bg-emerald-900/15 p-3">
                    <p class="text-xs uppercase tracking-[0.08em] text-emerald-200">Entwicklung</p>
                    <p class="mt-2 text-sm text-emerald-100">Stufe {{ $currentLevel }}</p>
                    <p class="mt-1 text-xs text-emerald-200/80">XP: {{ $xpTotal }} / {{ $xpNextLevelThreshold }} (nächste Stufe)</p>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-emerald-950/70">
                        <div class="h-full bg-emerald-500/80" style="width: {{ max(0, min($progressPercent, 100)) }}%;"></div>
                    </div>
                    <p class="mt-2 text-xs text-emerald-200/80">Noch {{ $xpToNextLevel }} XP bis Stufe {{ $currentLevel + 1 }}</p>
                    <p class="mt-2 text-xs uppercase tracking-[0.08em] text-emerald-300">Unverteilte AP: {{ $attributePointsUnspent }}</p>
                </div>
            </aside>

            <article class="character-paper-panel ui-card-soft space-y-5 rounded-xl border border-stone-800/85 bg-black/45 p-5 sm:p-6">
                <section class="rounded-lg border border-emerald-700/60 bg-emerald-950/15 p-4">
                    <h2 class="font-heading text-2xl text-emerald-100">Entwicklung</h2>
                    <p class="mt-2 text-sm text-emerald-200/90">
                        Attributsteigerungen sind nur über Attributpunkte (AP) aus Stufenaufstiegen möglich.
                    </p>

                    @if ($canSpendAttributePoints && $attributePointsUnspent > 0)
                        <form method="POST" action="{{ route('characters.progression.spend', $character) }}" class="mt-4 space-y-3">
                            @csrf

                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach ($attributeMeta as $key => $meta)
                                    @php
                                        $label = (string) ($meta['label'] ?? strtoupper($key));
                                    @endphp
                                    <label class="rounded border border-emerald-700/50 bg-black/25 px-3 py-2 text-xs uppercase tracking-[0.08em] text-emerald-200">
                                        {{ $label }}
                                        <input
                                            type="number"
                                            name="attribute_allocations[{{ $key }}]"
                                            min="0"
                                            max="{{ min(4, $attributePointsUnspent) }}"
                                            step="1"
                                            value="{{ old('attribute_allocations.'.$key, 0) }}"
                                            class="mt-2 w-full rounded border border-stone-700/80 bg-black/40 px-2 py-1 text-sm normal-case tracking-normal text-stone-100 outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-500/30"
                                        >
                                    </label>
                                @endforeach
                            </div>

                            <div>
                                <label for="progression-note" class="mb-2 block text-xs uppercase tracking-[0.08em] text-emerald-200">Notiz (optional)</label>
                                <input
                                    id="progression-note"
                                    type="text"
                                    name="note"
                                    maxlength="500"
                                    value="{{ old('note', '') }}"
                                    placeholder="z. B. Kapitel 3: Fokus auf körperliche Belastbarkeit"
                                    class="w-full rounded border border-stone-700/80 bg-black/40 px-3 py-2 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-500/30"
                                >
                            </div>

                            @if ($hasAllocationErrors)
                                <div class="rounded border border-red-700/70 bg-red-950/30 p-3 text-sm text-red-200">
                                    @foreach ($errors->all() as $message)
                                        @if (str_contains(strtolower($message), 'attribut') || str_contains(strtolower($message), 'punkt'))
                                            <p>{{ $message }}</p>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            <button type="submit" class="ui-btn ui-btn-success">
                                AP verteilen
                            </button>
                        </form>
                    @else
                        @if ($attributePointsUnspent <= 0)
                            <p class="mt-3 text-sm text-emerald-200/80">Aktuell sind keine unverteilten Attributpunkte verfügbar.</p>
                        @endif
                    @endif
                </section>

                @include('characters.partials.inline-editor', ['character' => $character])

                <section>
                    <h3 class="font-heading text-xl text-stone-100">Grundeigenschaften</h3>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        @foreach ($attributeMeta as $key => $meta)
                            @php
                                $label = (string) ($meta['label'] ?? strtoupper($key));
                                $description = (string) ($meta['description'] ?? '');
                                $baseValue = $resolveBaseAttribute($character, $key);
                                $effectiveValue = (int) ($effectiveAttributes[$key] ?? $baseValue);
                                $note = (string) ($character->{$key.'_note'} ?? '');
                            @endphp
                            <article class="rounded-lg border border-stone-700/80 bg-black/30 p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-xs font-semibold uppercase tracking-widest text-stone-300">{{ $label }}</p>
                                    <p class="text-sm text-stone-100">
                                        {{ $baseValue }} %
                                        @if ($effectiveValue !== $baseValue)
                                            <span class="text-xs text-amber-300">(effektiv {{ $effectiveValue }} %)</span>
                                        @endif
                                    </p>
                                </div>
                                @if ($description !== '')
                                    <p class="mt-2 text-xs leading-relaxed text-stone-500">{{ $description }}</p>
                                @endif
                                @if ($note !== '')
                                    <p class="mt-2 text-xs leading-relaxed text-stone-400">{{ $note }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>

                <section class="grid gap-3 sm:grid-cols-2">
                    <article class="rounded-lg border border-emerald-700/60 bg-emerald-900/15 p-3">
                        <h4 class="text-xs font-semibold uppercase tracking-widest text-emerald-200">Vorteile</h4>
                        @if (is_array($character->advantages) && count($character->advantages) > 0)
                            <ul class="mt-2 space-y-1 text-sm text-emerald-100/90">
                                @foreach ($character->advantages as $advantage)
                                    <li class="break-words">- {{ $advantage }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-2 text-sm text-emerald-100/80">Keine Einträge.</p>
                        @endif
                    </article>

                    <article class="rounded-lg border border-red-700/60 bg-red-900/15 p-3">
                        <h4 class="text-xs font-semibold uppercase tracking-widest text-red-200">Nachteile</h4>
                        @if (is_array($character->disadvantages) && count($character->disadvantages) > 0)
                            <ul class="mt-2 space-y-1 text-sm text-red-100/90">
                                @foreach ($character->disadvantages as $disadvantage)
                                    <li class="break-words">- {{ $disadvantage }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-2 text-sm text-red-100/80">Keine Einträge.</p>
                        @endif
                    </article>
                </section>

                <section class="grid gap-3 lg:grid-cols-3">
                    <article class="rounded-lg border border-emerald-700/50 bg-emerald-950/10 p-3">
                        <h4 class="text-xs font-semibold uppercase tracking-widest text-emerald-200">Inventar</h4>
                        @if ($inventoryEntries->isNotEmpty())
                            <ul class="mt-2 space-y-1 text-sm text-emerald-100/90">
                                @foreach ($inventoryEntries as $inventoryEntry)
                                    <li class="break-words">
                                        - {{ $inventoryEntry['quantity'] }}x {{ $inventoryEntry['name'] }}
                                        @if ($inventoryEntry['equipped'])
                                            <span class="text-xs uppercase tracking-[0.08em] text-emerald-300">(ausgerüstet)</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-2 text-sm text-emerald-100/80">Keine Einträge.</p>
                        @endif
                    </article>

                    <article class="rounded-lg border border-amber-700/50 bg-amber-950/10 p-3">
                        <h4 class="text-xs font-semibold uppercase tracking-widest text-amber-200">Waffen</h4>
                        @if (is_array($character->weapons) && count($character->weapons) > 0)
                            <div class="mt-2 overflow-x-auto">
                                <table class="min-w-full border-collapse text-sm text-stone-200">
                                    <thead>
                                        <tr class="text-left text-xs uppercase tracking-[0.08em] text-stone-400">
                                            <th class="border-b border-stone-700/70 px-2 py-1">Waffe</th>
                                            <th class="border-b border-stone-700/70 px-2 py-1">AT %</th>
                                            <th class="border-b border-stone-700/70 px-2 py-1">PA %</th>
                                            <th class="border-b border-stone-700/70 px-2 py-1">SP</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($character->weapons as $weapon)
                                            <tr>
                                                <td class="border-b border-stone-800/60 px-2 py-1">{{ data_get($weapon, 'name', '-') }}</td>
                                                <td class="border-b border-stone-800/60 px-2 py-1">{{ data_get($weapon, 'attack', 0) }}</td>
                                                <td class="border-b border-stone-800/60 px-2 py-1">{{ data_get($weapon, 'parry', 0) }}</td>
                                                <td class="border-b border-stone-800/60 px-2 py-1">{{ data_get($weapon, 'damage', '-') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="mt-2 text-sm text-amber-100/80">Keine Einträge.</p>
                        @endif
                    </article>

                    <article class="rounded-lg border border-sky-700/50 bg-sky-950/10 p-3">
                        <h4 class="text-xs font-semibold uppercase tracking-widest text-sky-200">Rüstung</h4>
                        @if ($armorEntries->isNotEmpty())
                            <p class="mt-2 text-xs uppercase tracking-[0.08em] text-sky-300">
                                Effektiver RS: {{ $totalArmorProtection }}
                                @if ($equippedArmorEntries->isNotEmpty())
                                    (nur ausgerüstet)
                                @else
                                    (alle Einträge)
                                @endif
                            </p>
                            <ul class="mt-2 space-y-1 text-sm text-sky-100/90">
                                @foreach ($armorEntries as $armor)
                                    <li class="break-words">
                                        - {{ data_get($armor, 'name', '-') }} (RS {{ data_get($armor, 'protection', 0) }})
                                        @if ((bool) data_get($armor, 'equipped', false))
                                            <span class="text-xs uppercase tracking-[0.08em] text-sky-300">(ausgerüstet)</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-2 text-sm text-sky-100/80">Keine Einträge.</p>
                        @endif
                    </article>
                </section>

                <section class="rounded-lg border border-stone-700/70 bg-black/30 p-4">
                    <h4 class="text-xs font-semibold uppercase tracking-widest text-stone-300">Inventar-Audit-Log</h4>
                    @if ($inventoryLogs->isNotEmpty())
                        <ul class="mt-3 space-y-2 text-sm text-stone-200">
                            @foreach ($inventoryLogs as $logEntry)
                                @php($actionLabel = $logEntry->action === 'remove' ? 'entfernt' : 'hinzugefügt')
                                <li class="rounded border border-stone-700/70 bg-neutral-900/50 px-3 py-2">
                                    <p class="text-xs uppercase tracking-[0.08em] text-stone-400">
                                        <x-relative-time :at="$logEntry->created_at" />
                                        • {{ $logEntry->actor->name ?? 'System' }}
                                    </p>
                                    <p class="mt-1">
                                        {{ $logEntry->quantity }}x {{ $logEntry->item_name }}
                                        @if ($logEntry->equipped)
                                            <span class="text-xs uppercase tracking-[0.08em] text-emerald-300">(ausgerüstet)</span>
                                        @endif
                                        wurde {{ $actionLabel }}.
                                    </p>
                                    @if ($logEntry->note)
                                        <p class="mt-1 text-xs text-stone-400">Notiz: {{ $logEntry->note }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-2 text-sm text-stone-400">Noch keine Inventar-Änderungen protokolliert.</p>
                    @endif
                </section>

                <section class="rounded-lg border border-emerald-700/60 bg-emerald-950/10 p-4">
                    <h4 class="text-xs font-semibold uppercase tracking-widest text-emerald-200">Progressions-Log</h4>
                    @if ($progressionEvents->isNotEmpty())
                        <ul class="mt-3 space-y-2 text-sm text-emerald-100">
                            @foreach ($progressionEvents as $event)
                                @php
                                    $eventType = (string) $event->event_type;
                                    $eventLabel = match ($eventType) {
                                        'xp_milestone' => 'XP-Meilenstein',
                                        'xp_correction' => 'XP-Korrektur',
                                        'ap_spend' => 'AP-Ausgabe',
                                        'level_up_system' => 'Stufenaufstieg',
                                        default => $eventType,
                                    };
                                    $attributeDeltas = is_array($event->attribute_deltas) ? $event->attribute_deltas : [];
                                @endphp
                                <li class="rounded border border-emerald-700/40 bg-black/20 px-3 py-2">
                                    <p class="text-xs uppercase tracking-[0.08em] text-emerald-300">
                                        <x-relative-time :at="$event->created_at" />
                                        • {{ $eventLabel }}
                                        • {{ $event->actorUser->name ?? 'System' }}
                                    </p>
                                    <p class="mt-1">
                                        XP Δ {{ (int) $event->xp_delta >= 0 ? '+' : '' }}{{ (int) $event->xp_delta }}
                                        • AP Δ {{ (int) $event->ap_delta >= 0 ? '+' : '' }}{{ (int) $event->ap_delta }}
                                        • Stufe {{ (int) $event->level_before }} → {{ (int) $event->level_after }}
                                    </p>
                                    @if ($attributeDeltas !== [])
                                        <p class="mt-1 text-xs text-emerald-200/90">
                                            Attribute:
                                            {{ collect($attributeDeltas)->map(fn ($value, $key): string => strtoupper((string) $key).' +'.(int) $value)->implode(', ') }}
                                        </p>
                                    @endif
                                    @if ($event->campaign || $event->scene || $event->reason)
                                        <p class="mt-1 text-xs text-emerald-200/80">
                                            @if ($event->campaign)
                                                Kampagne: {{ $event->campaign->title }}
                                            @endif
                                            @if ($event->scene)
                                                • Szene: {{ $event->scene->title }}
                                            @endif
                                            @if ($event->reason)
                                                • Grund: {{ $event->reason }}
                                            @endif
                                        </p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-2 text-sm text-emerald-200/80">Noch keine Progressions-Einträge vorhanden.</p>
                    @endif
                </section>
            </article>
        </div>

        <a
            href="{{ route('characters.index') }}"
            class="ui-btn inline-flex"
        >
            Zurück zur Übersicht
        </a>
    </section>
@endsection
