@extends('layouts.auth')

@section('title', 'Rangliste | Chroniken der Asche')

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
                                <th class="px-3 py-2 text-xs uppercase tracking-[0.1em] text-stone-500">Rang</th>
                                <th class="px-3 py-2 text-xs uppercase tracking-[0.1em] text-stone-500">Spieler</th>
                                <th class="px-3 py-2 text-xs uppercase tracking-[0.1em] text-stone-500">Rolle</th>
                                <th class="px-3 py-2 text-xs uppercase tracking-[0.1em] text-stone-500">Punkte</th>
                                <th class="px-3 py-2 text-xs uppercase tracking-[0.1em] text-stone-500">Approved Posts</th>
                                <th class="px-3 py-2 text-xs uppercase tracking-[0.1em] text-stone-500">Charaktere</th>
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
    </section>
@endsection
