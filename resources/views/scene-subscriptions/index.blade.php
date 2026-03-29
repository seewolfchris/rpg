@extends('layouts.auth')

@section('title', 'Szenen-Abos | C76-RPG')

@section('content')
    <section class="mx-auto w-full max-w-6xl space-y-6">
        <div class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Dashboard</p>
                    <h1 class="font-heading text-3xl text-stone-100">Szenen-Abos</h1>
                    <p class="mt-3 text-sm text-stone-300">
                        Verwalte alle abonnierten Szenen zentral, inkl. Filter und Sammelaktionen.
                    </p>
                </div>

                <a
                    href="{{ route('notifications.index') }}"
                    class="rounded-md border border-stone-600/80 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                >
                    Zum Posteingang
                </a>
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-4">
                <article class="rounded-lg border border-stone-800 bg-neutral-900/60 p-4">
                    <p class="text-xs uppercase tracking-[0.08em] text-stone-500">Gesamt</p>
                    <p class="mt-2 text-2xl font-semibold text-stone-100">{{ $totalCount }}</p>
                </article>
                <article class="rounded-lg border border-emerald-700/40 bg-emerald-900/10 p-4">
                    <p class="text-xs uppercase tracking-[0.08em] text-emerald-300">Aktiv</p>
                    <p class="mt-2 text-2xl font-semibold text-emerald-200">{{ $activeCount }}</p>
                </article>
                <article class="rounded-lg border border-red-700/40 bg-red-900/10 p-4">
                    <p class="text-xs uppercase tracking-[0.08em] text-red-300">Stumm</p>
                    <p class="mt-2 text-2xl font-semibold text-red-200">{{ $mutedCount }}</p>
                </article>
                <article class="rounded-lg border border-amber-700/40 bg-amber-900/10 p-4">
                    <p class="text-xs uppercase tracking-[0.08em] text-amber-300">Ungelesen</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-200">{{ $unreadCount }}</p>
                </article>
            </div>
        </div>

        <section class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <form method="GET" action="{{ route('scene-subscriptions.index') }}" class="grid gap-3 md:grid-cols-[1fr_auto_auto]">
                <input
                    type="text"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Suche nach Szene oder Kampagne ..."
                    class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                >

                <select
                    name="status"
                    class="rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-sm text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                >
                    <option value="all" @selected($status === 'all')>Alle</option>
                    <option value="active" @selected($status === 'active')>Nur aktiv</option>
                    <option value="muted" @selected($status === 'muted')>Nur stumm</option>
                </select>

                <button
                    type="submit"
                    class="rounded-md border border-amber-500/60 bg-amber-500/15 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/30"
                >
                    Filtern
                </button>
            </form>

            <form method="POST" action="{{ route('scene-subscriptions.bulk-update') }}" class="mt-4 flex flex-wrap items-center gap-3 rounded-lg border border-stone-800 bg-neutral-900/50 p-4">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $status }}">
                <input type="hidden" name="q" value="{{ $search }}">

                <label for="bulk_action" class="text-xs uppercase tracking-[0.08em] text-stone-400">Sammelaktion</label>
                <select
                    id="bulk_action"
                    name="bulk_action"
                    required
                    class="rounded-md border border-stone-600/80 bg-neutral-900/80 px-3 py-2 text-sm text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                >
                    <option value="mute_filtered">Gefilterte stummschalten</option>
                    <option value="unmute_filtered">Gefilterte aktivieren</option>
                    <option value="unfollow_filtered">Gefilterte entfolgen</option>
                    <option value="mute_all_active">Alle aktiven stummschalten</option>
                    <option value="unmute_all_muted">Alle stummen aktivieren</option>
                    <option value="unfollow_all_muted">Alle stummen entfolgen</option>
                </select>

                <button
                    type="submit"
                    class="rounded-md border border-stone-600/80 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                >
                    Anwenden
                </button>
            </form>
        </section>

        <section class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            @if ($subscriptions->isEmpty())
                <p class="text-sm text-stone-400">Keine Abos für den gewählten Filter.</p>
            @else
                <div class="space-y-3">
                    @foreach ($subscriptions as $subscription)
                        @php($subscribedScene = $subscription->scene)
                        @if ($subscribedScene && $subscribedScene->campaign)
                            @php($hasUnread = $subscription->hasUnread((int) ($subscribedScene->latest_post_id ?? 0)))
                            <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm text-stone-100">
                                            <span class="font-semibold">{{ $subscribedScene->title }}</span>
                                            <span class="text-stone-500">• {{ $subscribedScene->campaign->title }}</span>
                                        </p>
                                        <p class="mt-2 text-xs uppercase tracking-[0.08em] {{ $subscription->is_muted ? 'text-red-300' : 'text-emerald-300' }}">
                                            {{ $subscription->is_muted ? 'Stumm geschaltet' : 'Aktiv benachrichtigt' }}
                                        </p>
                                        <p class="mt-1 text-xs uppercase tracking-[0.08em] {{ $hasUnread ? 'text-amber-300' : 'text-stone-500' }}">
                                            {{ $hasUnread ? 'Neue Beiträge vorhanden' : 'Alles gelesen' }}
                                        </p>
                                        <p class="mt-1 text-xs uppercase tracking-[0.08em] text-stone-500">
                                            Posts: {{ $subscribedScene->posts_count }}
                                            @if ($subscription->last_read_at)
                                                • Letzter Lesepunkt: <x-relative-time :at="$subscription->last_read_at" />
                                            @endif
                                        </p>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2">
                                        <a
                                            href="{{ route('campaigns.scenes.show', ['world' => $subscribedScene->campaign->world, 'campaign' => $subscribedScene->campaign, 'scene' => $subscribedScene]) }}"
                                            class="rounded-md border border-stone-600/80 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                                        >
                                            Szene
                                        </a>

                                        <form method="POST" action="{{ route('campaigns.scenes.subscription.mute', ['world' => $subscribedScene->campaign->world, 'campaign' => $subscribedScene->campaign, 'scene' => $subscribedScene]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button
                                                type="submit"
                                                class="rounded-md border border-stone-600/80 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                                            >
                                                {{ $subscription->is_muted ? 'Stumm aufheben' : 'Stumm schalten' }}
                                            </button>
                                        </form>

                                        @if ($hasUnread)
                                            <form method="POST" action="{{ route('campaigns.scenes.subscription.read', ['world' => $subscribedScene->campaign->world, 'campaign' => $subscribedScene->campaign, 'scene' => $subscribedScene]) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button
                                                    type="submit"
                                                    class="rounded-md border border-amber-500/70 bg-amber-500/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/30"
                                                >
                                                    Als gelesen
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('campaigns.scenes.subscription.unread', ['world' => $subscribedScene->campaign->world, 'campaign' => $subscribedScene->campaign, 'scene' => $subscribedScene]) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button
                                                    type="submit"
                                                    class="rounded-md border border-stone-600/80 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                                                >
                                                    Als ungelesen
                                                </button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('campaigns.scenes.unsubscribe', ['world' => $subscribedScene->campaign->world, 'campaign' => $subscribedScene->campaign, 'scene' => $subscribedScene]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="rounded-md border border-red-700/80 bg-red-900/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-red-200 transition hover:bg-red-900/40"
                                            >
                                                Entfolgen
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </article>
                        @endif
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $subscriptions->links() }}
                </div>
            @endif
        </section>
    </section>
@endsection
