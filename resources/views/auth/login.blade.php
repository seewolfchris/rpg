@extends('layouts.auth')

@section('title', 'Login | Chroniken der Asche')

@section('content')
    <section class="mx-auto w-full max-w-lg rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-2xl shadow-black/50 backdrop-blur-sm sm:p-8">
        <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Rückkehr der Chronisten</p>
        <h1 class="font-heading text-3xl text-stone-100">Anmelden</h1>
        <p class="font-body mt-2 text-stone-300">Betritt wieder die Aschelande und führe deine Geschichte fort.</p>

        <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
            @csrf

            <div>
                <label for="email" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">E-Mail</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
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
                <label for="password" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Passwort</label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    placeholder="********"
                >
                @error('password')
                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-stone-300">
                <input
                    type="checkbox"
                    name="remember"
                    value="1"
                    @checked(old('remember'))
                    class="h-4 w-4 rounded border-stone-500 bg-neutral-900 text-amber-500 focus:ring-amber-500/60"
                >
                Eingeloggt bleiben
            </label>

            <p class="text-sm text-stone-300">
                Passwort vergessen?
                <a href="{{ route('password.request') }}" class="font-semibold text-amber-300 hover:text-amber-200">Reset-Link anfordern</a>
            </p>

            <button
                type="submit"
                class="w-full rounded-md border border-amber-400/70 bg-amber-500/20 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-amber-100 transition hover:bg-amber-400/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300"
            >
                Einloggen
            </button>
        </form>

        <p class="mt-6 text-sm text-stone-300">
            Noch kein Konto?
            <a href="{{ route('register') }}" class="font-semibold text-amber-300 hover:text-amber-200">Jetzt registrieren</a>
        </p>
    </section>
@endsection
