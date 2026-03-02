@extends('layouts.auth')

@section('title', 'Registrierung | Chroniken der Asche')

@section('content')
    <section class="mx-auto w-full max-w-lg rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-2xl shadow-black/50 backdrop-blur-sm sm:p-8">
        <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Neue Seelen im Nebel</p>
        <h1 class="font-heading text-3xl text-stone-100">Registrierung</h1>
        <p class="font-body mt-2 text-stone-300">Erschaffe deinen Zugang und beginne deine erste Chronik.</p>

        <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-5">
            @csrf

            <div>
                <label for="name" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Name</label>
                <input
                    id="name"
                    type="text"
                    name="name"
                    value="{{ old('name') }}"
                    required
                    autofocus
                    autocomplete="name"
                    class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    placeholder="Dein Chronistenname"
                >
                @error('name')
                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">E-Mail</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
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
                Konto erstellen
            </button>
        </form>

        <p class="mt-6 text-sm text-stone-300">
            Bereits registriert?
            <a href="{{ route('login') }}" class="font-semibold text-amber-300 hover:text-amber-200">Zum Login</a>
        </p>
    </section>
@endsection
