@extends('layouts.auth')

@section('title', 'Weltueberblick · Wissenszentrum')

@section('content')
    <section class="mx-auto w-full max-w-5xl space-y-6">
        <header class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <p class="text-xs uppercase tracking-[0.14em] text-amber-400/80">Wissenszentrum · {{ $world->name }}</p>
            <h1 class="mt-2 font-heading text-3xl text-stone-100 sm:text-4xl">Weltueberblick (Markdown)</h1>
            <p class="mt-4 text-base leading-relaxed text-stone-300 sm:text-lg">
                Read-only Vorschau aus dem Repo. Keine DB-Synchronisation, kein Schreibzugriff.
            </p>
        </header>

        @include('knowledge._nav')

        <article class="rounded-xl border border-stone-800 bg-neutral-900/65 p-5">
            <div class="knowledge-content text-[#cccccc]">
                {!! $worldOverviewHtml !!}
            </div>
        </article>
    </section>
@endsection
