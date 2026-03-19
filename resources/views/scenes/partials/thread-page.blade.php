@php
    $pagePosts = $posts->getCollection();
    $icPosts = $pagePosts->where('post_type', 'ic');
    $oocPosts = $pagePosts->where('post_type', 'ooc');
    $subscription = $subscription ?? null;
    $latestPostId = (int) ($latestPostId ?? 0);
    $unreadPostsCount = max(0, (int) ($unreadPostsCount ?? 0));
    $canModerateScene = (bool) ($canModerateScene ?? false);
@endphp

@if ($posts->currentPage() === 1 && auth()->check())
    <section id="scene-thread-live-controls" class="ui-card-soft border-amber-700/30 bg-black/20 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs uppercase tracking-[0.08em] text-stone-400">
                Read-Tracking
                <span id="scene-thread-unread-count" class="ml-2 rounded border border-amber-700/60 bg-amber-900/20 px-2 py-0.5 text-[0.65rem] text-amber-200">
                    Ungelesen: {{ $unreadPostsCount }}
                </span>
            </p>
            <div class="flex flex-wrap items-center gap-2">
                @if ($latestPostId > 0)
                    <a
                        href="{{ route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene, 'jump' => 'latest']) }}"
                        class="ui-btn !px-2.5 !py-1 !text-[0.65rem]"
                    >
                        Neuester
                    </a>
                @endif
                @if ($subscription && (int) ($subscription->last_read_post_id ?? 0) > 0)
                    <a
                        href="{{ route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene, 'jump' => 'last_read']) }}"
                        class="ui-btn !px-2.5 !py-1 !text-[0.65rem]"
                    >
                        Letzter Read
                    </a>
                @endif
                @if ($unreadPostsCount > 0)
                    <a
                        href="{{ route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene, 'jump' => 'first_unread']) }}"
                        class="ui-btn ui-btn-accent !px-2.5 !py-1 !text-[0.65rem]"
                    >
                        Erstes Ungelesen
                    </a>
                @endif
            </div>
        </div>

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
                    <button type="submit" class="ui-btn !px-2.5 !py-1 !text-[0.65rem]">
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
                    <button type="submit" class="ui-btn !px-2.5 !py-1 !text-[0.65rem]">
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
                <button type="submit" class="ui-btn ui-btn-accent !px-3 !py-1.5 !text-[0.65rem]">
                    Bulk Moderation
                </button>
            </form>
            <p class="mt-2 text-[0.65rem] uppercase tracking-[0.08em] text-stone-500">
                Auswahl erfolgt pro Post über die Checkbox "Bulk".
            </p>
        @endif
    </section>
@endif

@if ($pagePosts->isEmpty() && $posts->currentPage() === 1)
    <p class="mt-4 text-sm text-stone-400">Noch keine Beiträge in dieser Szene.</p>
@elseif ($pagePosts->isNotEmpty())
    <div id="scene-thread-page-{{ $posts->currentPage() }}" class="space-y-6" data-scene-thread-page="{{ $posts->currentPage() }}">
        <section class="ui-card-soft border-amber-700/30 bg-black/20 p-4">
            <h3 class="font-heading text-xl text-amber-100">Abenteuerfluss (IC)</h3>
            <p class="mt-1 text-xs uppercase tracking-[0.08em] text-amber-300">
                Fokus auf In-Character-Posts für ungestörten Lesefluss.
            </p>

            @if ($icPosts->isEmpty())
                <p class="mt-4 text-sm text-stone-400">Auf dieser Seite sind keine IC-Beiträge vorhanden.</p>
            @else
                <div id="scene-thread-ic-list-{{ $posts->currentPage() }}" class="mt-4 space-y-4">
                    @foreach ($icPosts as $post)
                        @include('posts._thread-item', ['post' => $post, 'campaign' => $campaign, 'scene' => $scene])
                    @endforeach
                </div>
            @endif
        </section>

        <details data-ooc-thread class="ui-card-soft border-stone-700/70 bg-neutral-900/35 p-4">
            <summary class="cursor-pointer list-none">
                <h3 class="font-heading inline text-xl text-stone-100">OOC-Kanal</h3>
                <p class="mt-1 text-xs uppercase tracking-[0.08em] text-stone-400">
                    Absprachen und Meta-Kommentare getrennt vom Abenteuerfluss.
                </p>
            </summary>

            @if ($oocPosts->isEmpty())
                <p class="mt-4 text-sm text-stone-400">Auf dieser Seite sind keine OOC-Beiträge vorhanden.</p>
            @else
                <div id="scene-thread-ooc-list-{{ $posts->currentPage() }}" class="mt-4 space-y-4">
                    @foreach ($oocPosts as $post)
                        @include('posts._thread-item', ['post' => $post, 'campaign' => $campaign, 'scene' => $scene])
                    @endforeach
                </div>
            @endif
        </details>
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
        Ältere Beiträge laden ...
    </div>
@elseif ($pagePosts->isNotEmpty())
    <div class="mt-6 text-center text-xs uppercase tracking-[0.08em] text-stone-500">
        Thread-Ende erreicht.
    </div>
@endif
