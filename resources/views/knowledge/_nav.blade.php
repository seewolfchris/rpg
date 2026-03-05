@php
    $knowledgeTabs = [
        ['route' => 'knowledge.index', 'label' => 'Uebersicht'],
        ['route' => 'knowledge.how-to-play', 'label' => 'Wie spielt man?'],
        ['route' => 'knowledge.rules', 'label' => 'Regelwerk'],
        ['route' => 'knowledge.encyclopedia', 'pattern' => 'knowledge.encyclopedia*', 'label' => 'Enzyklopaedie'],
    ];
@endphp

<nav class="rounded-xl border border-stone-800 bg-neutral-900/70 p-3" aria-label="Wissenszentrum Navigation">
    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($knowledgeTabs as $tab)
            @php($isCurrent = request()->routeIs($tab['pattern'] ?? $tab['route']))
            <a
                href="{{ route($tab['route']) }}"
                class="{{ $isCurrent ? 'border-amber-500/70 bg-amber-500/20 text-amber-100' : 'border-stone-700/80 bg-black/35 text-stone-200 hover:border-stone-500/80 hover:text-stone-100' }} rounded-md border px-3 py-2 text-center text-xs font-semibold uppercase tracking-[0.1em] transition"
                @if ($isCurrent) aria-current="page" @endif
            >
                {{ $tab['label'] }}
            </a>
        @endforeach
    </div>
</nav>
