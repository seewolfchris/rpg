@php
    $isIcPost = $post->post_type === 'ic';
    $isOocPost = $post->post_type === 'ooc';
    $postContentClass = $isIcPost
        ? 'mx-auto max-w-[78ch] text-[1.03rem] leading-8 text-stone-100 sm:text-[1.08rem] [&_blockquote]:my-6 [&_blockquote]:rounded-xl [&_blockquote]:border-amber-700/45 [&_blockquote]:bg-amber-900/10 [&_blockquote]:px-5 [&_blockquote]:py-4 [&_blockquote]:italic [&_blockquote]:text-amber-100 [&_p]:my-0 [&_p+p]:mt-5'
        : 'max-w-[84ch] text-[0.96rem] leading-7 text-stone-200 [&_blockquote]:my-4 [&_blockquote]:rounded-lg [&_blockquote]:border-stone-600/70 [&_blockquote]:bg-black/30 [&_blockquote]:px-4 [&_blockquote]:py-3 [&_blockquote]:text-stone-200 [&_p]:my-0 [&_p+p]:mt-4';
@endphp

<article id="post-{{ $post->id }}" data-post-type="{{ $post->post_type }}" data-reading-post-anchor tabindex="-1" class="thread-post {{ $isIcPost ? 'thread-post-ic' : 'thread-post-ooc' }} rounded-xl border p-5 sm:p-6">
    <div class="thread-post-meta flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
            <p class="{{ $isIcPost ? 'text-base text-stone-100 sm:text-lg' : 'text-sm text-stone-200' }}">
                <span class="font-semibold">{{ $post->user->name }}</span>
                <span class="text-stone-500">• <x-relative-time :at="$post->created_at" /></span>
            </p>

            @if ($post->character)
                <p class="mt-1 text-xs uppercase tracking-[0.08em] text-amber-300/95">
                    Charakter: {{ $post->character->name }}
                </p>
            @endif

            <div class="mt-2 flex flex-wrap items-center gap-2">
                <span class="rounded border border-stone-600/80 bg-black/40 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-stone-300">
                    {{ $isOocPost ? 'Meta' : strtoupper($post->post_type) }}
                </span>
                <span class="rounded border border-stone-600/80 bg-black/40 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-stone-300">
                    {{ match ($post->moderation_status) {
                        'approved' => 'Freigegeben',
                        'rejected' => 'Abgelehnt',
                        default => 'Ausstehend',
                    } }}
                </span>
                <span class="rounded border border-stone-600/80 bg-black/40 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-stone-300">
                    {{ match ($post->content_format) {
                        'markdown' => 'Markdown',
                        'bbcode' => 'BBCode',
                        default => 'Klartext',
                    } }}
                </span>
                @if ($post->is_edited)
                    <span class="rounded border border-stone-600/80 bg-black/40 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-stone-300">Bearbeitet</span>
                @endif
                @if ($post->is_pinned)
                    <span class="rounded border border-amber-600/70 bg-amber-900/20 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-amber-300">Angepinnt</span>
                @endif
            </div>
        </div>

        <div class="flex shrink-0 flex-wrap items-center gap-2">
            @can('moderate', $post)
                <label for="bulk_post_{{ $post->id }}" class="inline-flex items-center gap-2 rounded-md border border-stone-600/80 bg-black/35 px-2.5 py-1.5 text-[0.65rem] uppercase tracking-[0.08em] text-stone-300">
                    <input
                        id="bulk_post_{{ $post->id }}"
                        type="checkbox"
                        name="post_ids[]"
                        value="{{ $post->id }}"
                        form="thread-moderation-bulk-form"
                        class="h-3.5 w-3.5 rounded border-stone-500 bg-neutral-900 text-amber-400 focus:ring-amber-500/60"
                    >
                    Sammel
                </label>
            @endcan

            <form
                method="POST"
                action="{{ route('campaigns.scenes.bookmark.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]) }}"
                hx-target="#post-{{ $post->id }}"
                hx-swap="outerHTML"
                class="contents"
            >
                @csrf
                <input type="hidden" name="post_id" value="{{ $post->id }}">
                <button
                    type="submit"
                    class="rounded-md border border-emerald-600/70 bg-emerald-900/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-emerald-200 transition hover:bg-emerald-900/35"
                >
                    Lesezeichen
                </button>
            </form>

            @can('moderate', $post)
                @if ($post->is_pinned)
                    <form
                        method="POST"
                        action="{{ route('posts.unpin', ['world' => $campaign->world, 'post' => $post]) }}"
                        hx-target="#post-{{ $post->id }}"
                        hx-swap="outerHTML"
                        class="contents"
                    >
                        @csrf
                        @method('PATCH')
                        <button
                            type="submit"
                            class="rounded-md border border-amber-600/70 bg-amber-900/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-amber-200 transition hover:bg-amber-900/35"
                        >
                            Pin lösen
                        </button>
                    </form>
                @else
                    <form
                        method="POST"
                        action="{{ route('posts.pin', ['world' => $campaign->world, 'post' => $post]) }}"
                        hx-target="#post-{{ $post->id }}"
                        hx-swap="outerHTML"
                        class="contents"
                    >
                        @csrf
                        @method('PATCH')
                        <button
                            type="submit"
                            class="rounded-md border border-amber-600/70 bg-amber-900/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-amber-200 transition hover:bg-amber-900/35"
                        >
                            Anpinnen
                        </button>
                    </form>
                @endif
            @endcan

            @can('update', $post)
                <a
                    href="{{ route('posts.edit', ['world' => $campaign->world, 'post' => $post]) }}"
                    class="rounded-md border border-stone-600/80 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                >
                    Bearbeiten
                </a>
            @endcan

            @can('delete', $post)
                <form method="POST" action="{{ route('posts.destroy', ['world' => $campaign->world, 'post' => $post]) }}" onsubmit="return confirm('Beitrag wirklich löschen?');">
                    @csrf
                    @method('DELETE')
                    <button
                        type="submit"
                        class="rounded-md border border-red-700/80 bg-red-900/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-red-200 transition hover:bg-red-900/40"
                    >
                        Löschen
                    </button>
                </form>
            @endcan
        </div>
    </div>

    <div class="thread-post-content narrative-flow mt-4 break-words {{ $postContentClass }} [&_a]:text-amber-300 [&_a]:underline [&_blockquote]:border-l [&_blockquote]:pl-4 [&_code]:rounded [&_code]:bg-black/50 [&_code]:px-1 [&_pre]:overflow-x-auto [&_pre]:rounded [&_pre]:border [&_pre]:border-stone-800 [&_pre]:bg-black/50 [&_pre]:p-3">
        @php($postMeta = is_array($post->meta) ? $post->meta : [])
        @php($icQuote = trim((string) ($postMeta['ic_quote'] ?? '')))
        @if ($post->post_type === 'ic' && $icQuote !== '')
            <blockquote class="mb-5 rounded-xl border border-amber-600/50 bg-amber-900/10 px-5 py-4 text-base italic leading-relaxed text-amber-100">
                „{{ $icQuote }}“
            </blockquote>
        @endif
        {!! $post->renderedContent() !!}
    </div>

    @if (\App\Support\SensitiveFeatureGate::enabled('features.wave4.reactions', false))
        @php($reactionSymbols = ['heart' => '❤️', 'joy' => '😂', 'clap' => '👏', 'fire' => '🔥'])
        @php($reactionCollection = $post->relationLoaded('reactions') ? $post->reactions : collect())
        @php($reactionCounts = $reactionCollection->groupBy('emoji')->map(fn ($items) => $items->count()))
        @php($currentUserReactionKeys = $reactionCollection->where('user_id', (int) auth()->id())->pluck('emoji')->all())
        <section class="mt-4 flex flex-wrap items-center gap-2">
            @foreach ($reactionSymbols as $reactionKey => $reactionSymbol)
                @php($currentCount = (int) ($reactionCounts[$reactionKey] ?? 0))
                @php($hasReacted = in_array($reactionKey, $currentUserReactionKeys, true))
                @if ($hasReacted)
                    <form method="POST" action="{{ route('posts.reactions.destroy', ['world' => $campaign->world, 'post' => $post]) }}">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="emoji" value="{{ $reactionKey }}">
                        <button
                            type="submit"
                            class="rounded-full border border-amber-600/70 bg-amber-900/25 px-2.5 py-1 text-xs text-amber-100 transition hover:bg-amber-900/40"
                        >
                            {{ $reactionSymbol }} {{ $currentCount }}
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('posts.reactions.store', ['world' => $campaign->world, 'post' => $post]) }}">
                        @csrf
                        <input type="hidden" name="emoji" value="{{ $reactionKey }}">
                        <button
                            type="submit"
                            class="rounded-full border border-stone-600/80 bg-black/35 px-2.5 py-1 text-xs text-stone-200 transition hover:border-stone-400"
                        >
                            {{ $reactionSymbol }} {{ $currentCount }}
                        </button>
                    </form>
                @endif
            @endforeach
        </section>
    @endif

    @if ($post->diceRoll)
        @php($probeRolls = is_array($post->diceRoll->rolls) ? $post->diceRoll->rolls : [])
        @php($probeAttributeConfig = (array) config('character_sheet.attributes', []))
        @php($probeAttributeKey = (string) ($post->diceRoll->probe_attribute_key ?? ''))
        @php($probeAttributeLabel = $probeAttributeKey !== '' ? (string) data_get($probeAttributeConfig, $probeAttributeKey.'.label', strtoupper($probeAttributeKey)) : null)
        @php($probeOutcomeLabel = $post->diceRoll->probe_is_success === null ? null : ($post->diceRoll->probe_is_success ? 'Bestanden' : 'Nicht bestanden'))
        <section class="dice-roll-visual mt-4 rounded-lg border border-amber-700/40 bg-amber-900/10 p-4">
            <p class="text-xs uppercase tracking-widest text-amber-300">GM-Probe</p>
            <p class="mt-2 text-sm text-stone-200">{{ $post->diceRoll->label }}</p>

            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs uppercase tracking-[0.08em] text-stone-400">
                <span class="rounded border border-stone-600/80 bg-black/35 px-2 py-1">
                    Held: {{ $post->diceRoll->character?->name ?? 'Unbekannt' }}
                </span>
                <span class="rounded border border-stone-600/80 bg-black/35 px-2 py-1">
                    Modus: {{ strtoupper($post->diceRoll->roll_mode) }}
                </span>
                <span class="rounded border border-stone-600/80 bg-black/35 px-2 py-1">
                    Modifikator: {{ $post->diceRoll->modifier >= 0 ? '+' : '' }}{{ $post->diceRoll->modifier }}
                </span>
                @if ($probeAttributeLabel)
                    <span class="rounded border border-stone-600/80 bg-black/35 px-2 py-1">
                        Probe auf: {{ $probeAttributeLabel }}
                        @if ($post->diceRoll->probe_target_value !== null)
                            ({{ $post->diceRoll->probe_target_value }} %)
                        @endif
                    </span>
                @endif
                @if ($probeOutcomeLabel)
                    <span class="rounded border border-stone-600/80 bg-black/35 px-2 py-1">
                        Ergebnis: {{ $probeOutcomeLabel }}
                    </span>
                @endif
            </div>

            <div class="dice-roll-track mt-3">
                @foreach ($probeRolls as $probeRoll)
                    <span class="dice-roll-chip" style="--dice-index: {{ $loop->index }};">W{{ $probeRoll }}</span>
                @endforeach
            </div>

            <p class="mt-3 text-sm text-stone-200">
                Wurf: [{{ implode(', ', $probeRolls) }}]
                @if (count($probeRolls) > 1)
                    -> genommen: {{ $post->diceRoll->kept_roll }}
                @endif
                {{ $post->diceRoll->modifier >= 0 ? '+' : '' }}{{ $post->diceRoll->modifier }}
                = <span class="font-semibold text-amber-200">{{ $post->diceRoll->total }}</span>
            </p>

            @if ($post->diceRoll->is_critical_success)
                <p class="mt-2 text-xs uppercase tracking-[0.08em] text-emerald-300">Kritischer Erfolg</p>
            @elseif ($post->diceRoll->is_critical_failure)
                <p class="mt-2 text-xs uppercase tracking-[0.08em] text-red-300">Kritischer Fehlschlag</p>
            @endif

            @if (
                (int) $post->diceRoll->applied_le_delta !== 0
                || (int) $post->diceRoll->applied_ae_delta !== 0
                || $post->diceRoll->resulting_le_current !== null
                || $post->diceRoll->resulting_ae_current !== null
            )
                <div class="mt-3 rounded-md border border-stone-700/80 bg-black/30 p-3 text-xs uppercase tracking-[0.08em] text-stone-300">
                    <p class="text-stone-400">Direkte Auswirkungen auf den Charakterbogen</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span class="rounded border border-stone-600/80 bg-black/35 px-2 py-1">
                            LE: {{ $post->diceRoll->applied_le_delta >= 0 ? '+' : '' }}{{ $post->diceRoll->applied_le_delta }}
                            @if ($post->diceRoll->resulting_le_current !== null)
                                => {{ $post->diceRoll->resulting_le_current }}
                            @endif
                        </span>
                        <span class="rounded border border-stone-600/80 bg-black/35 px-2 py-1">
                            AE: {{ $post->diceRoll->applied_ae_delta >= 0 ? '+' : '' }}{{ $post->diceRoll->applied_ae_delta }}
                            @if ($post->diceRoll->resulting_ae_current !== null)
                                => {{ $post->diceRoll->resulting_ae_current }}
                            @endif
                        </span>
                    </div>
                    @php($probeDamageMeta = is_array($post->meta) ? ($post->meta['probe_damage'] ?? null) : null)
                    @if (is_array($probeDamageMeta) && (int) ($probeDamageMeta['requested_damage'] ?? 0) > 0)
                        <p class="mt-2 text-[0.7rem] text-stone-400">
                            Schaden: {{ (int) $probeDamageMeta['requested_damage'] }}
                            - RS {{ (int) ($probeDamageMeta['armor_rs'] ?? 0) }}
                            = {{ (int) ($probeDamageMeta['effective_damage'] ?? 0) }}
                        </p>
                    @endif
                </div>
            @endif
        </section>
    @endif

    @php($inventoryAward = is_array($post->meta) ? ($post->meta['inventory_award'] ?? null) : null)
    @if (is_array($inventoryAward) && trim((string) ($inventoryAward['item'] ?? '')) !== '')
        @php($inventoryAwardQuantity = max(1, (int) ($inventoryAward['quantity'] ?? 1)))
        @php($inventoryAwardEquipped = (bool) ($inventoryAward['equipped'] ?? false))
        <section class="mt-4 rounded-lg border border-emerald-700/40 bg-emerald-900/10 p-4">
            <p class="text-xs uppercase tracking-widest text-emerald-300">Inventar aktualisiert</p>
            <p class="mt-2 text-sm text-stone-200">
                Held:
                <span class="font-semibold text-emerald-200">{{ $inventoryAward['character_name'] ?? 'Unbekannt' }}</span>
            </p>
            <p class="mt-1 text-sm text-stone-200">
                Neuer Gegenstand:
                <span class="font-semibold text-emerald-100">{{ $inventoryAwardQuantity }}x {{ $inventoryAward['item'] }}</span>
                @if ($inventoryAwardEquipped)
                    <span class="text-xs uppercase tracking-[0.08em] text-emerald-300">(ausgerüstet)</span>
                @endif
            </p>
        </section>
    @endif

    @if ($post->revisions->isNotEmpty())
        <details class="mt-4 rounded-lg border border-stone-800/80 bg-black/30 p-3">
            <summary class="cursor-pointer text-xs uppercase tracking-[0.08em] text-stone-400">
                Bearbeitungsverlauf ({{ $post->revisions->count() }})
            </summary>

            <ol class="mt-3 space-y-3">
                @foreach ($post->revisions as $revision)
                    <li class="revision-ink-entry rounded-md border border-stone-800/70 bg-neutral-900/50 p-3">
                        <p class="text-xs uppercase tracking-[0.08em] text-stone-500">
                            v{{ $revision->version }}
                            • <x-relative-time :at="$revision->created_at" />
                            • {{ strtoupper($revision->post_type) }}
                            @if ($revision->editor)
                                • bearbeitet von {{ $revision->editor->name }}
                            @endif
                        </p>
                        <div class="revision-ink-content mt-2 break-words text-sm leading-relaxed text-stone-300 [&_a]:text-amber-300 [&_a]:underline [&_blockquote]:border-l [&_blockquote]:border-stone-700 [&_blockquote]:pl-4 [&_code]:rounded [&_code]:bg-black/50 [&_code]:px-1 [&_pre]:overflow-x-auto [&_pre]:rounded [&_pre]:border [&_pre]:border-stone-800 [&_pre]:bg-black/50 [&_pre]:p-3">
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
                • <x-relative-time :at="$post->pinned_at" />
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
                            • <x-relative-time :at="$log->created_at" />
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
        <form
            method="POST"
            action="{{ route('posts.moderate', ['world' => $campaign->world, 'post' => $post]) }}"
            class="mt-4 flex flex-wrap items-start gap-2 sm:items-center"
            hx-target="#post-{{ $post->id }}"
            hx-swap="outerHTML"
        >
            @csrf
            @method('PATCH')
            <label for="moderation_status_{{ $post->id }}" class="text-xs uppercase tracking-[0.08em] text-stone-400">Moderation</label>
            <select
                id="moderation_status_{{ $post->id }}"
                name="moderation_status"
                class="rounded-md border border-stone-600/80 bg-neutral-900/80 px-2 py-1.5 text-xs text-stone-100"
            >
                <option value="pending" @selected($post->moderation_status === 'pending')>Ausstehend</option>
                <option value="approved" @selected($post->moderation_status === 'approved')>Freigegeben</option>
                <option value="rejected" @selected($post->moderation_status === 'rejected')>Abgelehnt</option>
            </select>
            <input
                type="text"
                name="moderation_note"
                maxlength="500"
                placeholder="Optionaler Hinweis ..."
                class="min-w-0 w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-3 py-1.5 text-xs text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40 sm:flex-1"
            >
            <button
                type="submit"
                class="rounded-md border border-amber-500/60 bg-amber-500/15 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/30"
            >
                Setzen
            </button>
        </form>
    @endcan
</article>

@if (request()->header('HX-Request') === 'true' && isset($bookmarkCountForNav))
    @if ($bookmarkCountForNav > 0)
        <span id="nav-bookmark-count-badge" hx-swap-oob="outerHTML" class="absolute -right-1.5 -top-1.5 inline-flex min-w-5 items-center justify-center rounded-full border border-emerald-300/80 bg-emerald-500 px-1.5 text-[0.6rem] font-bold text-black">
            {{ $bookmarkCountForNav > 99 ? '99+' : $bookmarkCountForNav }}
        </span>
    @else
        <span id="nav-bookmark-count-badge" hx-swap-oob="outerHTML" class="hidden"></span>
    @endif
@endif
