@extends('layouts.auth')

@section('title', 'Passwort zuruecksetzen | Chroniken der Asche')

@section('content')
    <section class="mx-auto w-full max-w-lg rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-2xl shadow-black/50 backdrop-blur-sm sm:p-8">
        <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Wiederherstellung</p>
        <h1 class="font-heading text-3xl text-stone-100">Neues Passwort setzen</h1>
        <p class="font-body mt-2 text-stone-300">Setze ein neues Passwort fuer dein Konto.</p>

        <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-5">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <label for="email" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">E-Mail</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email', request('email')) }}"
                    required
                    autofocus
                    autocomplete="username"
                    class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    placeholder="name@reich.de"
                >
                @error('email')
                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Neues Passwort</label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    placeholder="Mindestens 8 Zeichen"
                >
                @error('password')
                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Passwort bestaetigen</label>
                <input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    placeholder="Noch einmal eingeben"
                >
            </div>

            <button
                type="submit"
                class="w-full rounded-md border border-amber-400/70 bg-amber-500/20 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-amber-100 transition hover:bg-amber-400/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300"
            >
                Passwort speichern
            </button>
        </form>
    </section>
@endsection
