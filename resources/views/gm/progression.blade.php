@extends('layouts.auth')

@section('title', 'GM Charakterentwicklung | C76-RPG')

@section('content')
    @php
        $eventMode = (string) old('event_mode', 'milestone');
        $selectedSceneId = (int) old('scene_id', 0);
        $oldAwardsByCharacter = collect(old('awards', []))
            ->filter(static fn ($award): bool => is_array($award))
            ->mapWithKeys(static fn (array $award): array => [
                (int) ($award['character_id'] ?? 0) => (int) ($award['xp_delta'] ?? 0),
            ]);
        $defaultMilestone = (int) ($milestoneSuggestions[0] ?? 25);
    @endphp

    <section class="mx-auto w-full max-w-7xl space-y-6">
        <div class="ui-card p-6 sm:p-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">GM Progression</p>
                    <h1 class="font-heading text-3xl text-stone-100">Charakterentwicklung</h1>
                    <p class="mt-2 text-sm text-stone-300">Vergib XP-Meilensteine oder Korrekturen für Kampagnen-Charaktere.</p>
                </div>
                <a href="{{ route('gm.index') }}" class="ui-btn">Zum GM Hub</a>
            </div>
        </div>

        @if ($campaigns->isEmpty())
            <section class="ui-card p-6 text-sm text-stone-300">
                Für deinen Account gibt es in dieser Welt keine Co-GM-Berechtigung für XP-Vergaben.
            </section>
        @else
            <section class="ui-card p-6 sm:p-8">
                <form method="GET" action="{{ route('gm.progression.index', ['world' => $world]) }}" class="flex flex-wrap items-end gap-3">
                    <div>
                        <label for="campaign_id" class="mb-2 block text-xs uppercase tracking-widest text-stone-400">Kampagne</label>
                        <select
                            id="campaign_id"
                            name="campaign_id"
                            class="rounded-md border border-stone-700/80 bg-black/40 px-4 py-2.5 text-sm text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/30"
                        >
                            @foreach ($campaigns as $campaign)
                                <option value="{{ $campaign->id }}" @selected((int) ($selectedCampaign?->id ?? 0) === (int) $campaign->id)>
                                    {{ $campaign->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="ui-btn ui-btn-accent">Laden</button>
                </form>
            </section>

            @if ($selectedCampaign && $characters->isNotEmpty())
                <section class="ui-card p-6 sm:p-8">
                    <form method="POST" action="{{ route('gm.progression.award-xp', ['world' => $world]) }}" class="space-y-6">
                        @csrf
                        <input type="hidden" name="campaign_id" value="{{ $selectedCampaign->id }}">

                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label for="event_mode" class="mb-2 block text-xs uppercase tracking-widest text-stone-400">Modus</label>
                                <select
                                    id="event_mode"
                                    name="event_mode"
                                    class="w-full rounded-md border border-stone-700/80 bg-black/40 px-4 py-2.5 text-sm text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/30"
                                >
                                    <option value="milestone" @selected($eventMode === 'milestone')>Meilenstein</option>
                                    <option value="correction" @selected($eventMode === 'correction')>Korrektur</option>
                                </select>
                                <p class="mt-2 text-xs text-stone-500">
                                    Vorschläge Meilenstein:
                                    {{ collect($milestoneSuggestions)->map(static fn ($xp): string => '+'.(int) $xp)->implode(', ') ?: '-' }}
                                </p>
                                <p class="mt-1 text-xs text-stone-500">
                                    Vorschläge Korrektur:
                                    {{ collect($correctionSuggestions)->map(static fn ($xp): string => (string) (int) $xp)->implode(', ') ?: '-' }}
                                </p>
                            </div>

                            <div>
                                <label for="scene_id" class="mb-2 block text-xs uppercase tracking-widest text-stone-400">Szene (optional)</label>
                                <select
                                    id="scene_id"
                                    name="scene_id"
                                    class="w-full rounded-md border border-stone-700/80 bg-black/40 px-4 py-2.5 text-sm text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/30"
                                >
                                    <option value="">Keine Szene</option>
                                    @foreach ($scenes as $scene)
                                        <option value="{{ $scene->id }}" @selected($selectedSceneId === (int) $scene->id)>
                                            {{ $scene->title }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="reason" class="mb-2 block text-xs uppercase tracking-widest text-stone-400">Grund (optional)</label>
                                <input
                                    id="reason"
                                    name="reason"
                                    type="text"
                                    maxlength="500"
                                    value="{{ old('reason', '') }}"
                                    placeholder="z. B. Kapitelabschluss: Die Ruinen von Erest"
                                    class="w-full rounded-md border border-stone-700/80 bg-black/40 px-4 py-2.5 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/30"
                                >
                            </div>
                        </div>

                        <div class="overflow-x-auto rounded-lg border border-stone-700/80">
                            <table class="min-w-full border-collapse">
                                <thead class="bg-stone-900/60">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs uppercase tracking-widest text-stone-400">Charakter</th>
                                        <th class="px-4 py-3 text-left text-xs uppercase tracking-widest text-stone-400">Spieler</th>
                                        <th class="px-4 py-3 text-left text-xs uppercase tracking-widest text-stone-400">XP-Delta</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($characters as $index => $character)
                                        @php
                                            $oldDelta = (int) ($oldAwardsByCharacter[(int) $character->id] ?? 0);
                                            $inputValue = $oldDelta !== 0
                                                ? $oldDelta
                                                : ($eventMode === 'milestone' ? $defaultMilestone : '');
                                        @endphp
                                        <tr class="border-t border-stone-800/70">
                                            <td class="px-4 py-3 text-sm text-stone-100">
                                                {{ $character->name }}
                                                <input type="hidden" name="awards[{{ $index }}][character_id]" value="{{ $character->id }}">
                                            </td>
                                            <td class="px-4 py-3 text-xs uppercase tracking-[0.08em] text-stone-400">
                                                {{ $character->user->name ?? '-' }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <input
                                                    type="number"
                                                    step="1"
                                                    name="awards[{{ $index }}][xp_delta]"
                                                    value="{{ $inputValue }}"
                                                    class="w-36 rounded-md border border-stone-600/80 bg-black/45 px-3 py-2 text-sm text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/30"
                                                    placeholder="{{ $eventMode === 'milestone' ? 'z. B. 40' : 'z. B. -40' }}"
                                                >
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <p class="text-xs text-stone-500">
                            Leere oder 0-Werte werden ignoriert. Mindestens ein Charakter mit ungleich 0 XP-Delta ist erforderlich.
                        </p>

                        <button type="submit" class="ui-btn ui-btn-accent">XP speichern</button>
                    </form>
                </section>
            @elseif ($selectedCampaign)
                <section class="ui-card p-6 text-sm text-stone-300">
                    Für diese Kampagne wurden keine teilnehmenden Charaktere gefunden.
                </section>
            @endif
        @endif
    </section>
@endsection
