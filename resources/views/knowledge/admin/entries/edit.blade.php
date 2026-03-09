@extends('layouts.auth')

@section('title', 'Enzyklopädie-Eintrag bearbeiten')

@section('content')
    <section class="mx-auto w-full max-w-4xl rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
        <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Wissenszentrum · Admin</p>
        <h1 class="font-heading text-3xl text-stone-100">Eintrag bearbeiten</h1>
        <p class="mt-2 text-stone-300">Kategorie: {{ $category->name }}</p>

        <form method="POST" action="{{ route('knowledge.admin.kategorien.eintraege.update', ['world' => $world, 'encyclopediaCategory' => $category, 'encyclopediaEntry' => $entry]) }}" class="mt-8">
            @csrf
            @method('PUT')
            @include('knowledge.admin.entries._form', [
                'submitLabel' => 'Änderungen speichern',
                'category' => $category,
                'entry' => $entry,
                'world' => $world,
            ])
        </form>
    </section>
@endsection
