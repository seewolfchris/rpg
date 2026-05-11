@extends('layouts.auth')

@section('title', 'Accountstatus | C76-RPG')

@section('content')
    <section class="mx-auto w-full max-w-lg rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-2xl shadow-black/50 backdrop-blur-sm sm:p-8">
        <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Accountstatus</p>
        <h1 class="font-heading text-3xl text-stone-100">Plattformzugriff eingeschränkt</h1>

        @if ($user->isPending())
            <p class="mt-4 text-stone-300">
                Dein Account wurde erstellt und wartet auf Freischaltung.
                Sobald die Freigabe erfolgt ist, kannst du dich normal anmelden.
            </p>
        @elseif ($user->isSuspended())
            <p class="mt-4 text-stone-300">
                Dein Account ist aktuell gesperrt.
                Der Zugriff auf die Plattform ist derzeit nicht möglich.
            </p>
        @else
            <p class="mt-4 text-stone-300">
                Dein Accountstatus erlaubt aktuell keinen vollständigen Zugriff auf die Plattform.
            </p>
        @endif

        <form method="POST" action="{{ route('logout') }}" class="mt-6">
            @csrf
            <button
                type="submit"
                class="w-full rounded-md border border-amber-400/70 bg-amber-500/20 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-amber-100 transition hover:bg-amber-400/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300"
            >
                Abmelden
            </button>
        </form>
    </section>
@endsection
