@extends('layouts.auth')

@section('title', 'GM Moderationszentrale | Chroniken der Asche')

@section('content')
    <section class="mx-auto w-full max-w-6xl space-y-6">
        <div class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">GM Moderation</p>
                    <h1 class="font-heading text-3xl text-stone-100">Freigabe-Queue</h1>
                    <p class="mt-2 text-sm text-stone-300">Pruefe Posts, filtere nach Status und setze Moderation mit einem Klick.</p>
                </div>

                <a
                    href="{{ route('gm.index') }}"
                    class="rounded-md border border-stone-600/80 px-4 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                >
                    Zum GM Hub
                </a>
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-4">
                <article class="rounded-lg border border-stone-800 bg-neutral-900/60 p-4">
                    <p class="text-xs uppercase tracking-[0.08em] text-stone-500">Gesamt</p>
                    <p class="mt-2 text-2xl font-semibold text-stone-100">{{ $totalCount }}</p>
                </article>
                <article class="rounded-lg border border-amber-700/40 bg-amber-900/10 p-4">
                    <p class="text-xs uppercase tracking-[0.08em] text-amber-300">Ausstehend</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-200">{{ $pendingCount }}</p>
                </article>
                <article class="rounded-lg border border-emerald-700/40 bg-emerald-900/10 p-4">
                    <p class="text-xs uppercase tracking-[0.08em] text-emerald-300">Freigegeben</p>
                    <p class="mt-2 text-2xl font-semibold text-emerald-200">{{ $approvedCount }}</p>
                </article>
                <article class="rounded-lg border border-red-700/40 bg-red-900/10 p-4">
                    <p class="text-xs uppercase tracking-[0.08em] text-red-300">Abgelehnt</p>
                    <p class="mt-2 text-2xl font-semibold text-red-200">{{ $rejectedCount }}</p>
                </article>
            </div>
        </div>

        <section class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <form method="GET" action="{{ route('gm.moderation.index') }}" class="grid gap-3 md:grid-cols-[1fr_auto_auto]">
                <input
                    type="text"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Suche: Post-ID, Autor, Szene, Kampagne, Inhalt ..."
                    class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                >

                <select
                    name="status"
                    class="rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-sm text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                >
                    <option value="pending" @selected($status === 'pending')>Ausstehend</option>
                    <option value="approved" @selected($status === 'approved')>Freigegeben</option>
                    <option value="rejected" @selected($status === 'rejected')>Abgelehnt</option>
                    <option value="all" @selected($status === 'all')>Alle</option>
                </select>

                <button
                    type="submit"
                    class="rounded-md border border-amber-500/60 bg-amber-500/15 px-4 py-2.5 text-xs font-semibold uppercase tracking-[0.1em] text-amber-100 transition hover:bg-amber-500/30"
                >
                    Filtern
                </button>
            </form>

            <form method="POST" action="{{ route('gm.moderation.bulk-update') }}" class="mt-4 space-y-3 rounded-lg border border-stone-800 bg-black/30 p-4">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $status }}">
                <input type="hidden" name="q" value="{{ $search }}">

                <p class="text-xs uppercase tracking-[0.08em] text-stone-500">
                    Bulk-Aktion auf aktuellen Filter anwenden
                </p>

                <div class="grid gap-3 md:grid-cols-[auto_1fr_auto]">
                    <select
                        name="moderation_status"
                        required
                        class="rounded-md border border-stone-600/80 bg-neutral-900/80 px-3 py-2 text-sm text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    >
                        <option value="approved">Auf Freigegeben setzen</option>
                        <option value="rejected">Auf Abgelehnt setzen</option>
                        <option value="pending">Auf Ausstehend setzen</option>
                    </select>

                    <input
                        type="text"
                        name="moderation_note"
                        maxlength="500"
                        placeholder="Optionaler Hinweis fuer alle betroffenen Posts ..."
                        class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-3 py-2 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    >

                    <button
                        type="submit"
                        class="rounded-md border border-amber-500/60 bg-amber-500/15 px-4 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-amber-100 transition hover:bg-amber-500/30"
                    >
                        Bulk ausfuehren
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            @if ($posts->isEmpty())
                <p class="text-sm text-stone-400">Keine Posts fuer den gewaehlten Filter.</p>
            @else
                <div class="space-y-4">
                    @foreach ($posts as $post)
                        <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm text-stone-100">
                                        <span class="font-semibold">Post #{{ $post->id }}</span>
                                        <span class="text-stone-500">• {{ $post->created_at->format('d.m.Y H:i') }}</span>
                                    </p>
                                    <p class="mt-1 text-sm text-stone-300">
                                        {{ $post->scene->campaign->title }} • {{ $post->scene->title }}
                                    </p>
                                    <p class="mt-1 text-xs uppercase tracking-[0.08em] text-stone-500">
                                        Autor: {{ $post->user->name }}
                                        @if ($post->character)
                                            • Charakter: {{ $post->character->name }}
                                        @endif
                                        • Audit: {{ $post->moderation_logs_count }}
                                    </p>
                                </div>

                                <span class="rounded border px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] {{
                                    $post->moderation_status === 'approved'
                                        ? 'border-emerald-600/60 bg-emerald-900/20 text-emerald-300'
                                        : ($post->moderation_status === 'rejected'
                                            ? 'border-red-700/60 bg-red-900/20 text-red-300'
                                            : 'border-amber-700/60 bg-amber-900/20 text-amber-300')
                                }}">
                                    {{ match ($post->moderation_status) {
                                        'approved' => 'freigegeben',
                                        'rejected' => 'abgelehnt',
                                        default => 'ausstehend',
                                    } }}
                                </span>
                            </div>

                            <div class="mt-4 whitespace-pre-line rounded-lg border border-stone-800/70 bg-black/35 p-4 text-sm leading-relaxed text-stone-300">
                                {{ \Illuminate\Support\Str::limit($post->content, 420) }}
                            </div>

                            @if ($post->latestModerationLog && $post->latestModerationLog->reason)
                                <div class="mt-4 rounded-lg border border-stone-800/70 bg-black/35 p-3">
                                    <p class="text-xs uppercase tracking-[0.08em] text-stone-500">
                                        Letzter Moderationshinweis
                                        @if ($post->latestModerationLog->moderator)
                                            • {{ $post->latestModerationLog->moderator->name }}
                                        @endif
                                        • {{ $post->latestModerationLog->created_at?->format('d.m.Y H:i') }}
                                    </p>
                                    <p class="mt-2 text-sm text-stone-300">
                                        {{ $post->latestModerationLog->reason }}
                                    </p>
                                </div>
                            @endif

                            <div class="mt-4 space-y-3">
                                <a
                                    href="{{ route('campaigns.scenes.show', [$post->scene->campaign, $post->scene]) }}#post-{{ $post->id }}"
                                    class="inline-flex rounded-md border border-stone-600/80 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.1em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                                >
                                    In Szene ansehen
                                </a>

                                <form method="POST" action="{{ route('posts.moderate', $post) }}" class="space-y-3 rounded-lg border border-stone-800/80 bg-black/30 p-3">
                                    @csrf
                                    @method('PATCH')
                                    <label for="moderation_note_{{ $post->id }}" class="block text-xs uppercase tracking-[0.08em] text-stone-500">Moderationshinweis</label>
                                    <textarea
                                        id="moderation_note_{{ $post->id }}"
                                        name="moderation_note"
                                        rows="2"
                                        maxlength="500"
                                        placeholder="Optionaler Grund fuer Autor und Audit-Log ..."
                                        class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-3 py-2 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                                    ></textarea>

                                    <div class="flex flex-wrap items-center gap-2">
                                        <button
                                            type="submit"
                                            name="moderation_status"
                                            value="approved"
                                            @disabled($post->moderation_status === 'approved')
                                            class="rounded-md border border-emerald-600/70 bg-emerald-900/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.1em] text-emerald-200 transition hover:bg-emerald-900/35 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            Freigeben
                                        </button>
                                        <button
                                            type="submit"
                                            name="moderation_status"
                                            value="rejected"
                                            @disabled($post->moderation_status === 'rejected')
                                            class="rounded-md border border-red-700/80 bg-red-900/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.1em] text-red-200 transition hover:bg-red-900/40 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            Ablehnen
                                        </button>
                                        <button
                                            type="submit"
                                            name="moderation_status"
                                            value="pending"
                                            @disabled($post->moderation_status === 'pending')
                                            class="rounded-md border border-amber-700/70 bg-amber-900/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.1em] text-amber-200 transition hover:bg-amber-900/35 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            Ausstehend
                                        </button>
                                    </div>
                                </form>
                            </div>

                            @if ($post->approvedBy)
                                <p class="mt-3 text-xs uppercase tracking-[0.08em] text-emerald-300">
                                    Letzte Freigabe durch {{ $post->approvedBy->name }}
                                    @if ($post->approved_at)
                                        • {{ $post->approved_at->format('d.m.Y H:i') }}
                                    @endif
                                </p>
                            @endif
                        </article>
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $posts->links() }}
                </div>
            @endif
        </section>
    </section>
@endsection
