@php
    $pagePosts = $posts->getCollection();
    $icPosts = $pagePosts->where('post_type', 'ic');
    $oocPosts = $pagePosts->where('post_type', 'ooc');
    $subscription = $subscription ?? null;
    $latestPostId = (int) ($latestPostId ?? 0);
    $unreadPostsCount = max(0, (int) ($unreadPostsCount ?? 0));
    $canModerateScene = (bool) ($canModerateScene ?? false);
    $sceneMoodConfig = (array) config('scenes.moods', []);
    $sceneMoodKey = (string) ($scene->mood ?: config('scenes.default_mood', 'neutral'));
    $sceneMoodMeta = (array) data_get($sceneMoodConfig, $sceneMoodKey, data_get($sceneMoodConfig, 'neutral', []));
    $sceneMoodLabel = (string) ($sceneMoodMeta['label'] ?? ucfirst($sceneMoodKey));
    $sceneMoodHint = match ($sceneMoodKey) {
        'dark' => 'Schwere Bilder, harte Entscheidungen und wenig Gewissheit.',
        'cheerful' => 'Wärmere Töne, schnelleres Tempo, mehr Hoffnung in den Antworten.',
        'mystic' => 'Andeutungen, Symbole und Rätsel dürfen den Ton tragen.',
        'tense' => 'Kurze Atemzüge, klare Reaktionen, jeder Satz kann kippen.',
        default => 'Der Ton bleibt offen: balanciere zwischen Ruhe und Bewegung.',
    };
@endphp

@if ($posts->currentPage() === 1)
    <section class="thread-mood-indicator thread-mood-{{ $sceneMoodKey }} ui-card-soft border-stone-700/70 p-4 sm:p-5">
        <p class="text-[0.65rem] uppercase tracking-[0.12em] text-amber-200/85">Stimmungsindikator</p>
        <p class="mt-1 font-heading text-lg text-stone-100">{{ $sceneMoodLabel }}</p>
        <p class="mt-1 text-sm leading-relaxed text-stone-300">{{ $sceneMoodHint }}</p>
    </section>
@endif

@if ($posts->currentPage() === 1 && auth()->check())
    <section id="scene-thread-live-controls" class="ui-card-soft border-amber-700/35 bg-black/25 p-4 sm:p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs uppercase tracking-[0.08em] text-stone-300">
                Lesefortschritt
                <span id="scene-thread-unread-count" class="ml-2 rounded border border-amber-700/60 bg-amber-900/20 px-2 py-0.5 text-[0.65rem] text-amber-200">
                    Ungelesen: {{ $unreadPostsCount }}
                </span>
            </p>
            <div class="flex flex-wrap items-center gap-2">
                @if ($latestPostId > 0)
                    <a
                        href="{{ route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene, 'jump' => 'latest']) }}"
                        class="ui-btn !px-3 !py-1.5 !text-[0.68rem]"
                    >
                        Neuester Post
                    </a>
                @endif
                @if ($subscription && (int) ($subscription->last_read_post_id ?? 0) > 0)
                    <a
                        href="{{ route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene, 'jump' => 'last_read']) }}"
                        class="ui-btn !px-3 !py-1.5 !text-[0.68rem]"
                    >
                        Letzter Lesepunkt
                    </a>
                @endif
            </div>
        </div>

        @if ($unreadPostsCount > 0)
            <a
                href="{{ route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene, 'jump' => 'first_unread']) }}"
                class="mt-3 inline-flex w-full items-center justify-center rounded-xl border border-amber-500/70 bg-amber-500/20 px-4 py-2.5 text-xs font-semibold uppercase tracking-[0.12em] text-amber-50 shadow-lg shadow-amber-950/30 transition hover:bg-amber-500/35 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400/60"
            >
                Nächster ungelesener Post
            </a>
        @else
            <p class="mt-3 text-xs uppercase tracking-[0.09em] text-emerald-300">Du bist auf dem aktuellen Stand dieser Szene.</p>
        @endif

        @if ($subscription)
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <form
                    method="POST"
                    action="{{ route('campaigns.scenes.subscription.read', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]) }}"
                    hx-post="{{ route('campaigns.scenes.subscription.read', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]) }}"
                    hx-target="#scene-thread-feed"
                    hx-swap="innerHTML"
                    hx-indicator="#global-hx-indicator"
                >
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="ui-btn !px-3 !py-1.5 !text-[0.68rem]">
                        Als gelesen
                    </button>
                </form>

                <form
                    method="POST"
                    action="{{ route('campaigns.scenes.subscription.unread', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]) }}"
                    hx-post="{{ route('campaigns.scenes.subscription.unread', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]) }}"
                    hx-target="#scene-thread-feed"
                    hx-swap="innerHTML"
                    hx-indicator="#global-hx-indicator"
                >
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="ui-btn !px-3 !py-1.5 !text-[0.68rem]">
                        Als ungelesen
                    </button>
                </form>
            </div>
        @endif

        @if ($canModerateScene)
            <form
                id="thread-moderation-bulk-form"
                method="POST"
                action="{{ route('gm.moderation.bulk-update', ['world' => $campaign->world]) }}"
                class="mt-4 grid gap-2 md:grid-cols-[auto_1fr_auto]"
                hx-post="{{ route('gm.moderation.bulk-update', ['world' => $campaign->world]) }}"
                hx-target="#scene-thread-feed"
                hx-swap="innerHTML"
                hx-indicator="#global-hx-indicator"
            >
                @csrf
                @method('PATCH')
                <input type="hidden" name="scene_id" value="{{ $scene->id }}">
                <input type="hidden" name="status" value="all">
                <input type="hidden" name="q" value="">

                <select
                    name="moderation_status"
                    required
                    class="rounded-md border border-stone-600/80 bg-neutral-900/80 px-2 py-1.5 text-xs text-stone-100"
                >
                    <option value="approved">Auswahl freigeben</option>
                    <option value="rejected">Auswahl ablehnen</option>
                </select>
                <input
                    type="text"
                    name="moderation_note"
                    maxlength="500"
                    placeholder="Hinweis für Audit-Log ..."
                    class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-3 py-1.5 text-xs text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                >
                <button type="submit" class="ui-btn ui-btn-accent !px-3 !py-1.5 !text-[0.68rem]">
                    Sammelmoderation
                </button>
            </form>
            <p class="mt-2 text-[0.68rem] uppercase tracking-[0.08em] text-stone-500">
                Auswahl erfolgt pro Post über die Checkbox "Sammel".
            </p>
        @endif
    </section>
