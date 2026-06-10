@php
    /** @var \App\Models\User|null $user */
    $isEditing = $user instanceof \App\Models\User && $user->exists;
    $selectedRole = old('role', $isEditing ? ($user->role instanceof \App\Enums\UserRole ? $user->role->value : (string) $user->role) : \App\Enums\UserRole::PLAYER->value);
    $selectedStatus = old('status', $isEditing ? ($user->status instanceof \App\Enums\UserStatus ? $user->status->value : (string) $user->status) : \App\Enums\UserStatus::PENDING->value);
    $canCreateCampaigns = (bool) old('can_create_campaigns', $isEditing ? $user->can_create_campaigns : false);
    $canPostWithoutModeration = (bool) old('can_post_without_moderation', $isEditing ? $user->can_post_without_moderation : false);
@endphp

<x-form-error-summary class="mb-6" />

@error('user')
    <p class="mb-4 text-sm text-red-300">{{ $message }}</p>
@enderror

<div class="grid gap-6 lg:grid-cols-2">
    <div>
        <label for="name" class="mb-2 block text-xs font-semibold uppercase tracking-widest text-stone-300">Name</label>
        <input
            id="name"
            name="name"
            type="text"
            value="{{ old('name', $isEditing ? $user->name : '') }}"
            maxlength="255"
            required
            class="w-full rounded-md border border-stone-700/80 bg-black/45 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/35"
        >
        @error('name')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="email" class="mb-2 block text-xs font-semibold uppercase tracking-widest text-stone-300">E-Mail</label>
        <input
            id="email"
            name="email"
            type="email"
            value="{{ old('email', $isEditing ? $user->email : '') }}"
            maxlength="255"
            required
            class="w-full rounded-md border border-stone-700/80 bg-black/45 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/35"
        >
        @error('email')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="password" class="mb-2 block text-xs font-semibold uppercase tracking-widest text-stone-300">Passwort</label>
        <input
            id="password"
            name="password"
            type="password"
            autocomplete="new-password"
            @if (! $isEditing) required @endif
            class="w-full rounded-md border border-stone-700/80 bg-black/45 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/35"
        >
        @if ($isEditing)
            <p class="mt-2 text-xs text-stone-500">Leer lassen, um das Passwort unverändert zu lassen.</p>
        @endif
        @error('password')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="password_confirmation" class="mb-2 block text-xs font-semibold uppercase tracking-widest text-stone-300">Passwort bestätigen</label>
        <input
            id="password_confirmation"
            name="password_confirmation"
            type="password"
            autocomplete="new-password"
            @if (! $isEditing) required @endif
            class="w-full rounded-md border border-stone-700/80 bg-black/45 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/35"
        >
    </div>

    <div>
        <label for="role" class="mb-2 block text-xs font-semibold uppercase tracking-widest text-stone-300">Plattformrolle</label>
        <select
            id="role"
            name="role"
            required
            class="w-full rounded-md border border-stone-700/80 bg-black/45 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/35"
        >
            <option value="{{ \App\Enums\UserRole::PLAYER->value }}" @selected($selectedRole === \App\Enums\UserRole::PLAYER->value)>Spieler</option>
            <option value="{{ \App\Enums\UserRole::ADMIN->value }}" @selected($selectedRole === \App\Enums\UserRole::ADMIN->value)>Admin</option>
        </select>
        @error('role')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="status" class="mb-2 block text-xs font-semibold uppercase tracking-widest text-stone-300">Accountstatus</label>
        <select
            id="status"
            name="status"
            required
            class="w-full rounded-md border border-stone-700/80 bg-black/45 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/35"
        >
            <option value="{{ \App\Enums\UserStatus::PENDING->value }}" @selected($selectedStatus === \App\Enums\UserStatus::PENDING->value)>Ausstehend</option>
            <option value="{{ \App\Enums\UserStatus::ACTIVE->value }}" @selected($selectedStatus === \App\Enums\UserStatus::ACTIVE->value)>Aktiv</option>
            <option value="{{ \App\Enums\UserStatus::SUSPENDED->value }}" @selected($selectedStatus === \App\Enums\UserStatus::SUSPENDED->value)>Gesperrt</option>
        </select>
        @error('status')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="inline-flex h-full w-full items-center gap-3 rounded-md border border-stone-700/80 bg-black/35 px-3 py-2 text-sm text-stone-200">
            <input type="hidden" name="can_create_campaigns" value="0">
            <input
                type="checkbox"
                name="can_create_campaigns"
                value="1"
                @checked($canCreateCampaigns)
                class="h-4 w-4 rounded border-stone-600 bg-black text-amber-400 focus:ring-amber-500/40"
            >
            <span>
                <span class="block text-xs font-semibold uppercase tracking-widest text-stone-300">SL-Recht</span>
                <span class="block text-xs text-stone-500">Darf eigene Kampagnen leiten.</span>
            </span>
        </label>
        @error('can_create_campaigns')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="inline-flex h-full w-full items-center gap-3 rounded-md border border-stone-700/80 bg-black/35 px-3 py-2 text-sm text-stone-200">
            <input type="hidden" name="can_post_without_moderation" value="0">
            <input
                type="checkbox"
                name="can_post_without_moderation"
                value="1"
                @checked($canPostWithoutModeration)
                class="h-4 w-4 rounded border-stone-600 bg-black text-amber-400 focus:ring-amber-500/40"
            >
            <span>
                <span class="block text-xs font-semibold uppercase tracking-widest text-stone-300">Ohne Moderation posten</span>
                <span class="block text-xs text-stone-500">Beiträge werden direkt freigegeben.</span>
            </span>
        </label>
        @error('can_post_without_moderation')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <div class="lg:col-span-2">
        <label for="status_reason" class="mb-2 block text-xs font-semibold uppercase tracking-widest text-stone-300">Sperrgrund</label>
        <input
            id="status_reason"
            name="status_reason"
            type="text"
            value="{{ old('status_reason', $isEditing ? (string) $user->status_reason : '') }}"
            maxlength="2000"
            placeholder="Optional, nur bei gesperrten Accounts relevant"
            class="w-full rounded-md border border-stone-700/80 bg-black/45 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/35"
        >
        @error('status_reason')
            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="mt-6 rounded-md border border-stone-800 bg-black/35 px-4 py-3 text-sm text-stone-300">
    Bei Admin-erstellten Benutzern wird keine Terms-Zustimmung gespeichert. Zustimmung wird nur gesetzt, wenn Benutzer selbst den Registrierungsprozess durchlaufen.
</div>

<div class="mt-6 flex flex-wrap gap-3">
    <button type="submit" class="ui-btn ui-btn-accent inline-flex">{{ $submitLabel }}</button>
    <a href="{{ $cancelUrl }}" class="ui-btn inline-flex">Abbrechen</a>
</div>
