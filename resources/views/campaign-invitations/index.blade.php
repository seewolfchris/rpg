@extends('layouts.auth')

@section('title', 'Kampagnen-Einladungen | C76-RPG')

@section('content')
    <section class="mx-auto w-full max-w-5xl space-y-6">
        <div class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Kampagnenzugang</p>
                    <h1 class="font-heading text-3xl text-stone-100">Einladungen</h1>
                    <p class="mt-2 text-sm text-stone-300">
                        Offene Einladungen: <span class="font-semibold text-amber-200">{{ $pendingCount }}</span>
                    </p>
                </div>

                <a
                    href="{{ route('worlds.index') }}"
                    class="rounded-md border border-stone-600/80 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                >
                    Zu Welten
                </a>
            </div>
        </div>

        <section class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <form method="GET" action="{{ route('campaign-invitations.index') }}" class="flex flex-wrap items-center gap-3">
                <label for="status" class="text-xs uppercase tracking-[0.08em] text-stone-500">Status</label>
                <select
                    id="status"
                    name="status"
                    class="rounded-md border border-stone-600/80 bg-neutral-900/80 px-3 py-2 text-sm text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                >
                    <option value="pending" @selected($status === 'pending')>Ausstehend</option>
                    <option value="accepted" @selected($status === 'accepted')>Angenommen</option>
                    <option value="declined" @selected($status === 'declined')>Abgelehnt</option>
                    <option value="all" @selected($status === 'all')>Alle</option>
                </select>

                <button
                    type="submit"
                    class="rounded-md border border-amber-500/60 bg-amber-500/15 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/30"
                >
                    Filtern
                </button>
            </form>
        </section>

        <section class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            @if ($invitations->isEmpty())
                @php
                    $isPendingFilter = $status === 'pending';
                @endphp
                <x-empty-state
                    :title="$isPendingFilter ? 'Keine offenen Einladungen' : 'Keine Einladungen im aktuellen Filter'"
                    :description="$isPendingFilter
                        ? 'Wenn dich eine Spielleitung zu einer Kampagne einlädt, erscheint die Einladung hier.'
                        : 'Für den gewählten Status wurden keine Kampagnen-Einladungen gefunden.'"
                >
                    <x-slot name="actions">
                        <a href="{{ route('worlds.index') }}" class="ui-btn ui-btn-accent">Welten ansehen</a>
                        @if (! $isPendingFilter)
                            <a href="{{ route('campaign-invitations.index') }}" class="ui-btn">Offene Einladungen</a>
                        @endif
                    </x-slot>
                </x-empty-state>
            @else
                <div class="space-y-3">
                    @foreach ($invitations as $invitation)
                        <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm text-stone-100">
                                        <span class="font-semibold">{{ $invitation->campaign->title }}</span>
                                        <span class="text-stone-500">• Leitung: {{ $invitation->campaign->owner->name }}</span>
                                    </p>
                                    <p class="mt-2 text-xs uppercase tracking-[0.08em] text-stone-500">
                                        Eingeladen von {{ $invitation->inviter?->name ?? 'System' }}
                                        • Rolle: {{ strtoupper($invitation->role) }}
                                        • <x-relative-time :at="$invitation->created_at" />
                                    </p>
                                </div>

                                <span class="rounded border px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] {{
                                    $invitation->status === 'accepted'
                                        ? 'border-emerald-600/60 bg-emerald-900/20 text-emerald-300'
                                        : ($invitation->status === 'declined'
                                            ? 'border-red-700/60 bg-red-900/20 text-red-300'
                                            : 'border-amber-700/60 bg-amber-900/20 text-amber-300')
                                }}">
                                    {{ match ($invitation->status) {
                                        'accepted' => 'angenommen',
                                        'declined' => 'abgelehnt',
                                        default => 'ausstehend',
                                    } }}
                                </span>
                            </div>

                            <div class="mt-4 flex flex-wrap items-center gap-2">
                                @php($invitationWorld = $invitation->campaign->world)
                                @if ($invitationWorld)
                                    @can('view', $invitation->campaign)
                                        <a
                                            href="{{ route('campaigns.show', ['world' => $invitationWorld, 'campaign' => $invitation->campaign]) }}"
                                            class="rounded-md border border-stone-600/80 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                                        >
                                            Kampagne ansehen
                                        </a>
                                    @endcan

                                    <a
                                        href="{{ route('worlds.show', ['world' => $invitationWorld]) }}"
                                        class="rounded-md border border-stone-600/80 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                                    >
                                        Weltprofil
                                    </a>

                                    <a
                                        href="{{ route('knowledge.rules', ['world' => $invitationWorld]) }}"
                                        class="rounded-md border border-stone-600/80 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                                    >
                                        Regeln/Wissen
                                    </a>
                                @endif

                                @if ($invitation->status === 'pending')
                                    <form method="POST" action="{{ route('campaign-invitations.accept', ['world' => $invitation->campaign->world, 'invitation' => $invitation]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button
                                            type="submit"
                                            class="rounded-md border border-emerald-600/70 bg-emerald-900/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-emerald-200 transition hover:bg-emerald-900/35"
                                        >
                                            Annehmen
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('campaign-invitations.decline', ['world' => $invitation->campaign->world, 'invitation' => $invitation]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button
                                            type="submit"
                                            class="rounded-md border border-red-700/80 bg-red-900/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-red-200 transition hover:bg-red-900/40"
                                        >
                                            Ablehnen
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $invitations->links() }}
                </div>
            @endif
        </section>
    </section>
@endsection
