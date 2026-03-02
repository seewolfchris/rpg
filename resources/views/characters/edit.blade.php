@extends('layouts.auth')

@section('title', 'Charakter bearbeiten | Chroniken der Asche')

@section('content')
    <section class="mx-auto w-full max-w-3xl rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
        <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Schicksal neu schreiben</p>
        <h1 class="font-heading text-3xl text-stone-100">Charakter bearbeiten</h1>
        <p class="mt-2 text-stone-300">Aktualisiere die Werte und Geschichte von {{ $character->name }}.</p>

        <form method="POST" action="{{ route('characters.update', $character) }}" enctype="multipart/form-data" class="mt-8">
            @csrf
            @method('PUT')
            @include('characters._form', ['submitLabel' => 'Speichern', 'character' => $character])
        </form>
    </section>
@endsection
