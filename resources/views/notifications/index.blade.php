@extends('layouts.auth')

@section('title', 'Benachrichtigungen | Chroniken der Asche')

@section('content')
    <section class="mx-auto w-full max-w-5xl space-y-6">
        <div class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">In-App</p>
                    <h1 class="font-heading text-3xl text-stone-100">Benachrichtigungen</h1>
                    <p class="mt-2 text-sm text-stone-300">
                        Ungelesen: <span class="font-semibold text-amber-200">{{ $unreadCount }}</span>
                    </p>
                    <div class="mt-4 rounded-xl border border-stone-700/80 bg-neutral-900/60 px-4 py-3">
                        <p class="text-xs uppercase tracking-[0.08em] text-stone-400">Browser-Push</p>
                        <div class="mt-2 flex flex-wrap items-center gap-3">
                            <p class="text-sm text-stone-300" data-browser-notifications-status>
                                Browser-Benachrichtigungen werden geprüft.
                            </p>
                            <button
                                type="button"
                                data-browser-notifications-enable
                                class="rounded-md border border-amber-500/60 bg-amber-500/15 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/30"
                            >
                                Browser-Permission aktivieren
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <a
                        href="{{ route('scene-subscriptions.index') }}"
                        class="rounded-md border border-stone-600/80 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                    >
                        Abo-Dashboard
                    </a>
                    <a
                        href="{{ route('notifications.preferences') }}"
                        class="rounded-md border border-stone-600/80 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                    >
                        Einstellungen
                    </a>
                    @if ($unreadCount > 0)
                        <form method="POST" action="{{ route('notifications.read-all') }}">
                            @csrf
                            <button
                                type="submit"
                                class="rounded-md border border-amber-500/60 bg-amber-500/15 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/30"
                            >
                                Alle als gelesen markieren
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <section class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-heading text-xl text-stone-100">Szenen-Abos</h2>
                    <p class="mt-1 text-sm text-stone-300">
                        Aktiv: <span class="font-semibold text-emerald-300">{{ $activeSubscriptionCount }}</span>
                        • Stumm: <span class="font-semibold text-red-300">{{ $mutedSubscriptionCount }}</span>
                    </p>
                </div>
            </div>

            @if ($subscriptions->isEmpty())
                <p class="mb-6 text-sm text-stone-400">
                    Du hast aktuell keine Szenen abonniert.
                </p>
            @else
                <div class="mb-8 space-y-3">
                    @foreach ($subscriptions as $subscription)
                        @php($subscribedScene = $subscription->scene)
                        @if ($subscribedScene && $subscribedScene->campaign)
                            <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm text-stone-100">
                                            <span class="font-semibold">{{ $subscribedScene->title }}</span>
                                            <span class="text-stone-500">• {{ $subscribedScene->campaign->title }}</span>
                                        </p>
                                        <p class="mt-1 text-xs uppercase tracking-[0.08em] {{ $subscription->is_muted ? 'text-red-300' : 'text-emerald-300' }}">
                                            {{ $subscription->is_muted ? 'Stumm geschaltet' : 'Aktiv benachrichtigt' }}
                                        </p>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2">
                                        <a
                                            href="{{ route('campaigns.scenes.show', [$subscribedScene->campaign, $subscribedScene]) }}"
                                            class="rounded-md border border-stone-600/80 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                                        >
                                            Szene
                                        </a>

                                        <form method="POST" action="{{ route('campaigns.scenes.subscription.mute', [$subscribedScene->campaign, $subscribedScene]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button
                                                type="submit"
                                                class="rounded-md border border-stone-600/80 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                                            >
                                                {{ $subscription->is_muted ? 'Unmute' : 'Mute' }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('campaigns.scenes.unsubscribe', [$subscribedScene->campaign, $subscribedScene]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="rounded-md border border-red-700/80 bg-red-900/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-red-200 transition hover:bg-red-900/40"
                                            >
                                                Unfollow
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </article>
                        @endif
                    @endforeach
                </div>
            @endif

            <h2 class="font-heading text-xl text-stone-100">Inbox</h2>

            @if ($notifications->isEmpty())
                <p class="text-sm text-stone-400">Keine Benachrichtigungen vorhanden.</p>
            @else
                <div class="mt-4 space-y-3">
                    @foreach ($notifications as $notification)
                        @php($data = $notification->data)
                        @php($isUnread = $notification->read_at === null)
                        <article class="rounded-xl border {{ $isUnread ? 'border-amber-700/60 bg-amber-900/10' : 'border-stone-800 bg-neutral-900/60' }} p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-stone-100">
                                        {{ $data['title'] ?? 'Benachrichtigung' }}
                                    </p>
                                    <p class="mt-1 text-sm text-stone-300">
                                        {{ $data['message'] ?? 'Neue Aktivität.' }}
                                    </p>
                                    <p class="mt-2 text-xs uppercase tracking-[0.08em] text-stone-500">
                                        {{ $notification->created_at->format('d.m.Y H:i') }}
                                        @if ($isUnread)
                                            • Ungelesen
                                        @else
                                            • Gelesen
                                        @endif
                                    </p>
                                </div>

                                <div class="flex items-center gap-2">
                                    <a
                                        href="{{ $data['action_url'] ?? route('notifications.index') }}"
                                        class="rounded-md border border-stone-600/80 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                                    >
                                        Öffnen
                                    </a>

                                    @if ($isUnread)
                                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="rounded-md border border-amber-500/70 bg-amber-500/15 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/30"
                                            >
                                                Als gelesen
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $notifications->links() }}
                </div>
            @endif
        </section>
    </section>
@endsection
