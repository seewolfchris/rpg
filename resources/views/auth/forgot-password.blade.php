@extends('layouts.auth')

@section('title', 'Passwort vergessen | C76-RPG')

@section('content')
    <section class="mx-auto w-full max-w-lg rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-2xl shadow-black/50 backdrop-blur-sm sm:p-8">
        <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Riss im Siegel</p>
        <h1 class="font-heading text-3xl text-stone-100">Passwort vergessen?</h1>
        <p class="font-body mt-2 text-stone-300">
            Gib deine E-Mail ein. Wir senden dir einen Link zum Zurücksetzen deines Passworts.
        </p>

        <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
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

            <button
                type="submit"
                class="w-full rounded-md border border-amber-400/70 bg-amber-500/20 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-amber-100 transition hover:bg-amber-400/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300"
            >
                Reset-Link senden
            </button>
        </form>

        <p class="mt-6 text-sm text-stone-300">
            Zurück zum
            <a href="{{ route('login') }}" class="font-semibold text-amber-300 hover:text-amber-200">Anmelden</a>
        </p>
    </section>
@endsection
