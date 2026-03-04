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

    <div class="mt-4 break-words leading-relaxed text-stone-200 [&_a]:text-amber-300 [&_a]:underline [&_blockquote]:border-l [&_blockquote]:border-stone-700 [&_blockquote]:pl-4 [&_code]:rounded [&_code]:bg-black/50 [&_code]:px-1 [&_pre]:overflow-x-auto [&_pre]:rounded [&_pre]:border [&_pre]:border-stone-800 [&_pre]:bg-black/50 [&_pre]:p-3">
        {!! $post->renderedContent() !!}
    </div>

    @if ($post->diceRoll)
        @php($probeRolls = is_array($post->diceRoll->rolls) ? $post->diceRoll->rolls : [])
        <section class="mt-4 rounded-lg border border-amber-700/40 bg-amber-900/10 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-amber-300">GM-Probe</p>
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
                </div>
            @endif
        </section>
    @endif

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
                        <div class="mt-2 break-words text-sm leading-relaxed text-stone-300 [&_a]:text-amber-300 [&_a]:underline [&_blockquote]:border-l [&_blockquote]:border-stone-700 [&_blockquote]:pl-4 [&_code]:rounded [&_code]:bg-black/50 [&_code]:px-1 [&_pre]:overflow-x-auto [&_pre]:rounded [&_pre]:border [&_pre]:border-stone-800 [&_pre]:bg-black/50 [&_pre]:p-3">
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
        <form method="POST" action="{{ route('posts.moderate', $post) }}" class="mt-4 flex flex-wrap items-start gap-2 sm:items-center">
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
                class="rounded-md border border-amber-500/60 bg-amber-500/15 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.1em] text-amber-100 transition hover:bg-amber-500/30"
            >
                Setzen
            </button>
        </form>
    @endcan
</article>
