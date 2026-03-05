@extends('layouts.auth')

@section('title', $character->name.' | Charakter')

@section('content')
    @php
        $sheet = (array) config('character_sheet', []);
        $attributeMeta = (array) data_get($sheet, 'attributes', []);
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
        $inventoryLogs = isset($inventoryLogs) ? collect($inventoryLogs) : collect();
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

    <section class="mx-auto w-full max-w-6xl space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
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
                    class="rounded-md border border-stone-600/80 px-4 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                >
                    Bearbeiten
                </a>
                <form method="POST" action="{{ route('characters.destroy', $character) }}" onsubmit="return confirm('Diesen Charakter wirklich loeschen?');">
                    @csrf
                    @method('DELETE')
                    <button
                        type="submit"
                        class="rounded-md border border-red-700/80 bg-red-900/20 px-4 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-red-200 transition hover:bg-red-900/40"
                    >
                        Loeschen
                    </button>
                </form>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[20rem_1fr]">
            <aside class="space-y-4 rounded-xl border border-stone-800 bg-neutral-900/70 p-4">
                <img
                    src="{{ $character->avatarUrl() }}"
                    alt="Portraet von {{ $character->name }}"
                    class="h-72 w-full rounded-lg object-cover"
                >

                <div class="grid gap-2 text-xs uppercase tracking-[0.08em] text-stone-300">
                    <p class="rounded border border-stone-700/80 bg-black/35 px-3 py-2">Herkunft: {{ $originLabel }}</p>
                    <p class="rounded border border-stone-700/80 bg-black/35 px-3 py-2">Spezies: {{ $speciesLabel }}</p>
                    <p class="rounded border border-stone-700/80 bg-black/35 px-3 py-2">Berufung: {{ $callingLabel }}</p>
                </div>

                <div class="grid grid-cols-2 gap-2 text-sm text-stone-200">
                    <div class="rounded border border-red-700/70 bg-red-950/25 px-3 py-2">LE: {{ $character->le_current ?? 0 }} / {{ $character->le_max ?? 0 }}</div>
                    <div class="rounded border border-sky-700/70 bg-sky-950/25 px-3 py-2">AE: {{ $character->ae_current ?? 0 }} / {{ $character->ae_max ?? 0 }}</div>
                </div>
            </aside>

            <article class="space-y-5 rounded-xl border border-stone-800 bg-black/45 p-6">
                <section>
                    <h2 class="font-heading text-2xl text-stone-100">Biografie</h2>
                    <div class="mt-4 whitespace-pre-line leading-relaxed text-stone-300">{{ $character->bio }}</div>
                </section>

                @if ($character->concept || $character->world_connection || $character->gm_secret || $character->gm_note)
                    <section class="space-y-3 rounded-lg border border-stone-700/80 bg-black/30 p-4">
                        <h3 class="font-heading text-xl text-stone-100">Narrative Kerndaten</h3>
                        @if ($character->concept)
                            <p class="text-sm text-stone-200"><span class="font-semibold text-stone-100">Konzept:</span> {{ $character->concept }}</p>
                        @endif
                        @if ($character->world_connection)
                            <p class="text-sm text-stone-200"><span class="font-semibold text-stone-100">Weltbezug:</span> {{ $character->world_connection }}</p>
                        @endif
                        @if ($character->gm_secret)
                            <p class="text-sm text-red-200"><span class="font-semibold text-red-100">Geheimnis (GM):</span> {{ $character->gm_secret }}</p>
                        @endif
                        @if ($character->gm_note)
                            <p class="text-sm text-stone-200"><span class="font-semibold text-stone-100">GM-Notiz:</span> {{ $character->gm_note }}</p>
                        @endif
                    </section>
                @endif

                <section>
                    <h3 class="font-heading text-xl text-stone-100">Grundeigenschaften</h3>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        @foreach ($attributeMeta as $key => $meta)
                            @php
                                $label = (string) ($meta['label'] ?? strtoupper($key));
                                $baseValue = $resolveBaseAttribute($character, $key);
                                $effectiveValue = (int) ($effectiveAttributes[$key] ?? $baseValue);
                                $note = (string) ($character->{$key.'_note'} ?? '');
                            @endphp
                            <article class="rounded-lg border border-stone-700/80 bg-black/30 p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-xs font-semibold uppercase tracking-[0.1em] text-stone-300">{{ $label }}</p>
                                    <p class="text-sm text-stone-100">
                                        {{ $baseValue }} %
                                        @if ($effectiveValue !== $baseValue)
                                            <span class="text-xs text-amber-300">(effektiv {{ $effectiveValue }} %)</span>
                                        @endif
                                    </p>
                                </div>
                                @if ($note !== '')
                                    <p class="mt-2 text-xs leading-relaxed text-stone-400">{{ $note }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>

                <section class="grid gap-3 sm:grid-cols-2">
                    <article class="rounded-lg border border-emerald-700/60 bg-emerald-900/15 p-3">
                        <h4 class="text-xs font-semibold uppercase tracking-[0.1em] text-emerald-200">Vorteile</h4>
                        @if (is_array($character->advantages) && count($character->advantages) > 0)
                            <ul class="mt-2 space-y-1 text-sm text-emerald-100/90">
                                @foreach ($character->advantages as $advantage)
                                    <li>- {{ $advantage }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-2 text-sm text-emerald-100/80">Keine Eintraege.</p>
                        @endif
                    </article>

                    <article class="rounded-lg border border-red-700/60 bg-red-900/15 p-3">
                        <h4 class="text-xs font-semibold uppercase tracking-[0.1em] text-red-200">Nachteile</h4>
                        @if (is_array($character->disadvantages) && count($character->disadvantages) > 0)
                            <ul class="mt-2 space-y-1 text-sm text-red-100/90">
                                @foreach ($character->disadvantages as $disadvantage)
                                    <li>- {{ $disadvantage }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-2 text-sm text-red-100/80">Keine Eintraege.</p>
                        @endif
                    </article>
                </section>

                <section class="grid gap-3 lg:grid-cols-[1.05fr_1fr]">
                    <article class="rounded-lg border border-emerald-700/50 bg-emerald-950/10 p-3">
                        <h4 class="text-xs font-semibold uppercase tracking-[0.1em] text-emerald-200">Inventar</h4>
                        @if ($inventoryEntries->isNotEmpty())
                            <ul class="mt-2 space-y-1 text-sm text-emerald-100/90">
                                @foreach ($inventoryEntries as $inventoryEntry)
                                    <li>
                                        - {{ $inventoryEntry['quantity'] }}x {{ $inventoryEntry['name'] }}
                                        @if ($inventoryEntry['equipped'])
                                            <span class="text-xs uppercase tracking-[0.08em] text-emerald-300">(ausgeruestet)</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-2 text-sm text-emerald-100/80">Keine Eintraege.</p>
                        @endif
                    </article>

                    <article class="rounded-lg border border-amber-700/50 bg-amber-950/10 p-3">
                        <h4 class="text-xs font-semibold uppercase tracking-[0.1em] text-amber-200">Waffen</h4>
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
                            <p class="mt-2 text-sm text-amber-100/80">Keine Eintraege.</p>
                        @endif
                    </article>
                </section>

                <section class="rounded-lg border border-stone-700/70 bg-black/30 p-4">
                    <h4 class="text-xs font-semibold uppercase tracking-[0.1em] text-stone-300">Inventar-Audit-Log</h4>
                    @if ($inventoryLogs->isNotEmpty())
                        <ul class="mt-3 space-y-2 text-sm text-stone-200">
                            @foreach ($inventoryLogs as $logEntry)
                                @php($actionLabel = $logEntry->action === 'remove' ? 'entfernt' : 'hinzugefuegt')
                                <li class="rounded border border-stone-700/70 bg-neutral-900/50 px-3 py-2">
                                    <p class="text-xs uppercase tracking-[0.08em] text-stone-400">
                                        {{ optional($logEntry->created_at)->format('d.m.Y H:i') ?? '-' }}
                                        • {{ $logEntry->actor->name ?? 'System' }}
                                    </p>
                                    <p class="mt-1">
                                        {{ $logEntry->quantity }}x {{ $logEntry->item_name }}
                                        @if ($logEntry->equipped)
                                            <span class="text-xs uppercase tracking-[0.08em] text-emerald-300">(ausgeruestet)</span>
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
                        <p class="mt-2 text-sm text-stone-400">Noch keine Inventar-Aenderungen protokolliert.</p>
                    @endif
                </section>
            </article>
        </div>

        <a
            href="{{ route('characters.index') }}"
            class="inline-flex rounded-md border border-stone-600/80 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
        >
            Zurueck zur Uebersicht
        </a>
    </section>
@endsection
