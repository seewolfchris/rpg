@php
    $stats = [
        'strength' => 'Staerke',
        'dexterity' => 'Geschick',
        'constitution' => 'Konstitution',
        'intelligence' => 'Intelligenz',
        'wisdom' => 'Weisheit',
        'charisma' => 'Charisma',
    ];
@endphp

<div class="space-y-5">
    <div>
        <label for="name" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Name</label>
        <input
            id="name"
            type="text"
            name="name"
            value="{{ old('name', $character->name ?? '') }}"
            required
            maxlength="120"
            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            placeholder="z. B. Seraphine von Dornfels"
        >
        @error('name')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="epithet" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Beiname</label>
        <input
            id="epithet"
            type="text"
            name="epithet"
            value="{{ old('epithet', $character->epithet ?? '') }}"
            maxlength="120"
            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            placeholder="z. B. die Ascheklinge"
        >
        @error('epithet')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="bio" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Biografie</label>
        <textarea
            id="bio"
            name="bio"
            rows="7"
            required
            class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-3 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            placeholder="Herkunft, Ziele, Makel und dunkle Geheimnisse ..."
        >{{ old('bio', $character->bio ?? '') }}</textarea>
        @error('bio')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="avatar" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Charakterbild</label>
        <input
            id="avatar"
            type="file"
            name="avatar"
            accept="image/jpeg,image/png,image/webp,image/avif"
            class="block w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-3 py-2 text-sm text-stone-200 file:mr-3 file:rounded file:border-0 file:bg-amber-500/20 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:uppercase file:tracking-[0.08em] file:text-amber-100 hover:file:bg-amber-500/35"
        >
        <p class="mt-2 text-xs text-stone-400">Erlaubt: JPG, PNG, WEBP, AVIF bis 3 MB.</p>
        @error('avatar')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror

        @if (!empty($character?->avatar_path))
            <div class="mt-4 flex items-center gap-4 rounded-md border border-stone-700/70 bg-neutral-900/70 p-3">
                <img
                    src="{{ $character->avatarUrl() }}"
                    alt="Aktuelles Charakterbild"
                    class="h-20 w-16 rounded object-cover"
                >
                <label class="flex items-center gap-2 text-sm text-stone-300">
                    <input
                        type="checkbox"
                        name="remove_avatar"
                        value="1"
                        @checked(old('remove_avatar'))
                        class="h-4 w-4 rounded border-stone-500 bg-neutral-900 text-amber-500 focus:ring-amber-500/60"
                    >
                    Bild entfernen
                </label>
            </div>
        @endif
    </div>

    <div>
        <h2 class="font-heading text-xl text-stone-100">Werte (d20)</h2>
        <p class="mt-1 text-sm text-stone-400">Jeder Wert muss zwischen 1 und 20 liegen.</p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($stats as $key => $label)
                <div>
                    <label for="{{ $key }}" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">{{ $label }}</label>
                    <input
                        id="{{ $key }}"
                        type="number"
                        name="{{ $key }}"
                        min="1"
                        max="20"
                        required
                        value="{{ old($key, $character->{$key} ?? 10) }}"
                        class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    >
                    @error($key)
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
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
            href="{{ isset($character) ? route('characters.show', $character) : route('characters.index') }}"
            class="rounded-md border border-stone-600/80 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
        >
            Abbrechen
        </a>
    </div>
</div>
