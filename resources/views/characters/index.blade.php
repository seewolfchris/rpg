@extends('layouts.auth')

@section('title', 'Charaktere | C76-RPG')

@section('content')
    @php
        $sheet = (array) config('character_sheet', []);
        $isGmView = auth()->user()->isGmOrAdmin();
        $attributeMeta = (array) data_get($sheet, 'attributes', []);
        $legacyMap = (array) data_get($sheet, 'legacy_column_map', []);
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
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">{{ $isGmView ? 'Charakterverwaltung (GM-Ansicht)' : 'Deine Charaktere' }}</p>
                <h1 class="font-heading text-3xl text-stone-100">Charaktere</h1>
                <p class="mt-2 text-stone-300">
                    {{ $isGmView
                        ? 'Verwalte Charaktere aller Spieler im gewählten Weltenkontext.'
                        : 'Verwalte Herkunft, Spezies, Berufung und d100-Eigenschaften deiner Figuren in der gewählten Welt.' }}
                </p>
            </div>

            <a
                href="{{ route('characters.create', ['world' => $selectedWorld->slug ?? null]) }}"
                class="rounded-md border border-amber-400/70 bg-amber-500/20 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-amber-100 transition hover:bg-amber-400/30"
            >
                Neuer Charakter
            </a>
        </div>

        <form method="GET" action="{{ route('characters.index') }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-stone-800 bg-neutral-900/45 p-4">
            <div>
                <label for="world" class="mb-2 block text-xs uppercase tracking-widest text-stone-400">Weltfilter</label>
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
            <button type="submit" class="ui-btn inline-flex">Anwenden</button>
            @if ($selectedWorld)
                <span class="rounded-full border border-amber-500/60 px-3 py-2 text-xs uppercase tracking-widest text-amber-200">
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
                    @endphp
                    <article class="overflow-hidden rounded-xl border border-stone-800 bg-neutral-900/65 shadow-lg shadow-black/30">
                        <img
                            src="{{ $character->avatarUrl() }}"
                            alt="Porträt von {{ $character->name }}"
                            class="h-48 w-full object-cover"
                            loading="lazy"
                        >

                        <div class="space-y-3 p-4">
                            <div>
                                <h2 class="font-heading text-xl text-stone-100">{{ $character->name }}</h2>
                                @if ($character->epithet)
                                    <p class="text-sm text-amber-300/90">{{ $character->epithet }}</p>
                                @endif
                                @if ($isGmView && $character->relationLoaded('user') && $character->user)
                                    <p class="mt-1 text-xs uppercase tracking-[0.08em] text-stone-400">Spieler: <span class="text-stone-200">{{ $character->user->name }}</span></p>
                                @endif
                            </div>

                            <div class="space-y-1 text-xs uppercase tracking-[0.08em] text-stone-400">
                                <p>Welt: <span class="text-stone-200">{{ $character->world?->name ?? '-' }}</span></p>
                                <p>Herkunft: <span class="text-stone-200">{{ $originLabel }}</span></p>
                                <p>Spezies: <span class="text-stone-200">{{ $speciesLabel }}</span></p>
                                <p>Berufung: <span class="text-stone-200">{{ $callingLabel }}</span></p>
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

                            <a
                                href="{{ route('characters.show', $character) }}"
                                class="inline-flex rounded-md border border-stone-600/80 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                            >
                                Details
                            </a>
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
