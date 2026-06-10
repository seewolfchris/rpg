@extends('layouts.auth')

@section('title', 'Benutzerverwaltung | C76-RPG')

@section('content')
    <section class="ui-page-wide rounded-2xl border border-stone-800 bg-neutral-900/60 p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs uppercase tracking-widest text-amber-300/80">Admin</p>
                <h1 class="mt-2 font-heading text-3xl text-stone-100 sm:text-4xl">Benutzerverwaltung</h1>
                <p class="mt-3 max-w-3xl text-sm text-stone-300">
                    Suche, Stammdaten, Plattformrechte und Accountstatus zentral verwalten.
                    Benutzer mit SL-Recht dürfen eigene Kampagnen erstellen und werden dort automatisch Kampagnenleitung und SL.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.users.create') }}" class="ui-btn ui-btn-accent inline-flex">Benutzer erstellen</a>
                <a href="{{ route('admin.users.moderation.index') }}" class="ui-btn inline-flex">Moderationsansicht</a>
            </div>
        </div>
    </section>

    <section class="ui-page-wide mt-6 rounded-2xl border border-stone-800 bg-neutral-900/60 p-4 sm:p-6">
        <form method="GET" action="{{ route('admin.users.index') }}" class="grid gap-3 lg:grid-cols-[minmax(16rem,1fr)_auto_auto_auto_auto_auto]">
            <label for="q" class="sr-only">Suche</label>
            <input
                id="q"
                name="q"
                value="{{ $filters['q'] }}"
                placeholder="Name oder E-Mail"
                class="min-w-0 rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
            >

            <label for="status" class="sr-only">Status</label>
            <select id="status" name="status" class="rounded-md border border-stone-600/80 bg-neutral-900/80 px-3 py-2.5 text-xs uppercase tracking-wider text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40">
                <option value="all" @selected($filters['status'] === 'all')>Status: Alle</option>
                <option value="{{ \App\Enums\UserStatus::ACTIVE->value }}" @selected($filters['status'] === \App\Enums\UserStatus::ACTIVE->value)>Status: Aktiv</option>
                <option value="{{ \App\Enums\UserStatus::PENDING->value }}" @selected($filters['status'] === \App\Enums\UserStatus::PENDING->value)>Status: Ausstehend</option>
                <option value="{{ \App\Enums\UserStatus::SUSPENDED->value }}" @selected($filters['status'] === \App\Enums\UserStatus::SUSPENDED->value)>Status: Gesperrt</option>
            </select>

            <label for="role" class="sr-only">Rolle</label>
            <select id="role" name="role" class="rounded-md border border-stone-600/80 bg-neutral-900/80 px-3 py-2.5 text-xs uppercase tracking-wider text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40">
                <option value="all" @selected($filters['role'] === 'all')>Rolle: Alle</option>
                <option value="{{ \App\Enums\UserRole::ADMIN->value }}" @selected($filters['role'] === \App\Enums\UserRole::ADMIN->value)>Rolle: Admin</option>
                <option value="{{ \App\Enums\UserRole::PLAYER->value }}" @selected($filters['role'] === \App\Enums\UserRole::PLAYER->value)>Rolle: Spieler</option>
            </select>

            <label for="sl" class="sr-only">SL-Recht</label>
            <select id="sl" name="sl" class="rounded-md border border-stone-600/80 bg-neutral-900/80 px-3 py-2.5 text-xs uppercase tracking-wider text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40">
                <option value="all" @selected($filters['sl'] === 'all')>SL: Alle</option>
                <option value="1" @selected($filters['sl'] === '1')>SL: Ja</option>
                <option value="0" @selected($filters['sl'] === '0')>SL: Nein</option>
            </select>

            <label for="moderation" class="sr-only">Moderationsrecht</label>
            <select id="moderation" name="moderation" class="rounded-md border border-stone-600/80 bg-neutral-900/80 px-3 py-2.5 text-xs uppercase tracking-wider text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40">
                <option value="all" @selected($filters['moderation'] === 'all')>Moderation: Alle</option>
                <option value="1" @selected($filters['moderation'] === '1')>Moderation: Frei</option>
                <option value="0" @selected($filters['moderation'] === '0')>Moderation: Standard</option>
            </select>

            <button type="submit" class="ui-btn inline-flex">Filtern</button>
        </form>

        <div class="mt-5 overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-800 text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-widest text-stone-400">
                        <th class="px-3 py-3">Nutzer</th>
                        <th class="px-3 py-3">Status</th>
                        <th class="px-3 py-3">Rolle</th>
                        <th class="px-3 py-3">Rechte</th>
                        <th class="px-3 py-3">Kampagnen</th>
                        <th class="px-3 py-3 text-right">Aktionen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-900">
                    @forelse ($users as $user)
                        @php($roleValue = $user->role instanceof \App\Enums\UserRole ? $user->role->value : (string) $user->role)
                        @php($statusValue = $user->status instanceof \App\Enums\UserStatus ? $user->status->value : (string) $user->status)
                        @php($statusLabel = match ($statusValue) {
                            \App\Enums\UserStatus::ACTIVE->value => 'Aktiv',
                            \App\Enums\UserStatus::PENDING->value => 'Ausstehend',
                            default => 'Gesperrt',
                        })
                        <tr>
                            <td class="px-3 py-3">
                                <p class="font-semibold text-stone-100">{{ $user->name }}</p>
                                <p class="text-xs text-stone-400">{{ $user->email }}</p>
                            </td>
                            <td class="px-3 py-3">
                                <span class="inline-flex rounded-full border px-2 py-1 text-xs uppercase tracking-widest
                                    @if ($statusValue === \App\Enums\UserStatus::ACTIVE->value)
                                        border-emerald-500/60 text-emerald-200
                                    @elseif ($statusValue === \App\Enums\UserStatus::PENDING->value)
                                        border-amber-500/70 text-amber-200
                                    @else
                                        border-red-500/70 text-red-200
                                    @endif
                                ">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <span class="inline-flex rounded-full border px-2 py-1 text-xs uppercase tracking-widest {{ $roleValue === \App\Enums\UserRole::ADMIN->value ? 'border-amber-500/70 text-amber-200' : 'border-stone-600 text-stone-300' }}">
                                    {{ \App\Enums\UserRole::labelFor($roleValue) }}
                                </span>
                            </td>
                            <td class="px-3 py-3 text-xs text-stone-300">
                                <span class="block">SL-Recht: {{ $user->can_create_campaigns ? 'Ja' : 'Nein' }}</span>
                                <span class="block">Moderationsfrei: {{ $user->can_post_without_moderation ? 'Ja' : 'Nein' }}</span>
                            </td>
                            <td class="px-3 py-3 text-xs text-stone-400">
                                <span class="block">Kampagnenleitung: {{ $user->owned_campaigns_count }}</span>
                                <span class="block">Teilnahmen: {{ $user->campaign_memberships_count }}</span>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('admin.users.show', $user) }}" class="ui-btn inline-flex">Details</a>
                                    <a href="{{ route('admin.users.edit', $user) }}" class="ui-btn inline-flex">Bearbeiten</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-6 text-center text-stone-400">Keine Benutzer gefunden.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5">
            {{ $users->links() }}
        </div>
    </section>
@endsection
