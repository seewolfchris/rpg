@extends('layouts.auth')

@section('title', 'Welt-Lore · Wissenszentrum')

@section('content')
    @php
        $loreCategories = [
            'zeitalter' => 'Zeitalter',
            'machtbloecke' => 'Machtblöcke',
            'regionen' => 'Regionen',
            'kernausdruecke' => 'Kernausdrücke',
        ];
    @endphp

    <section class="mx-auto w-full max-w-5xl space-y-6">
        <header class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <p class="text-xs uppercase tracking-[0.14em] text-amber-400/80">Wissenszentrum · {{ $world->name }}</p>
            <h1 class="mt-2 font-heading text-3xl text-stone-100 sm:text-4xl">Welt-Lore (Markdown)</h1>
            <p class="mt-4 text-base leading-relaxed text-stone-300 sm:text-lg">
                {{ $loreTitle }} · read-only aus dem Repository.
            </p>
        </header>

        @include('knowledge._nav')

        <section class="flex flex-wrap gap-2">
            <a
                href="{{ route('knowledge.lore', ['world' => $world]) }}"
                class="{{ $normalizedCategory === '' ? 'border-amber-500/70 bg-amber-500/20 text-amber-100' : 'border-stone-700/80 bg-black/35 text-stone-200 hover:border-stone-500/80 hover:text-stone-100' }} rounded-md border px-3 py-2 text-xs font-semibold uppercase tracking-widest transition"
            >
                Index
            </a>
            @foreach ($loreCategories as $categoryKey => $categoryLabel)
                <a
                    href="{{ route('knowledge.lore', ['world' => $world, 'category' => $categoryKey]) }}"
                    class="{{ $normalizedCategory === $categoryKey ? 'border-amber-500/70 bg-amber-500/20 text-amber-100' : 'border-stone-700/80 bg-black/35 text-stone-200 hover:border-stone-500/80 hover:text-stone-100' }} rounded-md border px-3 py-2 text-xs font-semibold uppercase tracking-widest transition"
                >
                    {{ $categoryLabel }}
                </a>
            @endforeach
        </section>

        <section class="space-y-4">
            @foreach ($loreSections as $section)
                <article class="rounded-xl border border-stone-800 bg-neutral-900/65 p-5" id="lore-{{ $section['key'] }}">
                    @if (($section['title'] ?? '') !== '')
                        <h2 class="font-heading text-xl text-stone-100">{{ $section['title'] }}</h2>
                    @endif
                    <div class="knowledge-content mt-3 text-[#cccccc]">
                        {!! $section['html'] !!}
                    </div>
                </article>
            @endforeach
        </section>
    </section>
@endsection
