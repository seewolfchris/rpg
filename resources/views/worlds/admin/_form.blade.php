@if ($errors->any())
    <section
        class="mb-6 rounded-md border border-red-700/70 bg-red-900/20 px-4 py-3 text-sm text-red-100"
        data-world-admin-error-summary
    >
        <p class="font-semibold uppercase tracking-widest text-red-200">Bitte Eingaben prüfen</p>
        <ul class="mt-2 list-disc space-y-1 pl-5 text-red-100/95">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </section>
@endif

<div class="grid gap-6 lg:grid-cols-2">
    <div>
        <label for="name" class="mb-2 block text-xs font-semibold uppercase tracking-widest text-stone-300">Name</label>
        <input
            id="name"
            name="name"
            type="text"
            value="{{ old('name', $world->name ?? '') }}"
            maxlength="120"
            required
            class="w-full rounded-md border border-stone-700/80 bg-black/45 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/35"
        >
    </div>
    <div>
        <label for="slug" class="mb-2 block text-xs font-semibold uppercase tracking-widest text-stone-300">Slug</label>
        <input
            id="slug"
            name="slug"
            type="text"
            value="{{ old('slug', $world->slug ?? '') }}"
            maxlength="140"
            required
            class="w-full rounded-md border border-stone-700/80 bg-black/45 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/35"
        >
        @error('slug')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>
    <div class="lg:col-span-2">
        <label for="tagline" class="mb-2 block text-xs font-semibold uppercase tracking-widest text-stone-300">Tagline</label>
        <input
            id="tagline"
            name="tagline"
            type="text"
            value="{{ old('tagline', $world->tagline ?? '') }}"
            maxlength="180"
            class="w-full rounded-md border border-stone-700/80 bg-black/45 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/35"
        >
    </div>
    <div class="lg:col-span-2">
        <label for="description" class="mb-2 block text-xs font-semibold uppercase tracking-widest text-stone-300">Beschreibung</label>
        <textarea
            id="description"
            name="description"
            rows="5"
            maxlength="5000"
            class="w-full rounded-md border border-stone-700/80 bg-black/45 px-4 py-3 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/35"
        >{{ old('description', $world->description ?? '') }}</textarea>
    </div>
    <div>
        <label for="position" class="mb-2 block text-xs font-semibold uppercase tracking-widest text-stone-300">Position</label>
        <input
            id="position"
            name="position"
            type="number"
            min="0"
            max="65535"
            value="{{ old('position', $world->position ?? 0) }}"
            class="w-full rounded-md border border-stone-700/80 bg-black/45 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/35"
        >
    </div>
    <div class="flex items-end">
        <label class="inline-flex items-center gap-2 rounded-md border border-stone-700/80 bg-black/35 px-3 py-2 text-sm text-stone-200">
            <input type="hidden" name="is_active" value="0">
            <input
                type="checkbox"
                name="is_active"
                value="1"
                @checked(old('is_active', $world->is_active ?? true))
                class="h-4 w-4 rounded border-stone-600 bg-black text-amber-400 focus:ring-amber-500/40"
            >
            Aktiv
        </label>
        @error('is_active')
            <p class="ml-3 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="mt-6 flex flex-wrap gap-3">
    <button type="submit" class="ui-btn ui-btn-accent inline-flex">{{ $submitLabel }}</button>
    <a href="{{ route('admin.worlds.index') }}" class="ui-btn inline-flex">Abbrechen</a>
</div>
