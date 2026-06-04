@extends('layouts.auth')

@section('title', 'Benutzer verwalten | C76-RPG')

@section('content')
    <section class="ui-page-wide rounded-2xl border border-stone-800 bg-neutral-900/60 p-6">
        <p class="text-xs uppercase tracking-widest text-amber-300/80">Admin</p>
        <h1 class="mt-2 font-heading text-3xl text-stone-100 sm:text-4xl">Benutzer verwalten</h1>
        <p class="mt-3 text-sm text-stone-300">
            Diese Oberfläche steuert globale Plattformrechte und den Accountstatus.
            Kampagnenrollen werden ausschließlich in der jeweiligen Kampagne verwaltet.
        </p>
    </section>

    <section class="ui-page-wide mt-6 rounded-2xl border border-stone-800 bg-neutral-900/60 p-4 sm:p-6">
        <form method="GET" action="{{ route('admin.users.moderation.index') }}" class="mb-4">
            <label for="q" class="sr-only">Suche</label>
            <div class="flex flex-wrap gap-2">
                <input
                    id="q"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Name oder E-Mail"
                    class="min-w-0 flex-1 rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                >
                <button type="submit" class="ui-btn inline-flex">Suchen</button>
            </div>
        </form>

        @error('user')
            <p class="mb-4 text-sm text-red-300">{{ $message }}</p>
        @enderror

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-800 text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-widest text-stone-400">
                        <th class="px-3 py-3">User</th>
                        <th class="px-3 py-3">Accountstatus</th>
                        <th class="px-3 py-3">Plattformrolle</th>
                        <th class="px-3 py-3">Kampagnen anlegen</th>
                        <th class="px-3 py-3">Ohne Moderation posten</th>
                        <th class="px-3 py-3 text-right">Aktionen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-900">
                    @forelse ($users as $user)
                        @php($isAdmin = $user->hasRole(\App\Enums\UserRole::ADMIN))
                        @php($isSelf = auth()->id() === (int) $user->id)
                        @php($isPending = $user->isPending())
                        @php($isSuspended = $user->isSuspended())
                        @php($isActive = $user->isActive())
                        <tr>
                            <td class="px-3 py-3">
                                <p class="font-semibold text-stone-100">{{ $user->name }}</p>
                                <p class="text-xs text-stone-400">{{ $user->email }}</p>
                            </td>
                            <td class="px-3 py-3">
                                <span class="inline-flex rounded-full border px-2 py-1 text-xs uppercase tracking-widest
                                    @if ($isActive)
                                        border-emerald-500/60 text-emerald-200
                                    @elseif ($isPending)
                                        border-amber-500/70 text-amber-200
                                    @else
                                        border-red-500/70 text-red-200
                                    @endif
                                ">
                                    {{ $isActive ? 'Aktiv' : ($isPending ? 'Wartend' : 'Gesperrt') }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <span class="inline-flex rounded-full border px-2 py-1 text-xs uppercase tracking-widest {{ $isAdmin ? 'border-amber-500/70 text-amber-200' : 'border-stone-600 text-stone-300' }}">
                                    {{ $isAdmin ? 'Admin' : 'User' }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <span class="inline-flex rounded-full border px-2 py-1 text-xs uppercase tracking-widest {{ $user->can_create_campaigns ? 'border-emerald-500/60 text-emerald-200' : 'border-stone-600 text-stone-300' }}">
                                    {{ $user->can_create_campaigns ? 'Aktiv' : 'Inaktiv' }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <span class="inline-flex rounded-full border px-2 py-1 text-xs uppercase tracking-widest {{ $user->can_post_without_moderation ? 'border-emerald-500/60 text-emerald-200' : 'border-stone-600 text-stone-300' }}">
                                    {{ $user->can_post_without_moderation ? 'Aktiv' : 'Inaktiv' }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <div class="space-y-3">
                                    <div class="space-y-2 rounded-md border border-stone-800/80 bg-neutral-950/40 p-3">
                                        @if ($isPending)
                                            <div class="flex flex-wrap justify-end gap-2">
                                                <form method="POST" action="{{ route('admin.users.moderation.approve', ['user' => $user, 'q' => $search !== '' ? $search : null]) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="rounded-md border border-emerald-500/70 bg-emerald-500/10 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-emerald-100 transition hover:bg-emerald-500/20">
                                                        Freischalten
                                                    </button>
                                                </form>
                                            </div>

                                            <form method="POST" action="{{ route('admin.users.moderation.suspend', ['user' => $user, 'q' => $search !== '' ? $search : null]) }}" class="flex flex-wrap items-center justify-end gap-2">
                                                @csrf
                                                @method('PATCH')
                                                <label class="sr-only" for="suspend-reason-{{ $user->id }}">Sperrgrund</label>
                                                <input
                                                    id="suspend-reason-{{ $user->id }}"
                                                    name="status_reason"
                                                    type="text"
                                                    maxlength="2000"
                                                    placeholder="Optionaler Sperrgrund"
                                                    class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-3 py-1.5 text-xs text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                                                >
                                                <button type="submit" class="rounded-md border border-red-500/70 bg-red-500/10 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-red-100 transition hover:bg-red-500/20">
                                                    Sperren
                                                </button>
                                            </form>
                                        @elseif ($isActive)
                                            <form method="POST" action="{{ route('admin.users.moderation.suspend', ['user' => $user, 'q' => $search !== '' ? $search : null]) }}" class="flex flex-wrap items-center justify-end gap-2">
                                                @csrf
                                                @method('PATCH')
                                                <label class="sr-only" for="suspend-reason-{{ $user->id }}">Sperrgrund</label>
                                                <input
                                                    id="suspend-reason-{{ $user->id }}"
                                                    name="status_reason"
                                                    type="text"
                                                    maxlength="2000"
                                                    placeholder="Optionaler Sperrgrund"
                                                    class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-3 py-1.5 text-xs text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                                                >
                                                <button type="submit" class="rounded-md border border-red-500/70 bg-red-500/10 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-red-100 transition hover:bg-red-500/20">
                                                    Sperren
                                                </button>
                                            </form>
                                        @else
                                            <div class="flex flex-wrap justify-end gap-2">
                                                <form method="POST" action="{{ route('admin.users.moderation.reactivate', ['user' => $user, 'q' => $search !== '' ? $search : null]) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="rounded-md border border-emerald-500/70 bg-emerald-500/10 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-emerald-100 transition hover:bg-emerald-500/20">
                                                        Reaktivieren
                                                    </button>
                                                </form>
                                            </div>
                                        @endif
                                    </div>

                                    <form method="POST" action="{{ route('admin.users.moderation.update', ['user' => $user, 'q' => $search !== '' ? $search : null]) }}" class="grid gap-2 sm:grid-cols-3">
                                        @csrf
                                        @method('PATCH')
                                        <label class="sr-only" for="role-{{ $user->id }}">Plattformrolle</label>
                                        <select
                                            id="role-{{ $user->id }}"
                                            name="role"
                                            class="rounded-md border border-stone-600/80 bg-neutral-900/80 px-3 py-1.5 text-xs uppercase tracking-wider text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                                        >
                                            <option value="{{ \App\Enums\UserRole::PLAYER->value }}" @selected($user->hasRole(\App\Enums\UserRole::PLAYER))>User</option>
                                            <option value="{{ \App\Enums\UserRole::ADMIN->value }}" @selected($isAdmin)>Admin</option>
                                        </select>

                                        <label class="sr-only" for="create-campaigns-{{ $user->id }}">Kampagnen anlegen</label>
                                        <select
                                            id="create-campaigns-{{ $user->id }}"
                                            name="can_create_campaigns"
                                            class="rounded-md border border-stone-600/80 bg-neutral-900/80 px-3 py-1.5 text-xs uppercase tracking-wider text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                                        >
                                            <option value="0" @selected(! $user->can_create_campaigns)>Create: Aus</option>
                                            <option value="1" @selected($user->can_create_campaigns)>Create: An</option>
                                        </select>

                                        <label class="sr-only" for="post-without-moderation-{{ $user->id }}">Ohne Moderation posten</label>
                                        <select
                                            id="post-without-moderation-{{ $user->id }}"
                                            name="can_post_without_moderation"
                                            class="rounded-md border border-stone-600/80 bg-neutral-900/80 px-3 py-1.5 text-xs uppercase tracking-wider text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                                        >
                                            <option value="0" @selected(! $user->can_post_without_moderation)>Moderation: An</option>
                                            <option value="1" @selected($user->can_post_without_moderation)>Moderation: Aus</option>
                                        </select>

                                        <div class="sm:col-span-3 flex justify-end">
                                            <button
                                                type="submit"
                                                class="rounded-md border border-amber-500/70 bg-amber-500/15 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/30"
                                            >
                                                Rechte speichern
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                @if ($isSelf && $isAdmin)
                                    <p class="mt-2 text-[0.65rem] uppercase tracking-wider text-stone-500">
                                        Eigener Admin-Status kann nicht entzogen werden.
                                    </p>
                                @endif
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
