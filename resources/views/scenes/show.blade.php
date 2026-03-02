@extends('layouts.auth')

@section('title', $scene->title.' | Szene')

@section('content')
    <section class="mx-auto w-full max-w-6xl space-y-6">
        <div class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <a href="{{ route('campaigns.show', $campaign) }}" class="text-xs uppercase tracking-[0.1em] text-amber-300 hover:text-amber-200">
                Zur Kampagne: {{ $campaign->title }}
            </a>

            <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Szene</p>
                    <h1 class="font-heading text-3xl text-stone-100">{{ $scene->title }}</h1>
                    @if ($scene->summary)
                        <p class="mt-3 text-stone-300">{{ $scene->summary }}</p>
                    @endif
                    <p class="mt-3 text-xs uppercase tracking-[0.09em] text-stone-500">
                        Erstellt von {{ $scene->creator->name }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded border border-stone-600/80 bg-black/40 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-stone-300">
                        {{ $scene->status }}
                    </span>
                    @if ($scene->allow_ooc)
                        <span class="rounded border border-emerald-600/60 bg-emerald-900/20 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-emerald-300">OOC ON</span>
                    @else
                        <span class="rounded border border-red-700/60 bg-red-900/20 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-red-300">OOC OFF</span>
                    @endif
                    <span class="rounded border border-stone-600/80 bg-black/40 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-stone-300">
                        Follower: {{ $scene->subscriptions_count }}
                    </span>
                    @if ($subscription)
                        <span class="rounded border {{ $subscription->is_muted ? 'border-red-700/60 bg-red-900/20 text-red-300' : 'border-emerald-600/60 bg-emerald-900/20 text-emerald-300' }} px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em]">
                            {{ $subscription->is_muted ? 'Abo stumm' : 'Abo aktiv' }}
                        </span>
                        @if ($latestPostId > 0)
                            <span class="rounded border border-stone-600/80 bg-black/40 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-stone-300">
                                {{ $hasUnreadPosts ? 'Neu im Thread' : 'Thread gelesen' }}
                            </span>
                        @endif
                    @else
                        <span class="rounded border border-stone-600/80 bg-black/40 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-stone-300">
                            Nicht abonniert
                        </span>
                    @endif
                </div>
            </div>

            @if ($scene->description)
                <article class="mt-6 rounded-xl border border-stone-800 bg-neutral-900/50 p-5">
                    <h2 class="font-heading text-xl text-stone-100">Szenenbeschreibung</h2>
                    <div class="mt-3 whitespace-pre-line leading-relaxed text-stone-300">{{ $scene->description }}</div>
                </article>
            @endif

            @if ($pinnedPosts->isNotEmpty())
                <section class="mt-6 rounded-xl border border-amber-700/40 bg-amber-900/10 p-5">
                    <h2 class="font-heading text-lg text-amber-100">Wichtige Pins</h2>
                    <ul class="mt-3 space-y-2">
                        @foreach ($pinnedPosts as $pinnedPost)
                            <li class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-amber-800/40 bg-black/30 px-3 py-2">
                                <p class="text-xs uppercase tracking-[0.08em] text-amber-200">
                                    #{{ $pinnedPost->id }} • {{ $pinnedPost->user->name }}
                                    @if ($pinnedPost->character)
                                        • {{ $pinnedPost->character->name }}
                                    @endif
                                    @if ($pinnedPost->pinned_at)
                                        • gepinnt {{ $pinnedPost->pinned_at->format('d.m.Y H:i') }}
                                    @endif
                                </p>
                                @if (! empty($pinnedPostJumpUrls[$pinnedPost->id]))
                                    <a
                                        href="{{ $pinnedPostJumpUrls[$pinnedPost->id] }}"
                                        class="rounded-md border border-amber-500/70 bg-amber-500/20 px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-[0.08em] text-amber-100 transition hover:bg-amber-500/30"
                                    >
                                        Zum Pin
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <div class="mt-6 flex flex-wrap items-center gap-3">
                @if ($jumpToLatestPostUrl)
                    <a
                        href="{{ $jumpToLatestPostUrl }}"
                        class="rounded-md border border-stone-600/80 px-4 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                    >
                        Zum neuesten Post
                    </a>
                @endif
                @if ($jumpToLastReadUrl)
                    <a
                        href="{{ $jumpToLastReadUrl }}"
                        class="rounded-md border border-stone-600/80 px-4 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                    >
                        Zum letzten Read
                    </a>
                @endif
                @if ($jumpToFirstUnreadUrl)
                    <a
                        href="{{ $jumpToFirstUnreadUrl }}"
                        class="rounded-md border border-amber-500/70 bg-amber-500/20 px-4 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-amber-100 transition hover:bg-amber-500/30"
                    >
                        Zum ersten neuen
                    </a>
                @endif
                @if ($bookmarkJumpUrl)
                    <a
                        href="{{ $bookmarkJumpUrl }}"
                        class="rounded-md border border-emerald-600/70 bg-emerald-900/20 px-4 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-emerald-200 transition hover:bg-emerald-900/35"
                    >
                        Zum Bookmark
                    </a>
                @endif
                @can('create', [App\Models\Post::class, $scene])
                    <a
                        href="#new-post-form"
                        class="rounded-md border border-stone-600/80 px-4 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                    >
                        Zum Schreibfeld
                    </a>
                @endcan

                @if ($subscription)
                    <form method="POST" action="{{ route('campaigns.scenes.subscription.mute', [$campaign, $scene]) }}">
                        @csrf
                        @method('PATCH')
                        <button
                            type="submit"
                            class="rounded-md border border-stone-600/80 px-4 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                        >
                            {{ $subscription->is_muted ? 'Stumm aus' : 'Stumm schalten' }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('campaigns.scenes.unsubscribe', [$campaign, $scene]) }}">
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="rounded-md border border-stone-600/80 px-4 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                        >
                            Entfolgen
                        </button>
                    </form>

                    <form method="POST" action="{{ route('campaigns.scenes.subscription.unread', [$campaign, $scene]) }}">
                        @csrf
                        @method('PATCH')
                        <button
                            type="submit"
                            class="rounded-md border border-stone-600/80 px-4 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                        >
                            Als ungelesen
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('campaigns.scenes.subscribe', [$campaign, $scene]) }}">
                        @csrf
                        <button
                            type="submit"
                            class="rounded-md border border-amber-500/60 bg-amber-500/15 px-4 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-amber-100 transition hover:bg-amber-500/30"
                        >
                            Folgen
                        </button>
                    </form>
                @endif

                <form method="POST" action="{{ route('campaigns.scenes.bookmark.store', [$campaign, $scene]) }}" class="flex items-center gap-2">
                    @csrf
                    <input type="hidden" name="post_id" value="{{ $latestPostId > 0 ? $latestPostId : '' }}">
                    <input
                        type="text"
                        name="label"
                        maxlength="80"
                        value="{{ old('label', $userBookmark?->label) }}"
                        placeholder="Bookmark-Label (optional)"
                        class="w-48 rounded-md border border-stone-600/80 bg-neutral-900/80 px-3 py-2 text-xs text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    >
                    <button
                        type="submit"
                        class="rounded-md border border-emerald-600/70 bg-emerald-900/20 px-4 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-emerald-200 transition hover:bg-emerald-900/35"
                    >
                        {{ $userBookmark ? 'Bookmark aktualisieren' : 'Bookmark setzen' }}
                    </button>
                </form>

                @if ($userBookmark)
                    <form method="POST" action="{{ route('campaigns.scenes.bookmark.destroy', [$campaign, $scene]) }}">
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="rounded-md border border-red-700/80 bg-red-900/20 px-4 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-red-200 transition hover:bg-red-900/40"
                        >
                            Bookmark loeschen
                        </button>
                    </form>
                @endif

                @can('update', $scene)
                    <a
                        href="{{ route('campaigns.scenes.edit', [$campaign, $scene]) }}"
                        class="rounded-md border border-stone-600/80 px-4 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                    >
                        Szene bearbeiten
                    </a>
                @endcan

                @can('delete', $scene)
                    <form method="POST" action="{{ route('campaigns.scenes.destroy', [$campaign, $scene]) }}" onsubmit="return confirm('Szene wirklich loeschen?');">
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="rounded-md border border-red-700/80 bg-red-900/20 px-4 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-red-200 transition hover:bg-red-900/40"
                        >
                            Szene loeschen
                        </button>
                    </form>
                @endcan
            </div>

            @if ($newPostsSinceLastRead > 0)
                <p class="mt-4 rounded-md border border-amber-600/50 bg-amber-900/20 px-3 py-2 text-xs uppercase tracking-[0.08em] text-amber-200">
                    {{ $newPostsSinceLastRead }} neue Beitraege wurden beim Oeffnen als gelesen markiert.
                </p>
            @elseif ($subscription && $subscription->last_read_at)
                <p class="mt-4 text-xs uppercase tracking-[0.08em] text-stone-500">
                    Letzter Read-Checkpoint: {{ $subscription->last_read_at->format('d.m.Y H:i') }}
                </p>
            @endif
            @error('post_id')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
            @error('label')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <section class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <h2 class="font-heading text-2xl text-stone-100">Thread</h2>

            @if ($posts->isEmpty())
                <p class="mt-4 text-sm text-stone-400">Noch keine Beitraege in dieser Szene.</p>
            @else
                <div class="mt-5 space-y-4">
                    @foreach ($posts as $post)
                        <article id="post-{{ $post->id }}" class="rounded-xl border border-stone-800 bg-neutral-900/60 p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm text-stone-200">
                                        <span class="font-semibold">{{ $post->user->name }}</span>
                                        <span class="text-stone-500">• {{ $post->created_at->format('d.m.Y H:i') }}</span>
                                    </p>

                                    @if ($post->character)
                                        <p class="mt-1 text-xs uppercase tracking-[0.08em] text-amber-300">
                                            Charakter: {{ $post->character->name }}
                                        </p>
                                    @endif

                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        <span class="rounded border border-stone-600/80 bg-black/40 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-stone-300">
                                            {{ strtoupper($post->post_type) }}
                                        </span>
                                        <span class="rounded border border-stone-600/80 bg-black/40 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-stone-300">
                                            {{ $post->moderation_status }}
                                        </span>
                                        <span class="rounded border border-stone-600/80 bg-black/40 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-stone-300">
                                            {{ $post->content_format }}
                                        </span>
                                        @if ($post->is_edited)
                                            <span class="rounded border border-stone-600/80 bg-black/40 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-stone-300">Edited</span>
                                        @endif
                                        @if ($post->is_pinned)
                                            <span class="rounded border border-amber-600/70 bg-amber-900/20 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-amber-300">Pinned</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <form method="POST" action="{{ route('campaigns.scenes.bookmark.store', [$campaign, $scene]) }}">
                                        @csrf
                                        <input type="hidden" name="post_id" value="{{ $post->id }}">
                                        <button
                                            type="submit"
                                            class="rounded-md border border-emerald-600/70 bg-emerald-900/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.1em] text-emerald-200 transition hover:bg-emerald-900/35"
                                        >
                                            Bookmark
                                        </button>
                                    </form>

                                    @can('moderate', $post)
                                        @if ($post->is_pinned)
                                            <form method="POST" action="{{ route('posts.unpin', $post) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button
                                                    type="submit"
                                                    class="rounded-md border border-amber-600/70 bg-amber-900/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.1em] text-amber-200 transition hover:bg-amber-900/35"
                                                >
                                                    Unpin
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('posts.pin', $post) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button
                                                    type="submit"
                                                    class="rounded-md border border-amber-600/70 bg-amber-900/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.1em] text-amber-200 transition hover:bg-amber-900/35"
                                                >
                                                    Pin
                                                </button>
                                            </form>
                                        @endif
                                    @endcan

                                    @can('update', $post)
                                        <a
                                            href="{{ route('posts.edit', $post) }}"
                                            class="rounded-md border border-stone-600/80 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.1em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                                        >
                                            Bearbeiten
                                        </a>
                                    @endcan

                                    @can('delete', $post)
                                        <form method="POST" action="{{ route('posts.destroy', $post) }}" onsubmit="return confirm('Beitrag wirklich loeschen?');">
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="rounded-md border border-red-700/80 bg-red-900/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.1em] text-red-200 transition hover:bg-red-900/40"
                                            >
                                                Loeschen
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </div>

                            <div class="mt-4 leading-relaxed text-stone-200 [&_a]:text-amber-300 [&_a]:underline [&_blockquote]:border-l [&_blockquote]:border-stone-700 [&_blockquote]:pl-4 [&_code]:rounded [&_code]:bg-black/50 [&_code]:px-1 [&_pre]:overflow-x-auto [&_pre]:rounded [&_pre]:border [&_pre]:border-stone-800 [&_pre]:bg-black/50 [&_pre]:p-3">
                                {!! $post->renderedContent() !!}
                            </div>

                            @if ($post->revisions->isNotEmpty())
                                <details class="mt-4 rounded-lg border border-stone-800/80 bg-black/30 p-3">
                                    <summary class="cursor-pointer text-xs uppercase tracking-[0.08em] text-stone-400">
                                        Bearbeitungsverlauf ({{ $post->revisions->count() }})
                                    </summary>

                                    <ol class="mt-3 space-y-3">
                                        @foreach ($post->revisions as $revision)
                                            <li class="rounded-md border border-stone-800/70 bg-neutral-900/50 p-3">
                                                <p class="text-xs uppercase tracking-[0.08em] text-stone-500">
                                                    v{{ $revision->version }}
                                                    • {{ $revision->created_at->format('d.m.Y H:i') }}
                                                    • {{ strtoupper($revision->post_type) }}
                                                    @if ($revision->editor)
                                                        • bearbeitet von {{ $revision->editor->name }}
                                                    @endif
                                                </p>
                                                <div class="mt-2 text-sm leading-relaxed text-stone-300 [&_a]:text-amber-300 [&_a]:underline [&_blockquote]:border-l [&_blockquote]:border-stone-700 [&_blockquote]:pl-4 [&_code]:rounded [&_code]:bg-black/50 [&_code]:px-1 [&_pre]:overflow-x-auto [&_pre]:rounded [&_pre]:border [&_pre]:border-stone-800 [&_pre]:bg-black/50 [&_pre]:p-3">
                                                    {!! $revision->renderedContent() !!}
                                                </div>
                                            </li>
                                        @endforeach
                                    </ol>
                                </details>
                            @endif

                            @if ($post->moderation_status === 'approved' && $post->approvedBy)
                                <p class="mt-3 text-xs uppercase tracking-[0.08em] text-emerald-300">
                                    Freigegeben von {{ $post->approvedBy->name }}
                                </p>
                            @endif

                            @if ($post->is_pinned)
                                <p class="mt-2 text-xs uppercase tracking-[0.08em] text-amber-300">
                                    Angepinnt
                                    @if ($post->pinnedBy)
                                        von {{ $post->pinnedBy->name }}
                                    @endif
                                    @if ($post->pinned_at)
                                        • {{ $post->pinned_at->format('d.m.Y H:i') }}
                                    @endif
                                </p>
                            @endif

                            @if ($post->moderationLogs->isNotEmpty())
                                <details class="mt-4 rounded-lg border border-stone-800/80 bg-black/30 p-3">
                                    <summary class="cursor-pointer text-xs uppercase tracking-[0.08em] text-stone-400">
                                        Moderationsverlauf ({{ $post->moderationLogs->count() }})
                                    </summary>

                                    <ol class="mt-3 space-y-3">
                                        @foreach ($post->moderationLogs as $log)
                                            <li class="rounded-md border border-stone-800/70 bg-neutral-900/50 p-3">
                                                <p class="text-xs uppercase tracking-[0.08em] text-stone-500">
                                                    {{ strtoupper($log->previous_status) }}
                                                    → {{ strtoupper($log->new_status) }}
                                                    • {{ $log->created_at->format('d.m.Y H:i') }}
                                                    @if ($log->moderator)
                                                        • von {{ $log->moderator->name }}
                                                    @endif
                                                </p>
                                                @if ($log->reason)
                                                    <p class="mt-2 text-sm leading-relaxed text-stone-300">
                                                        {{ $log->reason }}
                                                    </p>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ol>
                                </details>
                            @endif

                            @can('moderate', $post)
                                <form method="POST" action="{{ route('posts.moderate', $post) }}" class="mt-4 flex flex-wrap items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <label for="moderation_status_{{ $post->id }}" class="text-xs uppercase tracking-[0.08em] text-stone-400">Moderation</label>
                                    <select
                                        id="moderation_status_{{ $post->id }}"
                                        name="moderation_status"
                                        class="rounded-md border border-stone-600/80 bg-neutral-900/80 px-2 py-1.5 text-xs text-stone-100"
                                    >
                                        <option value="pending" @selected($post->moderation_status === 'pending')>Pending</option>
                                        <option value="approved" @selected($post->moderation_status === 'approved')>Approved</option>
                                        <option value="rejected" @selected($post->moderation_status === 'rejected')>Rejected</option>
                                    </select>
                                    <input
                                        type="text"
                                        name="moderation_note"
                                        maxlength="500"
                                        placeholder="Optionaler Hinweis ..."
                                        class="min-w-56 flex-1 rounded-md border border-stone-600/80 bg-neutral-900/80 px-3 py-1.5 text-xs text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                                    >
                                    <button
                                        type="submit"
                                        class="rounded-md border border-amber-500/60 bg-amber-500/15 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.1em] text-amber-100 transition hover:bg-amber-500/30"
                                    >
                                        Setzen
                                    </button>
                                </form>
                            @endcan
                        </article>
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $posts->links() }}
                </div>
            @endif
        </section>

        <section id="dice-log" class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <h2 class="font-heading text-2xl text-stone-100">Wuerfelaltar</h2>
            <p class="mt-2 text-sm text-stone-300">
                d20-Wuerfe werden transparent im Szenenlog festgehalten.
            </p>

            @can('create', [App\Models\Post::class, $scene])
                <form
                    method="POST"
                    action="{{ route('campaigns.scenes.dice-rolls.store', [$campaign, $scene]) }}"
                    class="mt-6 space-y-4"
                    data-dice-form
                    data-dice-log-target="scene-dice-log-list"
                >
                    @csrf

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label for="dice_character_id" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Charakter</label>
                            <select
                                id="dice_character_id"
                                name="dice_character_id"
                                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-3 py-2.5 text-sm text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                            >
                                <option value="">Ohne Charakter</option>
                                @foreach ($characters as $characterOption)
                                    <option value="{{ $characterOption->id }}" @selected((string) old('dice_character_id') === (string) $characterOption->id)>
                                        {{ $characterOption->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('dice_character_id')
                                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="dice_roll_mode" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Modus</label>
                            <select
                                id="dice_roll_mode"
                                name="dice_roll_mode"
                                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-3 py-2.5 text-sm text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                            >
                                <option value="normal" @selected(old('dice_roll_mode', 'normal') === 'normal')>Normal</option>
                                <option value="advantage" @selected(old('dice_roll_mode') === 'advantage')>Vorteil</option>
                                <option value="disadvantage" @selected(old('dice_roll_mode') === 'disadvantage')>Nachteil</option>
                            </select>
                            @error('dice_roll_mode')
                                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="dice_modifier" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Modifikator</label>
                            <input
                                id="dice_modifier"
                                type="number"
                                name="dice_modifier"
                                value="{{ old('dice_modifier', 0) }}"
                                min="-30"
                                max="30"
                                step="1"
                                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-3 py-2.5 text-sm text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                            >
                            @error('dice_modifier')
                                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="dice_label" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Label</label>
                            <input
                                id="dice_label"
                                type="text"
                                name="dice_label"
                                value="{{ old('dice_label') }}"
                                maxlength="80"
                                placeholder="z. B. Wahrnehmung"
                                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-3 py-2.5 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                            >
                            @error('dice_label')
                                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 pt-1">
                        <button
                            type="submit"
                            data-dice-submit
                            class="rounded-md border border-amber-400/70 bg-amber-500/20 px-5 py-2.5 text-xs font-semibold uppercase tracking-[0.12em] text-amber-100 transition hover:bg-amber-400/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300"
                        >
                            d20 werfen
                        </button>
                        <p class="text-xs text-stone-500">Bei Vorteil/Nachteil werden zwei d20 geworfen.</p>
                    </div>

                    <div data-dice-live class="mt-4 rounded-md border border-stone-700/80 bg-black/35 px-3 py-2 text-sm text-stone-300">
                        Bereit fuer den naechsten Wurf.
                    </div>
                </form>
            @else
                <p class="mt-4 text-sm text-stone-400">In dieser Szene sind aktuell keine neuen Wuerfe moeglich.</p>
            @endcan

            <div class="mt-8">
                <h3 class="font-heading text-lg text-stone-100">Wurfprotokoll</h3>

                @if ($diceRolls->isEmpty())
                    <p data-dice-empty class="mt-3 text-sm text-stone-400">Noch keine Wuerfelwuerfe in dieser Szene.</p>
                @endif

                <ol id="scene-dice-log-list" class="mt-4 space-y-3">
                    @foreach ($diceRolls as $roll)
                        @include('dice-rolls._item', ['roll' => $roll])
                    @endforeach
                </ol>
            </div>
        </section>

        @can('create', [App\Models\Post::class, $scene])
            <section id="new-post-form" class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
                <h2 class="font-heading text-2xl text-stone-100">Neuer Beitrag</h2>
                <p class="mt-2 text-xs text-stone-500">
                    Offline-Modus: Beitraege werden lokal gequeued und bei wiederhergestellter Verbindung automatisch synchronisiert.
                </p>
                <form
                    method="POST"
                    action="{{ route('campaigns.scenes.posts.store', [$campaign, $scene]) }}"
                    class="mt-6"
                    data-offline-post-form
                >
                    @csrf
                    @include('posts._form', [
                        'post' => null,
                        'characters' => $characters,
                        'submitLabel' => 'Beitrag posten',
                        'showModerationControls' => false,
                    ])
                </form>
            </section>
        @else
            <section class="rounded-2xl border border-stone-800 bg-black/45 p-6 text-sm text-stone-300 shadow-xl shadow-black/40 backdrop-blur-sm">
                In dieser Szene sind aktuell keine neuen Beitraege moeglich.
            </section>
        @endcan
    </section>
@endsection
