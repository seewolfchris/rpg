<div class="space-y-5">
    <x-form-error-summary data-knowledge-admin-error-summary />

    <div>
        <label for="name" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Name</label>
        <input
            id="name"
            type="text"
            name="name"
            value="{{ old('name', $category->name ?? '') }}"
            required
            maxlength="120"
            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            placeholder="z. B. Regionen"
        >
        @error('name')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="slug" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Slug</label>
        <input
            id="slug"
            type="text"
            name="slug"
            value="{{ old('slug', $category->slug ?? '') }}"
            maxlength="140"
            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            placeholder="wird automatisch aus dem Namen generiert"
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
            placeholder="Kurztext für Übersicht und Kontext"
        >{{ old('summary', $category->summary ?? '') }}</textarea>
        @error('summary')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="position" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Position</label>
            <input
                id="position"
                type="number"
                name="position"
                min="0"
                max="65535"
                value="{{ old('position', $category->position ?? 0) }}"
                class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            >
            @error('position')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div class="rounded-md border border-stone-700/80 bg-neutral-900/60 px-4 py-3">
            <label class="inline-flex items-center gap-2 text-sm text-stone-200">
                <input
                    type="checkbox"
                    name="is_public"
                    value="1"
                    @checked(old('is_public', $category->is_public ?? true))
                    class="h-4 w-4 rounded border-stone-500 bg-neutral-900 text-amber-500 focus:ring-amber-500/60"
                >
                Kategorie öffentlich sichtbar
            </label>
            @error('is_public')
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
            href="{{ $cancelUrl ?? route('knowledge.admin.kategorien.index', ['world' => $world ?? config('worlds.default_slug')]) }}"
            class="rounded-md border border-stone-600/80 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
        >
            Abbrechen
        </a>
    </div>
</div>
