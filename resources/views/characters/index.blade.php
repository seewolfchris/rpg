@extends('layouts.auth')

@section('title', 'Charaktere | C76-RPG')

@section('content')
    @php
        $sheet = (array) config('character_sheet', []);
        $isGmView = auth()->user()->isGmOrAdmin();
        $attributeMeta = (array) data_get($sheet, 'attributes', []);
        $legacyMap = (array) data_get($sheet, 'legacy_column_map', []);
        $characterStatuses = (array) ($characterStatuses ?? config('characters.statuses', []));
        $selectedStatus = (string) ($selectedStatus ?? 'all');
        $indexAttributeKeys = ['mu', 'kl', 'in', 'ch'];
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
        <div class="ui-card p-6 sm:p-8">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">{{ $isGmView ? 'Charakterverwaltung (GM-Ansicht)' : 'Deine Charaktere' }}</p>
                    <h1 class="font-heading break-words text-3xl text-stone-100">Charaktere</h1>
                    <p class="mt-2 break-words text-sm leading-relaxed text-stone-300 sm:text-base">
                        {{ $isGmView
                            ? 'Verwalte Charaktere aller Spieler im gewählten Weltenkontext.'
                            : 'Verwalte Herkunft, Spezies, Berufung und d100-Eigenschaften deiner Figuren in der gewählten Welt.' }}
                    </p>
                </div>

                <a
                    href="{{ route('characters.create', ['world' => $selectedWorld->slug ?? null]) }}"
                    class="ui-btn ui-btn-accent inline-flex"
                >
                    Neuer Charakter
                </a>
            </div>
        </div>

        <form method="GET" action="{{ route('characters.index') }}" class="ui-card-soft flex flex-wrap items-end gap-3 p-4 sm:p-5">
            <div>
                <label for="world" class="mb-2 block text-xs uppercase tracking-[0.1em] text-stone-400">Weltfilter</label>
                <select
                    id="world"
                    name="world"
                    class="rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 text-sm text-stone-100 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/35"
                >
                    @foreach ($worlds as $worldOption)
                        <option value="{{ $worldOption->slug }}" @selected(($selectedWorld->slug ?? '') === $worldOption->slug)>
                            {{ $worldOption->name }}
                        </option>
                    @endforeach
                    </select>
            </div>
            <div>
                <label for="status" class="mb-2 block text-xs uppercase tracking-[0.1em] text-stone-400">Statusfilter</label>
                <select
                    id="status"
                    name="status"
                    class="rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 text-sm text-stone-100 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/35"
                >
                    <option value="all" @selected($selectedStatus === 'all')>Alle</option>
                    @foreach ($characterStatuses as $statusKey => $statusMeta)
                        <option value="{{ $statusKey }}" @selected($selectedStatus === $statusKey)>
                            {{ $statusMeta['label'] ?? ucfirst((string) $statusKey) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="ui-btn inline-flex">Anwenden</button>
            @if ($selectedWorld)
                <span class="rounded-full border border-amber-500/60 px-3 py-2 text-xs uppercase tracking-[0.1em] text-amber-200">
                    Aktiv: {{ $selectedWorld->name }}
                </span>
            @endif
        </form>

        @if ($characters->isEmpty())
            <div class="rounded-xl border border-stone-800 bg-black/45 p-8 text-center text-stone-300">
                Noch keine Charaktere vorhanden.
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($characters as $character)
                    @php
                        $originLabel = (string) data_get($sheet, 'origins.'.$character->origin, $character->origin ?: '-');
                        $speciesLabel = (string) data_get($sheet, 'species.'.$character->species.'.label', $character->species ?: '-');
                        $callingLabel = (string) data_get($sheet, 'callings.'.$character->calling.'.label', $character->calling ?: '-');
                        $statusKey = (string) ($character->status ?: config('characters.default_status', 'active'));
                        $statusMeta = (array) data_get($characterStatuses, $statusKey, data_get($characterStatuses, 'active', []));
                        $statusLabel = (string) ($statusMeta['label'] ?? ucfirst($statusKey));
                        $statusBadgeClass = (string) ($statusMeta['badge_class'] ?? 'border-stone-600/80 bg-stone-900/35 text-stone-200');
                    @endphp
                    <article class="ui-card-soft rounded-xl border border-stone-800/85 bg-neutral-900/70 p-4 shadow-lg shadow-black/25 transition duration-300 hover:-translate-y-0.5 hover:border-amber-600/55">
                        <img
                            src="{{ $character->avatarUrl() }}"
                            alt="Porträt von {{ $character->name }}"
                            class="h-48 w-full rounded-lg object-cover"
                            loading="lazy"
                        >

                        <div class="space-y-3 pt-4">
                            <div class="min-w-0">
                                <h2 class="font-heading break-words text-xl text-stone-100">{{ $character->name }}</h2>
                                @if ($character->epithet)
                                    <p class="break-words text-sm leading-relaxed text-amber-300/90">{{ $character->epithet }}</p>
                                @endif
                                @if ($isGmView && $character->relationLoaded('user') && $character->user)
                                    <p class="mt-1 text-xs uppercase tracking-[0.08em] text-stone-400">Spieler: <span class="text-stone-200">{{ $character->user->name }}</span></p>
                                @endif
                            </div>

                            <div class="space-y-1 text-xs uppercase tracking-[0.08em] text-stone-400">
                                <p class="break-words">Welt: <span class="text-stone-200">{{ $character->world?->name ?? '-' }}</span></p>
                                <p>
                                    Status:
                                    <span class="ml-1 inline-flex rounded border px-1.5 py-0.5 text-[0.62rem] tracking-[0.08em] {{ $statusBadgeClass }}">{{ $statusLabel }}</span>
                                </p>
                                <p class="break-words">Herkunft: <span class="text-stone-200">{{ $originLabel }}</span></p>
                                <p class="break-words">Spezies: <span class="text-stone-200">{{ $speciesLabel }}</span></p>
                                <p class="break-words">Berufung: <span class="text-stone-200">{{ $callingLabel }}</span></p>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-xs text-stone-300">
                                @foreach ($indexAttributeKeys as $attributeKey)
                                    @php($label = (string) data_get($attributeMeta, $attributeKey.'.label', strtoupper($attributeKey)))
                                    <div class="rounded border border-stone-700/80 bg-black/40 px-2 py-1">
                                        {{ $label }} {{ $resolveBaseAttribute($character, $attributeKey) }}%
                                    </div>
                                @endforeach
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-xs text-stone-300">
                                <div class="rounded border border-red-700/70 bg-red-950/25 px-2 py-1">LE {{ $character->le_current ?? 0 }}/{{ $character->le_max ?? 0 }}</div>
                                <div class="rounded border border-sky-700/70 bg-sky-950/25 px-2 py-1">AE {{ $character->ae_current ?? 0 }}/{{ $character->ae_max ?? 0 }}</div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <a
                                    href="{{ route('characters.show', $character) }}"
                                    class="ui-btn inline-flex"
                                >
                                    Details
                                </a>
                                @if ((int) $character->user_id === (int) auth()->id() || $isGmView)
                                    <form method="POST" action="{{ route('characters.destroy', $character) }}" data-confirm="Diesen Charakter wirklich löschen?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ui-btn ui-btn-danger inline-flex">Löschen</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div>
                {{ $characters->links() }}
            </div>
        @endif
    </section>
@endsection