@endif

@if ($pagePosts->isEmpty() && $posts->currentPage() === 1)
    <p class="mt-4 text-sm text-stone-400">Noch keine Beiträge in dieser Szene.</p>
@elseif ($pagePosts->isNotEmpty())
    <div id="scene-thread-page-{{ $posts->currentPage() }}" class="space-y-6" data-scene-thread-page="{{ $posts->currentPage() }}">
        <section class="thread-reading-lane ui-card-soft border-amber-700/35 bg-black/25 p-4 sm:p-5">
            <h3 class="font-heading text-2xl text-amber-100">Abenteuerfluss (IC)</h3>
            <p class="mt-1 text-xs uppercase tracking-[0.1em] text-amber-300/90">
                Romanmodus: größere Typografie, ruhiger Lesefluss, Fokus auf Szene und Figuren.
            </p>

            @if ($icPosts->isEmpty())
                <p class="mt-4 text-sm text-stone-400">Auf dieser Seite sind keine IC-Beiträge vorhanden.</p>
            @else
                <div id="scene-thread-ic-list-{{ $posts->currentPage() }}" class="mt-5 space-y-5" data-reading-post-list>
                    @foreach ($icPosts as $post)
                        @include('posts._thread-item', [
                            'post' => $post,
                            'campaign' => $campaign,
                            'scene' => $scene,
                            'viewableCharacterIds' => $viewableCharacterIds ?? [],
                        ])
                    @endforeach
                </div>
            @endif
        </section>

        @if ($scene->allow_ooc)
            <details data-ooc-thread class="thread-meta-channel ui-card-soft border-stone-700/75 bg-neutral-900/45 p-4 sm:p-5">
                <summary class="cursor-pointer list-none">
                    <h3 class="font-heading inline text-xl text-stone-100">Meta-Kanal (OOC)</h3>
                    <p class="mt-1 text-xs uppercase tracking-[0.1em] text-stone-400">
                        Absprachen, Regiehinweise und kurze Klärungen außerhalb der Szene.
                    </p>
                </summary>

                @if ($oocPosts->isEmpty())
                    <p class="mt-4 text-sm text-stone-400">Auf dieser Seite sind keine OOC-Beiträge vorhanden.</p>
                @else
                    <div id="scene-thread-ooc-list-{{ $posts->currentPage() }}" class="mt-4 space-y-4" data-reading-post-list>
                        @foreach ($oocPosts as $post)
                            @include('posts._thread-item', [
                                'post' => $post,
                                'campaign' => $campaign,
                                'scene' => $scene,
                                'viewableCharacterIds' => $viewableCharacterIds ?? [],
                            ])
                        @endforeach
                    </div>
                @endif
            </details>
        @endif
    </div>
@endif

@if ($posts->hasMorePages())
    <div
        id="scene-thread-loader-{{ $posts->currentPage() + 1 }}"
        class="mt-6 rounded-lg border border-stone-700/70 bg-black/30 p-4 text-center text-xs uppercase tracking-[0.08em] text-stone-400"
        hx-get="{{ route('campaigns.scenes.thread', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene, 'page' => $posts->currentPage() + 1]) }}"
        hx-trigger="revealed once"
        hx-swap="outerHTML"
        hx-indicator="#global-hx-indicator"
    >
        Weitere Beiträge laden ...
    </div>
@elseif ($pagePosts->isNotEmpty())
    <div class="mt-6 text-center text-xs uppercase tracking-[0.08em] text-stone-500">
        Thread-Ende erreicht.
    </div>
@endif
