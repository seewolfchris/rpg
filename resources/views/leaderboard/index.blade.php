@extends('layouts.auth')

@section('title', 'Rangliste | C76-RPG')

@section('content')
    <section class="mx-auto w-full max-w-5xl space-y-6">
        <div class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Gamification</p>
            <h1 class="font-heading text-3xl text-stone-100">Rangliste der Chronisten</h1>
            <p class="mt-3 text-sm text-stone-300">
                Punkte entstehen durch freigegebene Posts. Dein aktueller Rang: <span class="font-semibold text-amber-200">#{{ $rank }}</span>
            </p>
        </div>

        <section class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            @if ($leaders->isEmpty())
                <p class="text-sm text-stone-400">Noch keine Punkte vergeben.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full border-separate border-spacing-y-2 text-left">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-xs uppercase tracking-widest text-stone-500">Rang</th>
                                <th class="px-3 py-2 text-xs uppercase tracking-widest text-stone-500">Spieler</th>
                                <th class="px-3 py-2 text-xs uppercase tracking-widest text-stone-500">Rolle</th>
                                <th class="px-3 py-2 text-xs uppercase tracking-widest text-stone-500">Punkte</th>
                                <th class="px-3 py-2 text-xs uppercase tracking-widest text-stone-500">Freigegebene Posts</th>
                                <th class="px-3 py-2 text-xs uppercase tracking-widest text-stone-500">Charaktere</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($leaders as $index => $leader)
                                <tr class="rounded-xl border border-stone-800 bg-neutral-900/60">
                                    <td class="px-3 py-3 text-sm font-semibold text-stone-100">#{{ $index + 1 }}</td>
                                    <td class="px-3 py-3 text-sm text-stone-200">
                                        {{ $leader->name }}
                                        @if ($leader->id === auth()->id())
                                            <span class="ml-2 rounded border border-amber-600/70 bg-amber-900/20 px-2 py-0.5 text-[0.65rem] uppercase tracking-[0.08em] text-amber-200">Du</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-xs uppercase tracking-[0.08em] text-stone-400">{{ $leader->role->value }}</td>
                                    <td class="px-3 py-3 text-sm font-semibold text-amber-200">{{ $leader->points }}</td>
                                    <td class="px-3 py-3 text-sm text-stone-300">{{ $leader->approved_posts_count }}</td>
                                    <td class="px-3 py-3 text-sm text-stone-300">{{ $leader->characters_count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @if (config('features.wave4.active_characters_week', false))
            <section class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="mb-2 text-xs uppercase tracking-[0.16em] text-emerald-300/80">Welle 4</p>
                        <h2 class="font-heading text-2xl text-stone-100">Aktive Charaktere diese Woche</h2>
                        <p class="mt-2 text-sm text-stone-300">Sortiert nach IC-Posts in den letzten 7 Tagen.</p>
                    </div>
                </div>

                @if (($activeCharactersThisWeek ?? collect())->isEmpty())
                    <p class="mt-4 text-sm text-stone-400">Noch keine Aktivitaet in den letzten 7 Tagen.</p>
                @else
                    <ol class="mt-4 space-y-2">
                        @foreach ($activeCharactersThisWeek as $index => $activeCharacter)
                            <li class="rounded-xl border border-stone-800 bg-neutral-900/60 px-4 py-3">
                                <p class="text-sm text-stone-100">
                                    <span class="font-semibold text-emerald-200">#{{ $index + 1 }}</span>
                                    <span class="ml-2 font-semibold">{{ $activeCharacter->name }}</span>
                                    <span class="ml-2 text-stone-400">({{ $activeCharacter->weekly_posts_count }} IC-Posts)</span>
                                </p>
                                @if ($activeCharacter->user)
                                    <p class="mt-1 text-xs uppercase tracking-[0.08em] text-stone-500">
                                        Spieler: {{ $activeCharacter->user->name }}
                                    </p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        @endif
    </section>
@endsection
