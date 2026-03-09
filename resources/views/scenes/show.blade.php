@extends('layouts.auth')

@section('title', $scene->title.' | Szene')

@section('content')
    <section class="mx-auto w-full max-w-6xl space-y-6">
        <div class="ui-card p-6 sm:p-8">
            <a href="{{ route('campaigns.show', $campaign) }}" class="break-words text-xs uppercase tracking-widest text-amber-300 hover:text-amber-200">
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
                    <span class="ui-badge !rounded">
                        {{ $scene->status }}
                    </span>
                    @if ($scene->allow_ooc)
                        <span class="ui-badge !rounded !border-emerald-600/60 !bg-emerald-900/20 !text-emerald-300">OOC ON</span>
                    @else
                        <span class="ui-badge !rounded !border-red-700/60 !bg-red-900/20 !text-red-300">OOC OFF</span>
                    @endif
                    <span class="ui-badge !rounded">
                        Follower: {{ $scene->subscriptions_count }}
                    </span>
                    @if ($subscription)
                        <span class="ui-badge !rounded {{ $subscription->is_muted ? '!border-red-700/60 !bg-red-900/20 !text-red-300' : '!border-emerald-600/60 !bg-emerald-900/20 !text-emerald-300' }}">
                            {{ $subscription->is_muted ? 'Abo stumm' : 'Abo aktiv' }}
                        </span>
                        @if ($latestPostId > 0)
                            <span class="ui-badge !rounded">
                                {{ $hasUnreadPosts ? 'Neu im Thread' : 'Thread gelesen' }}
                            </span>
                        @endif
                    @else
                        <span class="ui-badge !rounded">
                            Nicht abonniert
                        </span>
                    @endif
                </div>
            </div>

            @if ($scene->description)
                <article class="ui-card-soft mt-6 p-5">
                    <h2 class="font-heading text-xl text-stone-100">Szenenbeschreibung</h2>
                    <div class="mt-3 whitespace-pre-line leading-relaxed text-stone-300">{{ $scene->description }}</div>
                </article>
            @endif

            @if ($pinnedPosts->isNotEmpty())
                <section class="ui-card-soft mt-6 border-amber-700/40 bg-amber-900/10 p-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="font-heading text-lg text-amber-100">Wichtige Pins</h2>
                        <span class="ui-badge !border-amber-700/60 !bg-amber-950/30 !text-amber-100">{{ $pinnedPosts->count() }} aktiv</span>
                    </div>
                    <ul class="mt-3 space-y-2">
                        @foreach ($pinnedPosts as $pinnedPost)
                            <li class="ui-card-soft flex flex-wrap items-center justify-between gap-2 border-amber-800/40 bg-black/30 px-3 py-2">
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
                                        class="ui-btn ui-btn-accent !px-2.5 !py-1 !text-[0.65rem]"
                                    >
                                        Zum Pin
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <div class="ui-card-soft mt-6 space-y-3 p-4">
                <p class="text-xs uppercase tracking-widest text-stone-400">Schnellnavigation und Thread-Aktionen</p>
                <div class="flex flex-wrap items-center gap-3">
                @if ($jumpToLatestPostUrl)
                    <a
                        href="{{ $jumpToLatestPostUrl }}"
                        class="ui-btn"
                    >
                        Zum neuesten Post
                    </a>
                @endif
                @if ($jumpToLastReadUrl)
                    <a
                        href="{{ $jumpToLastReadUrl }}"
                        class="ui-btn"
                    >
                        Zum letzten Read
                    </a>
                @endif
                @if ($jumpToFirstUnreadUrl)
                    <a
                        href="{{ $jumpToFirstUnreadUrl }}"
                        class="ui-btn ui-btn-accent"
                    >
                        Zum ersten neuen
                    </a>
                @endif
                @if ($bookmarkJumpUrl)
                    <a
                        href="{{ $bookmarkJumpUrl }}"
                        class="ui-btn ui-btn-success"
                    >
                        Zum Bookmark
                    </a>
                @endif
                @can('create', [App\Models\Post::class, $scene])
                    <a
                        href="#new-post-form"
                        class="ui-btn"
                    >
                        Zum Schreibfeld
                    </a>
                @endcan
                @if ($canModerateScene)
                    <a
                        href="#inventory-quick-action"
                        class="ui-btn ui-btn-success"
                    >
                        Inventar-Schnellaktion
                    </a>
                @endif
                </div>

                @if ($subscription)
                    <form method="POST" action="{{ route('campaigns.scenes.subscription.mute', [$campaign, $scene]) }}">
                        @csrf
                        @method('PATCH')
                        <button
                            type="submit"
                            class="ui-btn"
                        >
                            {{ $subscription->is_muted ? 'Stumm aus' : 'Stumm schalten' }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('campaigns.scenes.unsubscribe', [$campaign, $scene]) }}">
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="ui-btn"
                        >
                            Entfolgen
                        </button>
                    </form>

                    <form method="POST" action="{{ route('campaigns.scenes.subscription.unread', [$campaign, $scene]) }}">
                        @csrf
                        @method('PATCH')
                        <button
                            type="submit"
                            class="ui-btn"
                        >
                            Als ungelesen
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('campaigns.scenes.subscribe', [$campaign, $scene]) }}">
                        @csrf
                        <button
                            type="submit"
                            class="ui-btn ui-btn-accent"
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
                        class="ui-btn ui-btn-success"
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
                            class="ui-btn ui-btn-danger"
                        >
                            Bookmark löschen
                        </button>
                    </form>
                @endif

                @can('update', $scene)
                    <a
                        href="{{ route('campaigns.scenes.edit', [$campaign, $scene]) }}"
                        class="ui-btn"
                    >
                        Szene bearbeiten
                    </a>
                @endcan

                @can('delete', $scene)
                    <form method="POST" action="{{ route('campaigns.scenes.destroy', [$campaign, $scene]) }}" onsubmit="return confirm('Szene wirklich löschen?');">
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="ui-btn ui-btn-danger"
                        >
                            Szene löschen
                        </button>
                    </form>
                @endcan
            </div>

            @if ($newPostsSinceLastRead > 0)
                <p class="ui-alert mt-4 !border-amber-600/50 !bg-amber-900/20 !px-3 !py-2 !text-xs uppercase tracking-[0.08em] !text-amber-200">
                    {{ $newPostsSinceLastRead }} neue Beiträge wurden beim Öffnen als gelesen markiert.
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

        @if ($canModerateScene)
            <section id="inventory-quick-action" class="ui-card border-emerald-800/40 bg-emerald-950/15 p-6 sm:p-8">
                <h2 class="font-heading text-2xl text-emerald-100">GM-Inventar-Schnellaktion</h2>
                <p class="mt-2 text-sm text-emerald-200/90">
                    Gegenstände direkt in der Szene hinzufügen oder entfernen, ohne den Charakterbogen zu öffnen.
                </p>

                <form method="POST" action="{{ route('campaigns.scenes.inventory-quick-action', [$campaign, $scene]) }}" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    @csrf

                    <div>
                        <label for="inventory_action_character_id" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Ziel-Held</label>
                        <select
                            id="inventory_action_character_id"
                            name="inventory_action_character_id"
                            required
                            class="w-full px-4 py-2.5 text-sm text-stone-100 sm:w-auto"
                        >
                            <option value="">Held wählen</option>
                            @foreach ($probeCharacters as $probeCharacter)
                                <option value="{{ $probeCharacter->id }}" @selected((string) old('inventory_action_character_id') === (string) $probeCharacter->id)>
                                    {{ $probeCharacter->name }}
                                    @if ($probeCharacter->relationLoaded('user') && $probeCharacter->user)
                                        ({{ $probeCharacter->user->name }})
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @error('inventory_action_character_id')
                            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="inventory_action_type" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Aktion</label>
                        <select
                            id="inventory_action_type"
                            name="inventory_action_type"
                            required
                            class="w-full px-4 py-2.5 text-sm text-stone-100 sm:w-auto"
                        >
                            <option value="add" @selected((string) old('inventory_action_type', 'add') === 'add')>Hinzufügen</option>
                            <option value="remove" @selected((string) old('inventory_action_type') === 'remove')>Entfernen</option>
                        </select>
                        @error('inventory_action_type')
                            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="inventory_action_item" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Gegenstand</label>
                        <input
                            id="inventory_action_item"
                            type="text"
                            name="inventory_action_item"
                            value="{{ old('inventory_action_item') }}"
                            maxlength="180"
                            required
                            placeholder="z. B. Seil 10m lang"
                            class="w-full px-4 py-2.5 text-sm text-stone-100 placeholder:text-stone-500"
                        >
                        @error('inventory_action_item')
                            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="inventory_action_quantity" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Menge</label>
                        <input
                            id="inventory_action_quantity"
                            type="number"
                            name="inventory_action_quantity"
                            value="{{ old('inventory_action_quantity', 1) }}"
                            min="1"
                            max="999"
                            step="1"
                            class="w-full px-4 py-2.5 text-sm text-stone-100"
                        >
                        @error('inventory_action_quantity')
                            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="inventory_action_note" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Notiz (optional)</label>
                        <input
                            id="inventory_action_note"
                            type="text"
                            name="inventory_action_note"
                            value="{{ old('inventory_action_note') }}"
                            maxlength="180"
                            placeholder="Kontext für den Log"
                            class="w-full px-4 py-2.5 text-sm text-stone-100 placeholder:text-stone-500"
                        >
                        @error('inventory_action_note')
                            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2 xl:col-span-5">
                        <label class="inline-flex items-center gap-2 text-xs uppercase tracking-widest text-stone-300">
                            <input
                                type="checkbox"
                                name="inventory_action_equipped"
                                value="1"
                                @checked((bool) old('inventory_action_equipped', false))
                                class="h-4 w-4 rounded border-stone-500 bg-neutral-900 text-emerald-500 focus:ring-emerald-500/60"
                            >
                            Als ausgerüstet eintragen (nur bei "Hinzufügen")
                        </label>
                        @error('inventory_action_equipped')
                            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2 xl:col-span-5 flex flex-wrap items-center gap-3">
                        <button
                            type="submit"
                            class="ui-btn ui-btn-success"
                        >
                            Inventar aktualisieren
                        </button>
                        <p class="text-xs text-stone-400">
                            Entfernen arbeitet auf exaktem Gegenstandsnamen (ohne Gross/Kleinschreibung).
                        </p>
                    </div>
                </form>
            </section>
        @endif

        <section class="ui-card p-6 sm:p-8">
            <h2 class="font-heading text-2xl text-stone-100">Thread</h2>

            @if ($posts->isEmpty())
                <p class="mt-4 text-sm text-stone-400">Noch keine Beiträge in dieser Szene.</p>
            @else
                @php
                    $pagePosts = $posts->getCollection();
                    $icPosts = $pagePosts->where('post_type', 'ic');
                    $oocPosts = $pagePosts->where('post_type', 'ooc');
                @endphp

                <div class="mt-5 space-y-6">
                    <section class="ui-card-soft border-amber-700/30 bg-black/20 p-4">
                        <h3 class="font-heading text-xl text-amber-100">Abenteuerfluss (IC)</h3>
                        <p class="mt-1 text-xs uppercase tracking-[0.08em] text-amber-300">
                            Fokus auf In-Character-Posts für ungestörten Lesefluss.
                        </p>

                        @if ($icPosts->isEmpty())
                            <p class="mt-4 text-sm text-stone-400">Auf dieser Seite sind keine IC-Beiträge vorhanden.</p>
                        @else
                            <div class="mt-4 space-y-4">
                                @foreach ($icPosts as $post)
                                    @include('posts._thread-item', ['post' => $post, 'campaign' => $campaign, 'scene' => $scene])
                                @endforeach
                            </div>
                        @endif
                    </section>

                    <section class="ui-card-soft border-stone-700/70 bg-neutral-900/35 p-4">
                        <h3 class="font-heading text-xl text-stone-100">OOC-Kanal</h3>
                        <p class="mt-1 text-xs uppercase tracking-[0.08em] text-stone-400">
                            Absprachen und Meta-Kommentare getrennt vom Abenteuerfluss.
                        </p>

                        @if ($oocPosts->isEmpty())
                            <p class="mt-4 text-sm text-stone-400">Auf dieser Seite sind keine OOC-Beiträge vorhanden.</p>
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
            <section id="new-post-form" class="ui-card p-6 sm:p-8">
                <h2 class="font-heading text-2xl text-stone-100">Neuer Beitrag</h2>
                <p class="mt-2 text-xs text-stone-500">
                    Offline-Modus: Beiträge werden lokal gequeued und bei wiederhergestellter Verbindung automatisch synchronisiert.
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
            <section class="ui-card p-6 text-sm text-stone-300">
                In dieser Szene sind aktuell keine neuen Beiträge möglich.
            </section>
        @endcan
    </section>
@endsection
