@extends('layouts.auth')

@section('title', 'Kategorie erstellen · Enzyklopaedie Admin')

@section('content')
    <section class="mx-auto w-full max-w-3xl rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
        <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Wissenszentrum · Admin</p>
        <h1 class="font-heading text-3xl text-stone-100">Kategorie erstellen</h1>
        <p class="mt-2 text-stone-300">Neue Struktur fuer Weltwissen anlegen.</p>

        <form method="POST" action="{{ route('knowledge.admin.kategorien.store') }}" class="mt-8">
            @csrf
            @include('knowledge.admin.categories._form', [
                'submitLabel' => 'Kategorie speichern',
                'category' => $category,
            ])
        </form>
    </section>
@endsection
