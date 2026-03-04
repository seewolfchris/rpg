@extends('layouts.auth')

@section('title', $scene->title.' | Szene')

@section('content')
    <section class="mx-auto w-full max-w-6xl space-y-6">
        <div class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <a href="{{ route('campaigns.show', $campaign) }}" class="break-words text-xs uppercase tracking-[0.1em] text-amber-300 hover:text-amber-200">
                Zur Kampagne: {{ $campaign->title }}
            </a>

            <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Szene</p>
                    <h1 class="font-heading break-words text-2xl text-stone-100 sm:text-3xl">{{ $scene->title }}</h1>
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

                <form method="POST" action="{{ route('campaigns.scenes.bookmark.store', [$campaign, $scene]) }}" class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                    @csrf
                    <input type="hidden" name="post_id" value="{{ $latestPostId > 0 ? $latestPostId : '' }}">
                    <input
                        type="text"
                        name="label"
                        maxlength="80"
                        value="{{ old('label', $userBookmark?->label) }}"
                        placeholder="Bookmark-Label (optional)"
                        class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-3 py-2 text-xs text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40 sm:w-48"
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
                @php
                    $pagePosts = $posts->getCollection();
                    $icPosts = $pagePosts->where('post_type', 'ic');
                    $oocPosts = $pagePosts->where('post_type', 'ooc');
                @endphp

                <div class="mt-5 space-y-6">
                    <section class="rounded-xl border border-amber-700/30 bg-black/20 p-4">
                        <h3 class="font-heading text-xl text-amber-100">Abenteuerfluss (IC)</h3>
                        <p class="mt-1 text-xs uppercase tracking-[0.08em] text-amber-300">
                            Fokus auf In-Character-Posts fuer ungestoerten Lesefluss.
                        </p>

                        @if ($icPosts->isEmpty())
                            <p class="mt-4 text-sm text-stone-400">Auf dieser Seite sind keine IC-Beitraege vorhanden.</p>
                        @else
                            <div class="mt-4 space-y-4">
                                @foreach ($icPosts as $post)
                                    @include('posts._thread-item', ['post' => $post, 'campaign' => $campaign, 'scene' => $scene])
                                @endforeach
                            </div>
                        @endif
                    </section>

                    <section class="rounded-xl border border-stone-700/70 bg-neutral-900/35 p-4">
                        <h3 class="font-heading text-xl text-stone-100">OOC-Kanal</h3>
                        <p class="mt-1 text-xs uppercase tracking-[0.08em] text-stone-400">
                            Absprachen und Meta-Kommentare getrennt vom Abenteuerfluss.
                        </p>

                        @if ($oocPosts->isEmpty())
                            <p class="mt-4 text-sm text-stone-400">Auf dieser Seite sind keine OOC-Beitraege vorhanden.</p>
                        @else
                            <div class="mt-4 space-y-4">
                                @foreach ($oocPosts as $post)
                                    @include('posts._thread-item', ['post' => $post, 'campaign' => $campaign, 'scene' => $scene])
                                @endforeach
                            </div>
                        @endif
                    </section>
                </div>

                <div class="mt-6">
                    {{ $posts->links() }}
                </div>
            @endif
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
                        'probeCharacters' => $probeCharacters,
                        'showProbeControls' => $canModerateScene,
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
