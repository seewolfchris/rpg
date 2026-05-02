@props([
    'href',
    'label' => 'Zurück',
    'sublabel' => null,
])

<div class="mb-4">
    <a
        href="{{ $href }}"
        class="inline-flex items-center gap-2 rounded-md border border-stone-600/80 bg-black/30 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
    >
        {{ $label }}
    </a>

    @if (is_string($sublabel) && $sublabel !== '')
        <p class="mt-1 text-xs uppercase tracking-[0.08em] text-stone-500">{{ $sublabel }}</p>
    @endif
</div>
