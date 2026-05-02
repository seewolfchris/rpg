<div class="space-y-5">
    @php
        $cancelUrl = is_string($cancelUrl ?? null) && $cancelUrl !== ''
            ? $cancelUrl
            : (isset($campaign)
                ? route('campaigns.show', ['world' => $world, 'campaign' => $campaign])
                : route('campaigns.index', ['world' => $world]));
    @endphp

    <div>
        <label for="title" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Titel</label>
        <input
            id="title"
            type="text"
            name="title"
            value="{{ old('title', $campaign->title ?? '') }}"
            required
            maxlength="150"
            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            placeholder="z. B. Die Schwärze von Morhaven"
        >
        @error('title')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="slug" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Slug</label>
        <input
            id="slug"
            type="text"
            name="slug"
            value="{{ old('slug', $campaign->slug ?? '') }}"
            required
            maxlength="170"
            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            placeholder="die-finsternis-von-morhaven"
        >
        @error('slug')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="summary" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Kurzbeschreibung</label>
        <textarea
            id="summary"
            name="summary"
            rows="3"
            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-3 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            placeholder="Worum geht es in dieser Kampagne?"
        >{{ old('summary', $campaign->summary ?? '') }}</textarea>
        @error('summary')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="lore" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Lore / Welttext</label>
        <textarea
            id="lore"
            name="lore"
            rows="8"
            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-3 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            placeholder="Ausführlicher Hintergrund zur Kampagne ..."
        >{{ old('lore', $campaign->lore ?? '') }}</textarea>
        @error('lore')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="status" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Status</label>
            <select
                id="status"
                name="status"
                required
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            >
                @php($campaignStatus = old('status', $campaign->status ?? 'draft'))
                <option value="draft" @selected($campaignStatus === 'draft')>Draft</option>
                <option value="active" @selected($campaignStatus === 'active')>Active</option>
                <option value="archived" @selected($campaignStatus === 'archived')>Archived</option>
            </select>
            @error('status')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div class="space-y-3">
            <label class="flex items-center gap-2 text-sm text-stone-300">
                <input
                    type="checkbox"
                    name="is_public"
                    value="1"
                    @checked(old('is_public', $campaign->is_public ?? false))
                    class="h-4 w-4 rounded border-stone-500 bg-neutral-900 text-amber-500 focus:ring-amber-500/60"
                >
                Öffentlich sichtbar
            </label>
            <label class="flex items-center gap-2 text-sm text-stone-300">
                <input
                    type="checkbox"
                    name="requires_post_moderation"
                    value="1"
                    @checked(old('requires_post_moderation', $campaign->requires_post_moderation ?? false))
                    class="h-4 w-4 rounded border-stone-500 bg-neutral-900 text-amber-500 focus:ring-amber-500/60"
                >
                Beiträge vor Veröffentlichung moderieren
            </label>
            @error('requires_post_moderation')
                <p class="text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="starts_at" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Startdatum</label>
            <input
                id="starts_at"
                type="datetime-local"
                name="starts_at"
                value="{{ old('starts_at', isset($campaign?->starts_at) ? $campaign->starts_at->format('Y-m-d\TH:i') : '') }}"
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            >
            @error('starts_at')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="ends_at" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Enddatum</label>
            <input
                id="ends_at"
                type="datetime-local"
                name="ends_at"
                value="{{ old('ends_at', isset($campaign?->ends_at) ? $campaign->ends_at->format('Y-m-d\TH:i') : '') }}"
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            >
            @error('ends_at')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3 pt-2">
        <button
            type="submit"
            class="rounded-md border border-amber-400/70 bg-amber-500/20 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-amber-100 transition hover:bg-amber-400/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300"
        >
            {{ $submitLabel }}
        </button>

        <a
            href="{{ $cancelUrl }}"
            class="rounded-md border border-stone-600/80 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
        >
            Abbrechen
        </a>
    </div>
</div>
