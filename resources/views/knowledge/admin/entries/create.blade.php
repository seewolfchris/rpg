@extends('layouts.auth')

@section('title', 'Enzyklopädie-Eintrag erstellen')

@section('content')
    <section class="mx-auto w-full max-w-4xl rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
        <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Wissenszentrum · Admin</p>
        <h1 class="font-heading text-3xl text-stone-100">Eintrag erstellen</h1>
        <p class="mt-2 text-stone-300">Kategorie: {{ $category->name }}</p>

        <form method="POST" action="{{ route('knowledge.admin.kategorien.eintraege.store', ['world' => $world, 'encyclopediaCategory' => $category]) }}" class="mt-8">
            @csrf
            @include('knowledge.admin.entries._form', [
                'submitLabel' => 'Eintrag speichern',
                'category' => $category,
                'entry' => $entry,
                'world' => $world,
            ])
        </form>
    </section>
@endsection
