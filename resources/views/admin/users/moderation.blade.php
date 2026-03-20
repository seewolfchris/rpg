@extends('layouts.auth')

@section('title', 'Spielerrechte | C76-RPG')

@section('content')
    <section class="rounded-2xl border border-stone-800 bg-neutral-900/60 p-6">
        <p class="text-xs uppercase tracking-widest text-amber-300/80">Admin</p>
        <h1 class="mt-2 font-heading text-3xl text-stone-100 sm:text-4xl">Spielerrechte für Moderation</h1>
        <p class="mt-3 text-sm text-stone-300">
            Vergib gezielt das Recht, Beiträge standardmäßig ohne Freigabe zu veröffentlichen.
        </p>
    </section>

    <section class="mt-6 rounded-2xl border border-stone-800 bg-neutral-900/60 p-4 sm:p-6">
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
                        <th class="px-3 py-3">Rolle</th>
                        <th class="px-3 py-3">Ohne Moderation posten</th>
                        <th class="px-3 py-3 text-right">Aktion</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-900">
                    @forelse ($users as $user)
                        @php($isPlayer = $user->hasRole(\App\Enums\UserRole::PLAYER))
                        <tr>
                            <td class="px-3 py-3">
                                <p class="font-semibold text-stone-100">{{ $user->name }}</p>
                                <p class="text-xs text-stone-400">{{ $user->email }}</p>
                            </td>
                            <td class="px-3 py-3 text-stone-300">{{ strtoupper($user->role->value) }}</td>
                            <td class="px-3 py-3">
                                <span class="inline-flex rounded-full border px-2 py-1 text-xs uppercase tracking-widest {{ $user->can_post_without_moderation ? 'border-emerald-500/60 text-emerald-200' : 'border-stone-600 text-stone-300' }}">
                                    {{ $user->can_post_without_moderation ? 'Aktiv' : 'Inaktiv' }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex justify-end">
                                    @if ($isPlayer)
                                        <form method="POST" action="{{ route('admin.users.moderation.update', ['user' => $user, 'q' => $search !== '' ? $search : null]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="can_post_without_moderation" value="{{ $user->can_post_without_moderation ? '0' : '1' }}">
                                            <button
                                                type="submit"
                                                class="rounded-md border px-3 py-1.5 text-xs font-semibold uppercase tracking-widest transition {{ $user->can_post_without_moderation ? 'border-red-700/80 bg-red-900/20 text-red-200 hover:bg-red-900/40' : 'border-emerald-700/80 bg-emerald-900/20 text-emerald-200 hover:bg-emerald-900/35' }}"
                                            >
                                                {{ $user->can_post_without_moderation ? 'Entziehen' : 'Erteilen' }}
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs uppercase tracking-widest text-stone-500">Nur Player</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-6 text-center text-stone-400">Keine Benutzer gefunden.</td>
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
