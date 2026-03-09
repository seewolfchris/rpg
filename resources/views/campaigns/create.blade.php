@extends('layouts.auth')

@section('title', 'Kampagne erstellen | C76-RPG')

@section('content')
    <section class="mx-auto w-full max-w-4xl rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
        <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Neue Kampagne · {{ $world->name }}</p>
        <h1 class="font-heading text-3xl text-stone-100">Kampagne erstellen</h1>
        <p class="mt-2 text-stone-300">Lege Titel, Status und Weltbeschreibung für den neuen Storybogen fest.</p>

        <form method="POST" action="{{ route('campaigns.store', ['world' => $world]) }}" class="mt-8">
            @csrf
            @include('campaigns._form', ['world' => $world, 'submitLabel' => 'Kampagne erstellen'])
        </form>
    </section>
@endsection
