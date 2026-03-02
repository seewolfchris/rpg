@php
    $currentType = old('post_type', $post->post_type ?? 'ic');
    $currentCharacter = old('character_id', $post->character_id ?? '');
    $currentFormat = old('content_format', $post->content_format ?? 'markdown');
    $currentModeration = old('moderation_status', $post->moderation_status ?? 'pending');
    $currentModerationNote = old('moderation_note');
@endphp

<div class="space-y-5">
    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <label for="post_type" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Beitragstyp</label>
            <select
                id="post_type"
                name="post_type"
                required
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            >
                <option value="ic" @selected($currentType === 'ic')>IC</option>
                <option value="ooc" @selected($currentType === 'ooc')>OOC</option>
            </select>
            <p class="mt-2 text-xs leading-relaxed text-stone-500">
                IC-Standard: Ich-Perspektive (1. Person), als schreibt dein Held selbst.
            </p>
            @error('post_type')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="character_id" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Charakter (für IC)</label>
            <select
                id="character_id"
                name="character_id"
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            >
                <option value="">Kein Charakter</option>
                @foreach ($characters as $characterOption)
                    <option value="{{ $characterOption->id }}" @selected((string) $currentCharacter === (string) $characterOption->id)>
                        {{ $characterOption->name }}
                    </option>
                @endforeach
            </select>
            @error('character_id')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="content_format" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Format</label>
            <select
                id="content_format"
                name="content_format"
                required
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            >
                <option value="markdown" @selected($currentFormat === 'markdown')>Markdown</option>
                <option value="bbcode" @selected($currentFormat === 'bbcode')>BBCode</option>
                <option value="plain" @selected($currentFormat === 'plain')>Plain Text</option>
            </select>
            @error('content_format')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div>
        <label for="content" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Inhalt</label>
        <textarea
            id="content"
            name="content"
            rows="8"
            required
            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-3 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            placeholder="Schreibe deinen Beitrag ..."
        >{{ old('content', $post->content ?? '') }}</textarea>
        <p class="mt-2 text-xs text-stone-500">Spoiler-Tag in allen Formaten: [spoiler]Geheimer Inhalt[/spoiler]</p>
        @error('content')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    @if ($showModerationControls)
        <div>
            <label for="moderation_status" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Moderationsstatus</label>
            <select
                id="moderation_status"
                name="moderation_status"
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            >
                <option value="pending" @selected($currentModeration === 'pending')>Pending</option>
                <option value="approved" @selected($currentModeration === 'approved')>Approved</option>
                <option value="rejected" @selected($currentModeration === 'rejected')>Rejected</option>
            </select>
            @error('moderation_status')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="moderation_note" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Moderationshinweis (optional)</label>
            <textarea
                id="moderation_note"
                name="moderation_note"
                rows="3"
                maxlength="500"
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                placeholder="Grund fuer Freigabe/Ablehnung ..."
            >{{ $currentModerationNote }}</textarea>
            @error('moderation_note')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-3 pt-2">
        <button
            type="submit"
            class="rounded-md border border-amber-400/70 bg-amber-500/20 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-amber-100 transition hover:bg-amber-400/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300"
        >
            {{ $submitLabel }}
        </button>

        @if (isset($post))
            <a
                href="{{ route('campaigns.scenes.show', [$post->scene->campaign, $post->scene]) }}"
                class="rounded-md border border-stone-600/80 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
            >
                Abbrechen
            </a>
        @endif
    </div>
</div>
