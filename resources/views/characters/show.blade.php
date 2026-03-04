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
