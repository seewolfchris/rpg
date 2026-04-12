@extends('layouts.auth')

@section('title', $campaign->title.' | Kampagne')

@section('content')
    <section class="mx-auto w-full max-w-6xl space-y-6">
        <div class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Kampagne</p>
                    <h1 class="font-heading break-words text-2xl text-stone-100 sm:text-3xl">{{ $campaign->title }}</h1>
                    @if ($campaign->summary)
                        <p class="mt-3 max-w-3xl text-stone-300">{{ $campaign->summary }}</p>
                    @endif
                    <p class="mt-3 text-xs uppercase tracking-[0.09em] text-stone-500">
                        Leitung: {{ $campaign->owner->name }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded border border-stone-600/80 bg-black/40 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-stone-300">
                        {{ $campaign->status }}
                    </span>

                    @if ($campaign->is_public)
                        <span class="rounded border border-emerald-600/60 bg-emerald-900/20 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-emerald-300">Public</span>
                    @else
                        <span class="rounded border border-amber-600/60 bg-amber-900/20 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-amber-300">Intern</span>
                    @endif
                </div>
            </div>

            @if ($campaign->lore)
                <article class="mt-6 rounded-xl border border-stone-800 bg-neutral-900/50 p-5">
                    <h2 class="font-heading text-xl text-stone-100">Lore</h2>
                    <div class="mt-3 whitespace-pre-line leading-relaxed text-stone-300">{{ $campaign->lore }}</div>
                </article>
            @endif

            <div class="mt-6 flex flex-wrap items-center gap-3">
                @can('update', $campaign)
                    <a
                        href="{{ route('campaigns.edit', ['world' => $campaign->world, 'campaign' => $campaign]) }}"
                        class="rounded-md border border-stone-600/80 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                    >
                        Bearbeiten
                    </a>
                @endcan

                @can('delete', $campaign)
                    <form method="POST" action="{{ route('campaigns.destroy', ['world' => $campaign->world, 'campaign' => $campaign]) }}" data-confirm="Kampagne wirklich löschen?">
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="rounded-md border border-red-700/80 bg-red-900/20 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-red-200 transition hover:bg-red-900/40"
                        >
                            Löschen
                        </button>
                    </form>
                @endcan
            </div>
        </div>

        <div class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Szenen</p>
                    <h2 class="font-heading text-2xl text-stone-100">Thread-Übersicht</h2>
                </div>

                @can('create', [App\Models\Scene::class, $campaign])
                    <a
                        href="{{ route('campaigns.scenes.create', ['world' => $campaign->world, 'campaign' => $campaign]) }}"
                        class="rounded-md border border-amber-400/70 bg-amber-500/20 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-400/30"
                    >
                        Szene anlegen
                    </a>
                @endcan
            </div>

            <form method="GET" action="{{ route('campaigns.show', ['world' => $campaign->world, 'campaign' => $campaign]) }}" class="mt-5 grid gap-3 md:grid-cols-[1fr_auto_auto]">
                <input
                    type="text"
                    name="q"
                    value="{{ $sceneSearch }}"
                    placeholder="Szenen suchen ..."
                    class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                >

                <select
                    name="scene_status"
                    class="rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-sm text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                >
                    <option value="all" @selected($sceneStatus === 'all')>Alle</option>
                    <option value="open" @selected($sceneStatus === 'open')>Open</option>
                    <option value="closed" @selected($sceneStatus === 'closed')>Closed</option>
                    @if ($canManageCampaign)
                        <option value="archived" @selected($sceneStatus === 'archived')>Archived</option>
                    @endif
                </select>

                <button
                    type="submit"
                    class="rounded-md border border-amber-500/60 bg-amber-500/15 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/30"
                >
                    Filtern
                </button>
            </form>

            @if ($scenes->isEmpty())
                <p class="mt-4 text-sm text-stone-400">Keine Szenen für den gewählten Filter.</p>
            @else
                <div class="mt-5 space-y-3">
                    @foreach ($scenes as $scene)
                        @php($sceneSubscription = $scene->subscriptions->first())
                        @php($sceneHasUnread = $sceneSubscription && $sceneSubscription->hasUnread((int) ($scene->latest_post_id ?? 0)))
                        <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-heading break-words text-lg text-stone-100">{{ $scene->title }}</h3>
                                    @if ($scene->summary)
                                        <p class="mt-1 text-sm text-stone-300">{{ $scene->summary }}</p>
                                    @endif
                                    <p class="mt-2 text-xs uppercase tracking-[0.08em] text-stone-500">
                                        Position {{ $scene->position }} • {{ $scene->posts_count }} Posts
                                    </p>
                                    @if ($sceneSubscription)
                                        <p class="mt-1 text-xs uppercase tracking-[0.08em] {{ $sceneHasUnread ? 'text-amber-300' : 'text-stone-500' }}">
                                            {{ $sceneHasUnread ? 'Neu seit deinem letzten Besuch' : 'Alles gelesen' }}
                                        </p>
                                    @endif
                                </div>

                                <div class="flex items-center gap-2">
                                    <span class="rounded border border-stone-600/80 bg-black/40 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-stone-300">
                                        {{ $scene->status }}
                                    </span>
                                    @if ($sceneHasUnread)
                                        <span class="rounded border border-amber-600/70 bg-amber-900/20 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-amber-300">
                                            Neu
                                        </span>
                                    @endif
                                    @if ($sceneSubscription)
                                        @if ($sceneHasUnread)
                                            <form method="POST" action="{{ route('campaigns.scenes.subscription.read', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button
                                                    type="submit"
                                                    class="rounded-md border border-amber-500/70 bg-amber-500/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/30"
                                                >
                                                    Gelesen
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('campaigns.scenes.subscription.unread', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button
                                                    type="submit"
                                                    class="rounded-md border border-stone-600/80 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                                                >
                                                    Ungelesen
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                    <a
                                        href="{{ route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]) }}"
                                        class="rounded-md border border-stone-600/80 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                                    >
                                        Öffnen
                                    </a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $scenes->links() }}
                </div>
            @endif
        </div>

        @if ($canManageInvitations)
            <section class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
                <h2 class="font-heading text-2xl text-stone-100">Einladungen</h2>
                <p class="mt-2 text-sm text-stone-300">
                    Lade bestehende Benutzer per E-Mail ein und definiere ihre Kampagnenrolle.
                </p>

                <form method="POST" action="{{ route('campaigns.invitations.store', ['world' => $campaign->world, 'campaign' => $campaign]) }}" class="mt-5 grid gap-3 md:grid-cols-[1fr_auto_auto]">
                    @csrf
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        placeholder="spieler@beispiel.de"
                        class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    >
                    <select
                        name="role"
                        class="rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-sm text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    >
                        <option value="player" @selected(old('role') === 'player')>Player</option>
                        @if (auth()->user()->hasRole(\App\Enums\UserRole::ADMIN))
                            <option value="trusted_player" @selected(old('role') === 'trusted_player')>Trusted Player</option>
                        @endif
                        <option value="co_gm" @selected(old('role') === 'co_gm')>Co-GM</option>
                    </select>
                    <button
                        type="submit"
                        class="rounded-md border border-amber-500/60 bg-amber-500/15 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/30"
                    >
                        Einladen
                    </button>
                </form>
                @error('email')
                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                @enderror
                @error('role')
                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                @enderror

                @if ($invitations->isEmpty())
                    <p class="mt-5 text-sm text-stone-400">Noch keine Einladungen vorhanden.</p>
                @else
                    <div class="mt-5 space-y-2">
                        @foreach ($invitations as $invitation)
                            <article class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-stone-800 bg-neutral-900/60 px-4 py-3">
                                <div>
                                    <p class="text-sm text-stone-200">
                                        {{ $invitation->user->name }}
                                        <span class="text-stone-500">• {{ $invitation->user->email }}</span>
                                    </p>
                                    <p class="mt-1 text-xs uppercase tracking-[0.08em] text-stone-500">
                                        @php($roleLabel = match ($invitation->role) {
                                            \App\Models\CampaignInvitation::ROLE_CO_GM => 'CO_GM',
                                            \App\Models\CampaignInvitation::ROLE_TRUSTED_PLAYER => 'TRUSTED_PLAYER',
                                            default => 'PLAYER',
                                        })
                                        Status: {{ strtoupper($invitation->status) }}
                                        • Rolle: {{ $roleLabel }}
                                        • von {{ $invitation->inviter?->name ?? 'System' }}
                                        • <x-relative-time :at="$invitation->created_at" />
                                    </p>
                                </div>

                                <form method="POST" action="{{ route('campaigns.invitations.destroy', ['world' => $campaign->world, 'campaign' => $campaign, 'invitation' => $invitation]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="rounded-md border border-red-700/80 bg-red-900/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-red-200 transition hover:bg-red-900/40"
                                    >
                                        Entfernen
                                    </button>
                                </form>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif
    </section>
@endsection
