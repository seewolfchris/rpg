<div class="rounded border border-stone-700/80 bg-black/30 px-3 py-2 text-xs uppercase tracking-[0.08em] text-stone-300">
    Probe Post #{{ $post->id }}:
    W20 {{ $roll }}
    @if ($modifier !== 0)
        {{ $modifier > 0 ? '+' : '' }}{{ $modifier }}
    @endif
    = {{ $total }}
    • {{ $outcome }}
</div>
