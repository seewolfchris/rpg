<li id="roll-{{ $roll->id }}" class="rounded-lg border border-stone-800 bg-neutral-900/60 p-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-sm text-stone-200">
                <span class="font-semibold">{{ $roll->user->name }}</span>
                <span class="text-stone-500">• {{ $roll->created_at->format('d.m.Y H:i') }}</span>
            </p>

            @if ($roll->character)
                <p class="mt-1 text-xs uppercase tracking-[0.08em] text-amber-300">
                    Charakter: {{ $roll->character->name }}
                </p>
            @endif

            @if ($roll->label)
                <p class="mt-1 text-sm text-stone-300">{{ $roll->label }}</p>
            @endif
        </div>

        <span class="rounded border border-stone-600/80 bg-black/40 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-stone-300">
            {{ strtoupper($roll->roll_mode) }}
        </span>
    </div>

    @php($rollValues = is_array($roll->rolls) ? $roll->rolls : [])
    <p class="mt-3 text-sm text-stone-300">
        Wurf: [{{ implode(', ', $rollValues) }}]
        @if (count($rollValues) > 1)
            -> genommen: {{ $roll->kept_roll }}
        @endif
        {{ $roll->modifier >= 0 ? '+' : '' }}{{ $roll->modifier }}
        = <span class="font-semibold text-amber-200">{{ $roll->total }}</span>
    </p>

    @if ($roll->is_critical_success)
        <p class="mt-2 text-xs uppercase tracking-[0.08em] text-emerald-300">Kritischer Erfolg</p>
    @elseif ($roll->is_critical_failure)
        <p class="mt-2 text-xs uppercase tracking-[0.08em] text-red-300">Kritischer Fehlschlag</p>
    @endif
</li>
