@php
    $pagePosts = $posts->getCollection();
    $icPosts = $pagePosts->where('post_type', 'ic');
    $oocPosts = $pagePosts->where('post_type', 'ooc');
@endphp

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
