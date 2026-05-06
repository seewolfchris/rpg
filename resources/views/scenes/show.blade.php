@extends('layouts.auth')

@section('title', $scene->title.' | Szene')

@section('content')
    @php
        $returnTo = request()->getRequestUri();
        $sceneMoodConfig = (array) config('scenes.moods', []);
        $sceneMoodKey = (string) ($scene->mood ?: config('scenes.default_mood', 'neutral'));
        $sceneMoodMeta = (array) data_get($sceneMoodConfig, $sceneMoodKey, data_get($sceneMoodConfig, 'neutral', []));
        $sceneMoodLabel = (string) ($sceneMoodMeta['label'] ?? ucfirst($sceneMoodKey));
        $sceneMoodThemeClass = (string) ($sceneMoodMeta['theme_class'] ?? 'scene-mood-neutral');
        $sceneMoodBadgeClass = (string) ($sceneMoodMeta['badge_class'] ?? '');
        $sceneHeaderStyle = null;
        $wave3EditorPreviewEnabled = \App\Support\SensitiveFeatureGate::enabled('features.wave3.editor_preview', false);
        $wave3DraftAutosaveEnabled = \App\Support\SensitiveFeatureGate::enabled('features.wave3.draft_autosave', false);
        $wave3EditorEnhancementsEnabled = $wave3EditorPreviewEnabled || $wave3DraftAutosaveEnabled;
        $combatToolsEnabled = \App\Support\SensitiveFeatureGate::enabled('features.combat_tools_enabled', false);

        if (! empty($scene->header_image_path)) {
            $sceneHeaderStyle = "background-image: linear-gradient(to bottom, rgba(0,0,0,.3), rgba(0,0,0,.78)), url('".asset('storage/'.$scene->header_image_path)."'); background-size: cover; background-position: center;";
        }
    @endphp
    <section class="mx-auto w-full max-w-6xl space-y-6">
        <div class="ui-card {{ $sceneMoodThemeClass }} p-6 sm:p-8">
            <x-navigation.back-link :href="$backUrl" label="Zurück" />

            <div class="mt-3 flex flex-wrap items-start justify-between gap-4 rounded-xl p-4 sm:p-5 {{ $sceneHeaderStyle ? 'border border-stone-700/80' : '' }}" @if ($sceneHeaderStyle) style="{{ $sceneHeaderStyle }}" @endif>
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
                    <span class="ui-badge !rounded {{ $sceneMoodBadgeClass }}">
                        Stimmung: {{ $sceneMoodLabel }}
                    </span>
                    @if ($scene->allow_ooc)
                        <span class="ui-badge !rounded !border-emerald-600/60 !bg-emerald-900/20 !text-emerald-300">OOC aktiv</span>
                    @else
                        <span class="ui-badge !rounded !border-red-700/60 !bg-red-900/20 !text-red-300">OOC aus</span>
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

            @if ($scene->previousScene)
                <div class="ui-card-soft mt-4 flex flex-wrap items-center gap-2 border-amber-700/40 bg-amber-950/20 px-4 py-3">
                    <p class="text-xs uppercase tracking-[0.08em] text-amber-300">Diese Szene folgt auf:</p>
                    @if (auth()->user()->can('view', $scene->previousScene))
                        <a
                            href="{{ route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene->previousScene, 'return_to' => $returnTo]) }}"
                            class="text-sm font-semibold text-amber-100 underline decoration-amber-500/60 underline-offset-4 hover:text-amber-50"
                        >
                            {{ $scene->previousScene->title }}
                        </a>
                    @else
                        <span class="text-sm text-amber-100/90">{{ $scene->previousScene->title }}</span>
                    @endif
                </div>
            @endif

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
                                    @if ($pinnedPost->isGmNarration())
                                        • Spielleitung
                                    @elseif ($pinnedPost->character)
                                        • {{ $pinnedPost->character->name }}
                                    @endif
                                    @if ($pinnedPost->pinned_at)
                                        • gepinnt <x-relative-time :at="$pinnedPost->pinned_at" />
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

            <div class="mt-6 grid gap-4 xl:grid-cols-[minmax(0,1fr)_19rem]">
                <div class="ui-card-soft space-y-3 p-4" data-reading-mode-chrome>
                    <p class="text-xs uppercase tracking-widest text-stone-400">Schnellnavigation und Thread-Werkzeuge</p>
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
                            Zum letzten Lesepunkt
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
                            Zum Lesezeichen
                        </a>
                    @endif
                    @can('create', [App\Models\Post::class, $scene])
                        <a
                            href="#new-post-form"
                            hx-boost="false"
                            class="ui-btn"
                        >
                            Zum Schreibfeld
                        </a>
                    @endcan
                    @if ($canModerateScene)
                        @if ($combatToolsEnabled)
                            <a
                                href="#combat-action-tool"
                                hx-boost="false"
                                class="ui-btn ui-btn-accent"
                            >
                                Kampfaktion auswerten
                            </a>
                        @endif
                        <a
                            href="#inventory-quick-action"
                            hx-boost="false"
                            class="ui-btn ui-btn-success"
                        >
                            Inventar-Schnellaktion
                        </a>
                    @endif
                    </div>

                    @if ($subscription)
                        <form method="POST" action="{{ route('campaigns.scenes.subscription.mute', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]) }}">
                            @csrf
                            @method('PATCH')
                            <button
                                type="submit"
                                class="ui-btn"
                            >
                                {{ $subscription->is_muted ? 'Stumm aus' : 'Stumm schalten' }}
                            </button>
                        </form>

                        <form method="POST" action="{{ route('campaigns.scenes.unsubscribe', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]) }}">
                            @csrf
                            @method('DELETE')
                            <button
                                type="submit"
                                class="ui-btn"
                            >
                                Entfolgen
                            </button>
                        </form>

                        <form method="POST" action="{{ route('campaigns.scenes.subscription.unread', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]) }}">
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
                        <form method="POST" action="{{ route('campaigns.scenes.subscribe', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]) }}">
                            @csrf
                            <button
                                type="submit"
                                class="ui-btn ui-btn-accent"
                            >
                                Folgen
                            </button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('campaigns.scenes.bookmark.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]) }}" class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                        @csrf
                        <input type="hidden" name="post_id" value="{{ $latestPostId > 0 ? $latestPostId : '' }}">
                        <input
                            type="text"
                            name="label"
                            maxlength="80"
                            value="{{ old('label', $userBookmark?->label) }}"
                            placeholder="Lesezeichen-Label (optional)"
                            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-3 py-2 text-xs text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40 sm:w-48"
                        >
                        <button
                            type="submit"
                            class="ui-btn ui-btn-success"
                        >
                            {{ $userBookmark ? 'Lesezeichen aktualisieren' : 'Lesezeichen setzen' }}
                        </button>
                    </form>

                    @if ($userBookmark)
                        <form method="POST" action="{{ route('campaigns.scenes.bookmark.destroy', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]) }}">
                            @csrf
                            @method('DELETE')
                            <button
                                type="submit"
                                class="ui-btn ui-btn-danger"
                            >
                                Lesezeichen löschen
                            </button>
                        </form>
                    @endif

                    @can('update', $scene)
                        <a
                            href="{{ route('campaigns.scenes.edit', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene, 'return_to' => $returnTo]) }}"
                            class="ui-btn"
                        >
                            Szene bearbeiten
                        </a>
                    @endcan

                    @can('delete', $scene)
                        <form method="POST" action="{{ route('campaigns.scenes.destroy', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]) }}" data-confirm="Szene wirklich löschen?">
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

                <aside class="ui-card-soft p-4" data-reading-mode-chrome>
                    <h2 class="font-heading text-lg text-stone-100">Szenen-Handouts</h2>

                    @if ($sceneHandouts->isEmpty())
                        @if ($canModerateScene)
                            <p class="mt-3 text-sm text-stone-400">Noch keine Handouts vorhanden.</p>
                            <a
                                href="{{ route('campaigns.handouts.create', ['world' => $campaign->world, 'campaign' => $campaign, 'return_to' => $returnTo]) }}"
                                class="ui-btn mt-3 ui-btn-accent !px-3 !py-2 !text-[0.68rem]"
                            >
                                Handout anlegen
                            </a>
                        @else
                            <p class="mt-3 text-sm text-stone-400">Noch keine sichtbaren Handouts.</p>
                            <p class="mt-2 text-xs text-stone-500">Sobald die Spielleitung Karten, Briefe oder Hinweise freigibt, erscheinen sie hier.</p>
                        @endif
                    @else
                        <ul class="mt-3 space-y-2">
                            @foreach ($sceneHandouts as $sceneHandout)
                                <li class="ui-card-soft border-stone-700/70 bg-black/25 p-3">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="ui-badge !rounded !text-[0.62rem]">
                                            {{ $sceneHandout->scene_id === null ? 'Kampagne' : 'Szene' }}
                                        </span>
                                        @if ($canModerateScene && $sceneHandout->revealed_at === null)
                                            <span class="ui-badge !rounded !border-amber-700/70 !bg-amber-900/20 !text-amber-200 !text-[0.62rem]">Verborgen für Spieler</span>
                                        @endif
                                    </div>

                                    <a
                                        href="{{ route('campaigns.handouts.show', ['world' => $campaign->world, 'campaign' => $campaign, 'handout' => $sceneHandout, 'return_to' => $returnTo]) }}"
                                        class="mt-2 block text-sm font-semibold text-stone-100 underline decoration-amber-500/60 underline-offset-4 hover:text-amber-100"
                                    >
                                        {{ $sceneHandout->title }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="mt-5 border-t border-stone-700/70 pt-4">
                        <h2 class="font-heading text-lg text-stone-100">Chronik</h2>
                        <p class="mt-2 text-sm text-stone-400">Wichtige Ereignisse und Kapitel dieser Kampagne.</p>
                        <p class="mt-2 text-xs uppercase tracking-[0.08em] text-stone-500">
                            {{ $sceneChronicleCount }} {{ $sceneChronicleCount === 1 ? 'relevanter Eintrag' : 'relevante Einträge' }}
                        </p>
                        <a
                            href="{{ route('campaigns.story-log.index', ['world' => $campaign->world, 'campaign' => $campaign, 'return_to' => $returnTo]) }}"
                            class="ui-btn mt-3 !px-3 !py-2 !text-[0.68rem]"
                        >
                            Chronik öffnen
                        </a>
                        @can('create', [App\Models\StoryLogEntry::class, $campaign])
                            <a
                                href="{{ route('campaigns.story-log.create', ['world' => $campaign->world, 'campaign' => $campaign, 'return_to' => $returnTo]) }}"
                                class="ui-btn ui-btn-accent mt-2 !px-3 !py-2 !text-[0.68rem]"
                            >
                                Eintrag erstellen
                            </a>
                        @endcan
                    </div>

                    @can('viewAny', [App\Models\PlayerNote::class, $campaign])
                        <div class="mt-5 border-t border-stone-700/70 pt-4">
                            <h2 class="font-heading text-lg text-stone-100">Meine Notizen</h2>
                            <p class="mt-2 text-sm text-stone-400">Private Gedanken und Hinweise zu dieser Kampagne.</p>
                            <p class="mt-2 text-xs uppercase tracking-[0.08em] text-stone-500">
                                {{ $scenePlayerNotesCount }} {{ $scenePlayerNotesCount === 1 ? 'eigene Notiz' : 'eigene Notizen' }}
                            </p>
                            <a
                                href="{{ route('campaigns.player-notes.index', ['world' => $campaign->world, 'campaign' => $campaign, 'return_to' => $returnTo]) }}"
                                class="ui-btn mt-3 !px-3 !py-2 !text-[0.68rem]"
                            >
                                Notizen öffnen
                            </a>
                            @can('create', [App\Models\PlayerNote::class, $campaign])
                                <a
                                    href="{{ route('campaigns.player-notes.create', ['world' => $campaign->world, 'campaign' => $campaign, 'return_to' => $returnTo]) }}"
                                    class="ui-btn ui-btn-accent mt-2 !px-3 !py-2 !text-[0.68rem]"
                                >
                                    Notiz erstellen
                                </a>
                            @endcan
                        </div>
                    @endcan
                </aside>
            </div>

            @if ($newPostsSinceLastRead > 0)
                <p class="ui-alert mt-4 !border-amber-600/50 !bg-amber-900/20 !px-3 !py-2 !text-xs uppercase tracking-[0.08em] !text-amber-200">
                    {{ $newPostsSinceLastRead }} neue Beiträge wurden beim Öffnen als gelesen markiert.
                </p>
            @elseif ($subscription && $subscription->last_read_at)
                <p class="mt-4 text-xs uppercase tracking-[0.08em] text-stone-500">
                    Letzter Lesepunkt: <x-relative-time :at="$subscription->last_read_at" />
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
            @if ($combatToolsEnabled)
                @include('scenes.partials.combat-action-form', ['campaign' => $campaign, 'scene' => $scene, 'probeCharacters' => $probeCharacters])
            @endif

            <section id="inventory-quick-action" class="ui-card border-emerald-800/40 bg-emerald-950/15 p-6 sm:p-8" data-reading-mode-chrome>
                <h2 class="font-heading text-2xl text-emerald-100">GM-Inventar-Schnellaktion</h2>
                <p class="mt-2 text-sm text-emerald-200/90">
                    Gegenstände direkt in der Szene hinzufügen oder entfernen, ohne den Charakterbogen zu öffnen.
                </p>

                <form method="POST" action="{{ route('campaigns.scenes.inventory-quick-action', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]) }}" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
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

        <section class="ui-card p-6 sm:p-8" data-scene-thread-reading-mode data-scene-id="{{ $scene->id }}">
            <header class="reading-chapter-header ui-card-soft mb-5 border-amber-700/35 bg-black/30 p-4 sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-[0.68rem] uppercase tracking-[0.12em] text-amber-300/85">
                            Kapitel {{ str_pad((string) max(1, (int) $scene->position), 2, '0', STR_PAD_LEFT) }}
                        </p>
                        <h2 class="mt-1 font-heading text-2xl text-amber-100">{{ $scene->title }}</h2>
                        <p class="mt-1 text-sm text-stone-300">{{ $campaign->title }} · Stimmung: {{ $sceneMoodLabel }}</p>
                        <p class="mt-2 text-[0.68rem] uppercase tracking-[0.1em] text-stone-400">
                            Tasten im Romanmodus: <kbd class="rounded border border-stone-700/80 bg-black/45 px-1.5 py-0.5 text-stone-200">N</kbd> nächster Post · <kbd class="rounded border border-stone-700/80 bg-black/45 px-1.5 py-0.5 text-stone-200">P</kbd> vorheriger Post · <kbd class="rounded border border-stone-700/80 bg-black/45 px-1.5 py-0.5 text-stone-200">Esc</kbd> beendet
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="ui-btn"
                            data-reading-mode-toggle
                            data-state-off="Romanmodus starten"
                            data-state-on="Romanmodus beenden"
                            aria-pressed="false"
                        >
                            Romanmodus starten
                        </button>
                        <button
                            type="button"
                            class="ui-btn ui-btn-accent"
                            data-reading-mode-fullscreen
                        >
                            Vollbild
                        </button>
                    </div>
                </div>
            </header>
            <aside
                class="reading-progress-bookmark"
                data-reading-progress-bookmark
                aria-hidden="true"
            >
                <p class="reading-progress-label" data-reading-progress-value>Post 1 / 1</p>
                <p class="reading-progress-percent" data-reading-progress-percent>0 %</p>
                <div class="reading-progress-ribbon">
                    <span class="reading-progress-ribbon-fill" data-reading-progress-bar></span>
                </div>
            </aside>
            <h2 class="font-heading text-2xl text-stone-100">Thread</h2>
            <div id="scene-thread-feed" class="mt-5 space-y-6">
                @include('scenes.partials.thread-page', [
                    'posts' => $posts,
                    'campaign' => $campaign,
                    'scene' => $scene,
                    'viewableCharacterIds' => $viewableCharacterIds ?? [],
                ])
            </div>

            @can('create', [App\Models\Post::class, $scene])
                <div
                    class="reading-mode-exit-write mt-6 rounded-xl border border-amber-700/45 bg-black/35 p-4"
                    data-reading-mode-exit-write-panel
                >
                    <p class="text-sm text-stone-200">Du bist am Ende des aktuellen Leseflusses.</p>
                    <p class="mt-1 text-xs text-stone-400">Beende den Romanmodus, um als Spielleitung oder Charakter zu antworten.</p>
                    <button
                        type="button"
                        class="ui-btn ui-btn-accent mt-3 !px-3 !py-2 !text-[0.68rem]"
                        data-reading-mode-exit-to-write
                    >
                        Romanmodus beenden &amp; antworten
                    </button>
                </div>
            @endcan
        </section>

        @can('create', [App\Models\Post::class, $scene])
            <section id="new-post-form" class="ui-card p-6 sm:p-8" data-reading-mode-chrome>
                <h2 class="font-heading text-2xl text-stone-100">Neuer Beitrag</h2>
                <p class="mt-2 text-xs text-stone-500">
                    Offline-Modus: Briefe werden lokal vorgemerkt und bei wiederhergestellter Verbindung automatisch zugestellt.
                </p>
                <div
                    id="offline-queue-status-panel"
                    class="mt-4 hidden rounded-lg border border-amber-700/45 bg-black/25 p-4"
                    aria-live="polite"
                ></div>
                <div
                    id="offline-dead-letter-panel"
                    class="mt-4 hidden rounded-lg border border-amber-700/45 bg-black/25 p-4"
                    aria-live="polite"
                ></div>
                <form
                    method="POST"
                    action="{{ route('campaigns.scenes.posts.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]) }}"
                    enctype="multipart/form-data"
                    class="mt-6"
                    data-offline-post-form
                    @if ($wave3EditorEnhancementsEnabled) data-post-editor @endif
                    @if ($wave3EditorPreviewEnabled) data-preview-url="{{ route('posts.preview', ['world' => $campaign->world]) }}" @endif
                    @if ($wave3DraftAutosaveEnabled) data-draft-key="scene-{{ $scene->id }}-user-{{ auth()->id() }}-new" @endif
                >
                    @csrf
                    @include('posts._form', [
                        'post' => null,
                        'characters' => $characters,
                        'canUseGmPostMode' => $canModerateScene,
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
