@extends('layouts.auth')

@section('title', 'Regelwerk · Wissenszentrum')

@section('content')
    @php($isWorldContext = isset($world) && $world instanceof \App\Models\World)

    <section class="mx-auto w-full max-w-5xl space-y-6">
        <header class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <p class="text-xs uppercase tracking-[0.14em] text-amber-400/80">Wissenszentrum</p>
            <h1 class="mt-2 font-heading text-3xl text-stone-100 sm:text-4xl">Regelwerk</h1>
            <p class="mt-4 text-base leading-relaxed text-stone-300 sm:text-lg">
                {{ $isWorldContext
                    ? 'Die Basisregeln gelten in dieser Welt und werden durch weltbezogene Lore ergänzt.'
                    : 'Das globale Regelwerk definiert, wie Beitragsfluss, GM-Proben und Moderation konsistent funktionieren.' }}
            </p>
        </header>

        @include('knowledge._nav')

        <section class="space-y-4">
            @foreach ($rulebookSections as $sectionKey => $sectionHtml)
                <article class="knowledge-rulebook-article rounded-xl border border-stone-800 bg-neutral-900/65 p-5" id="regelwerk-{{ $sectionKey }}">
                    <div class="knowledge-content text-[#cccccc]">
                        {!! $sectionHtml !!}
                    </div>
                </article>
            @endforeach
        </section>
    </section>
@endsection
